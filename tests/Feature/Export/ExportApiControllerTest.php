<?php

namespace Tests\Feature\Export;

use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ExportApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
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
}
