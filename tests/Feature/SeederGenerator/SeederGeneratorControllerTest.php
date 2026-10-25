<?php

declare(strict_types=1);

namespace Tests\Feature\SeederGenerator;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\SeederGenerator\AllowedRecord;
use Tests\Fixtures\SeederGenerator\AllowedRecordFactory;
use Tests\TestCase;
use Tests\TestEnvironment;

class SeederGeneratorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $this->isolateProjectFiles();
        $this->assertSame(realpath($this->testEnvironment->path.'/project'), realpath(base_path()));
        $this->assertSame(realpath(base_path('database')), realpath(database_path()));
        File::ensureDirectoryExists(database_path('seeders'));

        Schema::create('allowed_records', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('unique_key')->unique();
            $table->string('secret')->nullable();
        });
        Schema::create('hidden_fixture_records', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('secret');
        });
        DB::table('allowed_records')->insert([
            'label' => 'Visible fixture', 'unique_key' => 'fixture-original', 'secret' => 'SEC007_SECRET_SENTINEL',
        ]);
        DB::table('hidden_fixture_records')->insert([
            'label' => 'Hidden fixture', 'secret' => 'SEC007_HIDDEN_SENTINEL',
        ]);
        config(['seeder-generator.allowed_tables' => ['allowed_records' => $this->tablePolicy()]]);

        Artisan::shouldReceive('call')->andReturn(0)->byDefault();
        Artisan::shouldReceive('output')->andReturn('Synthetic command output')->byDefault();
    }

    /** @return array<string, mixed> */
    private function tablePolicy(): array
    {
        return [
            'columns' => ['label', 'unique_key'],
            'unique_columns' => ['unique_key'],
            'modes' => ['data', 'factory'],
            'operations' => ['preview', 'generate', 'execute', 'run'],
            'model' => AllowedRecord::class,
            'factory' => AllowedRecordFactory::class,
            'factory_trusted' => true,
            'truncate' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'table' => 'allowed_records', 'mode' => 'data', 'limit' => 2,
            'truncate' => false, 'idempotent' => true, 'unique_column' => 'unique_key',
            'seeder_name' => 'screen_records',
        ];
    }

    private function account(?string $slug = 'developer'): User
    {
        $user = User::factory()->create();

        if ($slug !== null) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->getConnection()->table('roles')->where('id', $role->id)->update(['slug' => $slug]);
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    public function test_partial_replacement_is_refused_through_every_mutating_entry_point(): void
    {
        $this->actingAs($this->account());
        config(['seeder-generator.allowed_tables.allowed_records.truncate' => true]);
        DB::table('allowed_records')->insert([
            ['label' => 'Second row', 'unique_key' => 'fixture-second', 'secret' => 'PRESERVE_SECOND'],
            ['label' => 'Third row', 'unique_key' => 'fixture-third', 'secret' => 'PRESERVE_THIRD'],
        ]);
        $before = $this->records();
        $payload = [...$this->payload(), 'truncate' => true];

        foreach (['preview', 'generate'] as $operation) {
            $this->postJson(route('admin.dev.seeder-generator.'.$operation), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('truncate');
            $this->assertSame($before, $this->records());
        }
        $this->postJson(route('admin.dev.seeder-generator.execute'), $payload)
            ->assertUnprocessable()->assertJsonPath('data.success', false)->assertJsonPath('data.records', 0);
        $this->assertSame($before, $this->records());
        $this->assertSame([], File::allFiles(database_path('seeders')));
    }

    private function environment(string $environment): void
    {
        $this->app->instance('env', $environment);
        config(['app.env' => $environment]);
    }

    /** @return array{allowed: string, hidden: string} */
    private function records(): array
    {
        return [
            'allowed' => DB::table('allowed_records')->orderBy('id')->get()->toJson(),
            'hidden' => DB::table('hidden_fixture_records')->orderBy('id')->get()->toJson(),
        ];
    }

    private function assertEndpointsDenied(int $status = 403): void
    {
        $before = $this->records();
        $this->getJson(route('admin.dev.seeder-generator.index'))->assertStatus($status);
        $this->getJson(route('admin.dev.seeder-generator.tables'))->assertStatus($status);

        foreach (['preview', 'generate', 'execute'] as $operation) {
            $this->postJson(route('admin.dev.seeder-generator.'.$operation), $this->payload())->assertStatus($status);
        }

        $this->postJson(route('admin.dev.seeder-generator.run'), ['class' => 'DatabaseSeeder'])->assertStatus($status);
        $this->assertSame($before, $this->records());
        $this->assertCount(0, File::allFiles(database_path('seeders')));
        Artisan::shouldNotHaveReceived('call');
    }

    public function test_guest_cannot_access_any_generator_endpoint(): void
    {
        $this->environment('local');
        $this->assertEndpointsDenied(401);
    }

    #[DataProvider('nonDeveloperRoles')]
    public function test_non_developer_cannot_access_any_generator_endpoint(?string $role): void
    {
        $this->environment('local');
        $this->actingAs($this->account($role));
        $this->assertEndpointsDenied();
    }

    /** @return array<string, array{?string}> */
    public static function nonDeveloperRoles(): array
    {
        return ['no role' => [null], 'user' => ['user'], 'admin' => ['admin'], 'super admin' => ['super-admin'], 'wrong case' => ['Developer']];
    }

    #[DataProvider('deniedEnvironments')]
    public function test_developer_cannot_access_generator_outside_local_or_testing(string $environment): void
    {
        $this->actingAs($this->account());
        $this->environment($environment);
        $this->assertEndpointsDenied();
    }

    /** @return array<string, array{string}> */
    public static function deniedEnvironments(): array
    {
        return ['production' => ['production'], 'staging' => ['staging'], 'preview' => ['preview'], 'wrong case' => ['LOCAL']];
    }

    public function test_environment_disagreement_cannot_enable_generator(): void
    {
        $this->environment('local');
        config(['app.env' => 'testing']);
        $this->actingAs($this->account());
        $this->assertEndpointsDenied();
    }

    public function test_bearer_star_cannot_enable_generator_for_web_developer(): void
    {
        $this->environment('local');
        $developer = $this->account();
        $token = $developer->createToken('Synthetic fixture', ['*'])->plainTextToken;
        $this->actingAs($developer, 'web')->withHeader('Authorization', 'Bearer '.$token);
        $this->assertEndpointsDenied();
    }

    public function test_permission_and_changed_super_role_cannot_replace_literal_developer(): void
    {
        $this->environment('local');
        $user = $this->account('admin');
        $permission = Permission::create(['name' => 'Synthetic ability', 'slug' => 'synthetic-ability']);
        $permission->getConnection()->table('permissions')->where('id', $permission->id)->update(['slug' => 'useDeveloperTools']);
        $user->permissions()->attach($permission->id);
        config(['permissions.super_role' => 'admin', 'permissions.functional' => ['useDeveloperTools']]);
        $this->actingAs($user);
        $this->assertEndpointsDenied();
    }

    public function test_default_empty_allowlist_exposes_no_tables_and_denies_direct_payloads(): void
    {
        $this->environment('local');
        $this->actingAs($this->account());
        config(['seeder-generator.allowed_tables' => []]);
        $before = $this->records();
        $this->getJson(route('admin.dev.seeder-generator.tables'))->assertOk()->assertExactJson(['data' => []]);

        foreach (['preview', 'generate', 'execute'] as $operation) {
            $this->postJson(route('admin.dev.seeder-generator.'.$operation), $this->payload())
                ->assertUnprocessable()->assertJsonValidationErrors('table');
        }

        $this->assertSame($before, $this->records());
        $this->assertCount(0, File::allFiles(database_path('seeders')));
        Artisan::shouldNotHaveReceived('call');
    }

    #[DataProvider('allowedEnvironments')]
    public function test_developer_lists_and_previews_only_authorized_fixture_columns(string $environment): void
    {
        $this->environment($environment);
        $this->actingAs($this->account());
        $response = $this->getJson(route('admin.dev.seeder-generator.tables'))->assertOk();
        $this->assertSame(['allowed_records'], array_column($response->json('data'), 'name'));
        $this->assertSame(['label', 'unique_key'], array_column($response->json('data.0.columns'), 'name'));
        $before = $this->records();
        $preview = $this->postJson(route('admin.dev.seeder-generator.preview'), $this->payload())
            ->assertOk()->assertJsonPath('data.class_name', 'GeneratedScreenRecordsSeeder')
            ->assertJsonPath('data.file_name', 'GeneratedScreenRecordsSeeder.php');
        $this->assertStringContainsString('Visible fixture', $preview->json('data.code'));
        $this->assertStringNotContainsString('SEC007_SECRET_SENTINEL', $preview->getContent());
        $this->assertStringNotContainsString('SEC007_HIDDEN_SENTINEL', $preview->getContent());
        $this->assertSame($before, $this->records());
        $this->assertCount(0, File::allFiles(database_path('seeders')));
    }

    /** @return array<string, array{string}> */
    public static function allowedEnvironments(): array
    {
        return ['local' => ['local'], 'isolated testing' => ['testing']];
    }

    #[DataProvider('invalidPayloads')]
    public function test_direct_invalid_payload_is_rejected_before_database_or_file_mutation(array $changes, string $field): void
    {
        $this->environment('local');
        $this->actingAs($this->account());
        $before = $this->records();

        foreach (['preview', 'generate', 'execute'] as $operation) {
            $this->postJson(route('admin.dev.seeder-generator.'.$operation), array_replace($this->payload(), $changes))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertSame($before, $this->records());
        $this->assertCount(0, File::allFiles(database_path('seeders')));
        Artisan::shouldNotHaveReceived('call');
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): array
    {
        return [
            'hidden table' => [['table' => 'hidden_fixture_records'], 'table'],
            'identity table' => [['table' => 'users'], 'table'],
            'secret column' => [['unique_column' => 'secret'], 'unique_column'],
            'non unique column' => [['unique_column' => 'label'], 'unique_column'],
            'php injection' => [['unique_column' => "unique_key'); throw new \\RuntimeException('injected');//"], 'unique_column'],
            'missing idempotency key' => [['unique_column' => null], 'unique_column'],
            'zero limit' => [['limit' => 0], 'limit'],
            'excessive limit' => [['limit' => 1001], 'limit'],
            'unsupported mode' => [['mode' => 'custom'], 'mode'],
            'path escape' => [['seeder_name' => '../escaped'], 'seeder_name'],
            'name newline' => [['seeder_name' => "screen_records\n"], 'seeder_name'],
            'truncate not enabled' => [['truncate' => true], 'truncate'],
        ];
    }

    public function test_developer_generates_owned_artifact_and_runs_with_existing_class_payload(): void
    {
        $this->environment('local');
        $this->actingAs($this->account());
        $before = $this->records();
        $response = $this->postJson(route('admin.dev.seeder-generator.generate'), $this->payload())
            ->assertOk()->assertJsonPath('data.class_name', 'GeneratedScreenRecordsSeeder');
        $path = database_path('seeders/GeneratedScreenRecordsSeeder.php');
        $this->assertSame($path, $response->json('data.path'));
        $this->assertFileExists($path);
        $this->assertStringNotContainsString('SEC007_SECRET_SENTINEL', File::get($path));
        $this->postJson(route('admin.dev.seeder-generator.run'), ['class' => 'GeneratedScreenRecordsSeeder'])
            ->assertOk()->assertJsonPath('data.exit_code', 0)->assertJsonPath('data.output', 'Synthetic command output');
        $this->assertSame($before, $this->records());
        Artisan::shouldHaveReceived('call')->once()->with('db:seed', \Mockery::on(fn (array $arguments): bool => in_array($arguments['--class'] ?? null, ['GeneratedScreenRecordsSeeder', 'Database\\Seeders\\GeneratedScreenRecordsSeeder'], true)
            && ($arguments['--no-interaction'] ?? null) === true
            && ! array_key_exists('--force', $arguments)
        ));
    }

    #[DataProvider('arbitrarySeederClasses')]
    public function test_run_refuses_arbitrary_missing_or_legacy_seeders_without_artisan(string $class): void
    {
        $this->environment('local');
        $this->actingAs($this->account());
        File::put(database_path('seeders/GeneratedLegacySeeder.php'), '<?php /* manual fixture, never execute */');
        $before = $this->records();
        $this->postJson(route('admin.dev.seeder-generator.run'), ['class' => $class])
            ->assertUnprocessable()->assertJsonValidationErrors('class');
        $this->assertSame($before, $this->records());
        Artisan::shouldNotHaveReceived('call');
    }

    /** @return array<string, array{string}> */
    public static function arbitrarySeederClasses(): array
    {
        return [
            'database seeder' => ['DatabaseSeeder'], 'user seeder' => ['UserSeeder'],
            'namespaced real seeder' => ['Database\\Seeders\\UserSeeder'],
            'missing generated file' => ['GeneratedMissingSeeder'], 'manual legacy file' => ['GeneratedLegacySeeder'],
        ];
    }

    public function test_run_rechecks_live_allowlist_after_generation(): void
    {
        $this->environment('local');
        $this->actingAs($this->account());
        $this->postJson(route('admin.dev.seeder-generator.generate'), array_replace($this->payload(), ['seeder_name' => 'revoked_records']))->assertOk();
        $path = database_path('seeders/GeneratedRevokedRecordsSeeder.php');
        $before = File::get($path);
        config(['seeder-generator.allowed_tables' => []]);
        $this->assertFalse(class_exists('Database\\Seeders\\GeneratedRevokedRecordsSeeder', false));
        $this->postJson(route('admin.dev.seeder-generator.run'), ['class' => 'GeneratedRevokedRecordsSeeder'])->assertUnprocessable();
        $this->assertSame($before, File::get($path));
        Artisan::shouldNotHaveReceived('call');
    }

    public function test_run_refuses_artifact_tampering_without_loading_php(): void
    {
        $this->environment('local');
        $this->actingAs($this->account());
        $this->postJson(route('admin.dev.seeder-generator.generate'), array_replace($this->payload(), ['seeder_name' => 'tampered_records']))->assertOk();
        $path = database_path('seeders/GeneratedTamperedRecordsSeeder.php');
        File::append($path, "\n/* altered synthetic artifact */\n");
        $before = File::get($path);
        $this->assertFalse(class_exists('Database\\Seeders\\GeneratedTamperedRecordsSeeder', false));
        $this->postJson(route('admin.dev.seeder-generator.run'), ['class' => 'GeneratedTamperedRecordsSeeder'])
            ->assertUnprocessable()->assertJsonValidationErrors('class');
        $this->assertSame($before, File::get($path));
        Artisan::shouldNotHaveReceived('call');
    }

    public function test_execute_uses_explicit_neutral_factory_and_ignores_hidden_data_options(): void
    {
        $this->environment('local');
        $this->actingAs($this->account());
        $response = $this->postJson(route('admin.dev.seeder-generator.execute'), array_replace($this->payload(), [
            'mode' => 'factory', 'truncate' => true, 'unique_column' => 'obsolete hidden selection',
        ]))->assertOk()->assertJsonPath('data.records', 2)->assertJsonPath('data.success', true);
        $this->assertSame(0, $response->json('data.exit_code'));
        $this->assertSame(3, DB::table('allowed_records')->count());
        $this->assertSame('SEC007_SECRET_SENTINEL', DB::table('allowed_records')->where('unique_key', 'fixture-original')->value('secret'));
        $this->assertSame(2, DB::table('allowed_records')->whereNull('secret')->count());
        $this->assertCount(0, File::allFiles(database_path('seeders')));
        Artisan::shouldNotHaveReceived('call');
    }
}
