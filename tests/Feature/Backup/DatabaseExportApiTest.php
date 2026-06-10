<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\User;
use App\Modules\Backup\Jobs\ProcessDatabaseExport;
use App\Modules\Backup\Models\DatabaseExport;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class DatabaseExportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'developer'], ['name' => 'Developer', 'slug' => 'developer']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);
        $this->admin->load('roles');

        $this->regularUser = User::factory()->create();
    }

    public function test_admin_can_list_database_exports(): void
    {
        DatabaseExport::create([
            'user_id' => $this->admin->id,
            'export_type' => DatabaseExport::TYPE_FULL,
            'status' => DatabaseExport::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/database-exports');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['uuid', 'status', 'export_type']]]);
    }

    public function test_non_admin_cannot_list_database_exports(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/database-exports')
            ->assertForbidden();
    }

    public function test_admin_can_create_export_and_job_is_dispatched(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/database-exports', [
                'export_type' => 'full',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.export_type', 'full');

        Queue::assertPushed(ProcessDatabaseExport::class);
    }

    public function test_admin_can_create_schema_only_export(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/database-exports', [
                'export_type' => 'schema',
            ])
            ->assertCreated()
            ->assertJsonPath('data.export_type', 'schema');
    }

    public function test_admin_can_scope_export_to_specific_tables(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/database-exports', [
                'export_type' => 'full',
                'tables' => ['users'],
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('database_exports', [
            'user_id' => $this->admin->id,
            'export_type' => 'full',
        ]);
    }

    public function test_invalid_export_type_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/database-exports', [
                'export_type' => 'invalid',
            ])
            ->assertUnprocessable();
    }

    public function test_non_admin_cannot_create_export(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/database-exports', [
                'export_type' => 'full',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_poll_own_export(): void
    {
        $export = DatabaseExport::create([
            'user_id' => $this->admin->id,
            'export_type' => DatabaseExport::TYPE_FULL,
            'status' => DatabaseExport::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/database-exports/{$export->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $export->uuid);
    }

    public function test_admin_cannot_poll_another_users_export(): void
    {
        $adminRole = Role::where('slug', 'developer')->firstOrFail();
        $otherAdmin = User::factory()->create();
        $otherAdmin->roles()->attach($adminRole);
        $otherAdmin->load('roles');

        $export = DatabaseExport::create([
            'user_id' => $otherAdmin->id,
            'export_type' => DatabaseExport::TYPE_FULL,
            'status' => DatabaseExport::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/database-exports/{$export->uuid}")
            ->assertForbidden();
    }

    public function test_download_expired_export_returns_410(): void
    {
        $export = DatabaseExport::create([
            'user_id' => $this->admin->id,
            'export_type' => DatabaseExport::TYPE_FULL,
            'status' => DatabaseExport::STATUS_COMPLETED,
            'file_path' => 'database-exports/test.sql',
            'file_name' => 'test.sql',
            'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
            ->get("/api/v1/database-exports/{$export->uuid}/download")
            ->assertStatus(410);
    }

    public function test_admin_can_list_tables(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/database-exports/tables')
            ->assertOk()
            ->assertJsonStructure(['data' => [['name']]]);
    }

    public function test_download_returns_404_when_file_missing_from_disk(): void
    {
        Storage::fake(config('database-exports.disk', 'local'));

        $export = DatabaseExport::create([
            'user_id' => $this->admin->id,
            'export_type' => DatabaseExport::TYPE_FULL,
            'status' => DatabaseExport::STATUS_COMPLETED,
            'file_path' => 'database-exports/missing-file.sql',
            'file_name' => 'missing-file.sql',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->admin)
            ->get("/api/v1/database-exports/{$export->uuid}/download")
            ->assertNotFound();
    }

    public function test_download_returns_404_when_file_path_is_missing(): void
    {
        $export = DatabaseExport::create([
            'user_id' => $this->admin->id,
            'export_type' => DatabaseExport::TYPE_FULL,
            'status' => DatabaseExport::STATUS_COMPLETED,
            'file_path' => null,
            'file_name' => 'missing-path.sql',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->admin)
            ->get("/api/v1/database-exports/{$export->uuid}/download")
            ->assertNotFound();
    }

    public function test_full_export_excludes_configured_tables_for_mysql(): void
    {
        $excludedTables = [
            'telescope_entries',
            'pulse_entries',
            'activity_log',
        ];

        $export = DatabaseExport::create([
            'user_id' => $this->admin->id,
            'export_type' => DatabaseExport::TYPE_FULL,
            'status' => DatabaseExport::STATUS_PENDING,
            'tables' => null,
        ]);

        $job = new ProcessDatabaseExport($export);
        $jobReflection = new ReflectionClass($job);
        $buildDumper = $jobReflection->getMethod('buildDumper');

        $dumper = $buildDumper->invoke($job, 'mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'testing',
            'username' => 'root',
            'password' => '',
            'dump' => [
                'exclude_tables' => $excludedTables,
            ],
        ], $export);

        $dumperReflection = new ReflectionClass($dumper);
        $excludeTables = $dumperReflection->getParentClass()->getProperty('excludeTables')->getValue($dumper);

        $this->assertSame($excludedTables, $excludeTables);
    }
}
