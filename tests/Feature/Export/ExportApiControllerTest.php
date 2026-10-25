<?php

namespace Tests\Feature\Export;

use App\Concerns\Exportable;
use App\Concerns\Searchable;
use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Supports\ApiTokenAbilities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExportApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateProjectFiles();
        config(['permissions.super_role' => 'developer']);
        $this->user = $this->actor('developer');
        $this->grant($this->user, 'view-users', 'view-clients');
        Bus::fake();
        Storage::fake('local');
        Notification::fake();
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_store_creates_export_and_dispatches_job(): void
    {
        Bus::fake();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/exports', [
                'model_class' => User::class,
                'format' => 'csv',
                'columns' => ['name', 'email'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['uuid', 'status', 'format']]);
        $response->assertJsonPath('data.status', 'pending');

        Bus::assertDispatched(ProcessExport::class);

        $this->assertDatabaseHas('exports', [
            'user_id' => $this->user->id,
            'model_class' => User::class,
            'format' => 'csv',
            'status' => 'pending',
        ]);
    }

    public function test_store_returns_422_for_disallowed_model(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/exports', [
                'model_class' => Export::class,
                'format' => 'csv',
            ]);

        $response->assertStatus(422);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/exports', [
            'model_class' => User::class,
            'format' => 'csv',
        ]);

        $response->assertStatus(401);
    }

    public function test_show_returns_export_for_owner(): void
    {
        $export = Export::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/exports/{$export->uuid}");

        $response->assertOk();
        $response->assertJsonPath('data.uuid', $export->uuid);
    }

    public function test_show_returns_403_for_non_owner(): void
    {
        $otherUser = User::factory()->create();
        $export = Export::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/exports/{$export->uuid}");

        $response->assertStatus(403);
    }

    public function test_index_returns_only_current_user_exports(): void
    {
        $otherUser = User::factory()->create();

        Export::factory()->create(['user_id' => $this->user->id]);
        Export::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/exports');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_excludes_expired_exports(): void
    {
        Export::factory()->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/exports');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_columns_returns_exportable_columns_for_user_model(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/exports/columns?model_class='.urlencode(User::class));

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['key', 'label']]]);

        $keys = collect($response->json('data'))->pluck('key')->toArray();
        $this->assertContains('name', $keys);
        $this->assertContains('email', $keys);
    }

    public function test_columns_returns_422_for_disallowed_model(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/exports/columns?model_class='.urlencode(Export::class));

        $response->assertStatus(422);
    }

    #[DataProvider('exportModels')]
    /** @return array<string, array{class-string<Model>}> */
    public static function exportModels(): array
    {
        return ['users' => [User::class], 'clients' => [Client::class]];
    }

    #[DataProvider('invalidRequests')]
    public function test_invalid_export_requests_create_no_record_or_job(array $changes, string $field): void
    {
        $this->actingAs($this->user, 'web')->postJson(route('api.exports.store'), array_replace($this->payload(), $changes))
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('exports', 0);
        Bus::assertNotDispatched(ProcessExport::class);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Notification::assertNothingSent();
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidRequests(): array
    {
        return [
            'disallowed model' => [['model_class' => Export::class], 'model_class'],
            'nonexistent model' => [['model_class' => 'SEC016\\MissingExportModel'], 'model_class'],
            'malformed model' => [['model_class' => ['not-a-class']], 'model_class'],
            'private column' => [['columns' => ['password']], 'columns.0'],
            'mixed public and private' => [['columns' => ['name', 'password']], 'columns.1'],
            'associative columns' => [['columns' => ['field' => 'name']], 'columns'],
            'duplicate columns' => [['columns' => ['name', 'name']], 'columns.0'],
            'nonstring column' => [['columns' => [7]], 'columns.0'],
        ];
    }

    #[DataProvider('defaultColumns')]
    public function test_omitted_null_and_empty_columns_keep_the_authorized_exportable_map(string $shape): void
    {
        $payload = $this->payload();
        if ($shape === 'omitted') {
            unset($payload['columns']);
        } else {
            $payload['columns'] = $shape === 'null' ? null : [];
        }

        $this->actingAs($this->user, 'web')->postJson(route('api.exports.store'), $payload)->assertCreated();
        $export = Export::query()->sole();
        $this->assertSame([], $export->columns);
        $this->assertSame(User::getExportableColumns(), User::resolveExportColumns($export->columns));
        Bus::assertDispatched(ProcessExport::class, 1);
    }

    /** @return array<string, array{string}> */
    public static function defaultColumns(): array
    {
        return ['omitted' => ['omitted'], 'null' => ['null'], 'empty' => ['empty']];
    }

    /** @param list<string> $abilities */
    /** @return array<string, array{list<string>}> */
    public static function personalAccessTokenAbilities(): array
    {
        return ['empty' => [[]], 'wildcard' => [['*']], 'known scopes' => [ApiTokenAbilities::ALL]];
    }

    /** @return array<string, mixed> */
    private function payload(string $model = User::class): array
    {
        return ['model_class' => $model, 'format' => 'csv', 'columns' => ['name', 'email']];
    }

    private function actor(?string $role, ?string $name = null): User
    {
        $actor = User::factory()->create($name === null ? [] : ['name' => $name]);
        if ($role !== null) {
            $actor->roles()->syncWithoutDetaching([Role::firstOrCreate(['slug' => $role], ['name' => $role])->id]);
        }

        return $actor;
    }

    private function grant(User $actor, string ...$slugs): void
    {
        foreach ($slugs as $slug) {
            $actor->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id]);
        }
    }

    /** @return list<list<string|null>> */
    private function csvRows(Export $export): array
    {
        $stream = fopen(Storage::disk('local')->path($export->file_path), 'rb');
        $this->assertIsResource($stream);
        $rows = [];
        try {
            $this->assertSame("\xEF\xBB\xBF", fread($stream, 3));
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $rows[] = $row;
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }
}

class UnscopedApiExportUser extends Model
{
    use Exportable, Searchable;

    protected $table = 'users';

    protected array $exportable = ['name' => 'Nome', 'email' => 'Email'];
}
