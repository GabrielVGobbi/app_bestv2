<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_client_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk();
    }

    public function test_api_returns_client_records(): void
    {
        $user = User::factory()->create();
        Client::factory()->count(2)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
