<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_api_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'tests',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'token_type',
            'access_token',
            'user' => ['uuid', 'name', 'email'],
        ]);
        $response->assertJsonPath('token_type', 'Bearer');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_user_cannot_create_api_token_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'tests',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_be_returned_by_token(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.auth.me'));

        $response->assertOk();
        $response->assertJsonPath('data.uuid', $user->uuid);
        $response->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_revoke_current_api_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('tests');

        $response = $this->withToken($token->plainTextToken)
            ->deleteJson(route('api.auth.logout'));

        $response->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_users_endpoint_requires_sanctum_authentication(): void
    {
        $this->getJson(route('api.users.index'))->assertUnauthorized();
    }
}
