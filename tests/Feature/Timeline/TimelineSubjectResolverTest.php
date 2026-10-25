<?php

declare(strict_types=1);

namespace Tests\Feature\Timeline;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Timeline\Support\TimelineSubjectResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineSubjectResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_resolves_client_by_numeric_id(): void
    {
        $client = Client::factory()->create();

        $resolved = app(TimelineSubjectResolver::class)->resolve('client', $client->id);

        $this->assertTrue($client->is($resolved));
    }

    public function test_resolver_resolves_client_by_uuid(): void
    {
        $client = Client::factory()->create([
            'uuid' => '6466cc5c-f28c-4491-9c6c-80c27efe6114',
        ]);

        $resolved = app(TimelineSubjectResolver::class)->resolve('client', $client->uuid);

        $this->assertTrue($client->is($resolved));
    }

    public function test_resolver_resolves_numeric_prefixed_uuid_before_primary_key(): void
    {
        Client::factory()->create(['id' => 7]);
        $client = Client::factory()->create([
            'uuid' => '7ad4e60c-1698-4b7d-a3e9-fa0acb1cde5d',
        ]);

        $resolved = app(TimelineSubjectResolver::class)->resolve('client', $client->uuid);

        $this->assertTrue($client->is($resolved));
    }

    public function test_resolver_rejects_unknown_alias_without_instantiating_arbitrary_class(): void
    {
        $this->getJson('/api/v1/timeline/App%5CModels%5CUser/1')
            ->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/timeline/App%5CModels%5CUser/1')
            ->assertNotFound();
    }

    public function test_timeline_endpoint_returns_resolved_client_subject_by_id(): void
    {
        $user = User::factory()->create();
        $this->grantClientRead($user);
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timeline/client/{$client->id}?types[]=comment")
            ->assertOk()
            ->assertJsonPath('subject.type', 'client')
            ->assertJsonPath('subject.id', $client->id)
            ->assertJsonPath('subject.uuid', $client->uuid)
            ->assertJsonPath('data', []);
    }

    public function test_timeline_endpoint_returns_resolved_client_subject_by_uuid(): void
    {
        $user = User::factory()->create();
        $this->grantClientRead($user);
        $client = Client::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timeline/client/{$client->uuid}")
            ->assertOk()
            ->assertJsonPath('subject.id', $client->id)
            ->assertJsonPath('subject.uuid', $client->uuid);
    }

    public function test_timeline_endpoint_requires_authentication(): void
    {
        $client = Client::factory()->create();

        $this->getJson("/api/v1/timeline/client/{$client->id}")
            ->assertUnauthorized();
    }

    private function grantClientRead(User $user): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'view-clients'],
            ['name' => 'View Clients', 'group' => 'clients'],
        );
        $user->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }
}
