<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Permissions\Models\Permission;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\TestEnvironment;

class RequiredPasswordAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $this->isolateProjectFiles();
        config(['sanctum.stateful' => ['localhost']]);
    }

    #[DataProvider('privateApiRequests')]
    public function test_required_password_blocks_private_api_data_with_a_json_password_error(string $channel, string $endpoint): void
    {
        $user = $this->userWithPrivateData(true);
        $before = $user->refresh()->getAttributes();
        $this->authenticateApi($user, $channel);

        $this->getJson(route($endpoint))
            ->assertForbidden()->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Location')->assertJsonValidationErrors('password')
            ->assertJsonMissingPath('data')->assertDontSee('SEC013 private notification')
            ->assertDontSee('SEC013 private client')->assertDontSee($user->getAttribute('email'));

        $this->assertSame($before, $user->refresh()->getAttributes());
    }

    #[DataProvider('privateApiRequests')]
    public function test_normal_accounts_keep_access_to_private_api_data(string $channel, string $endpoint): void
    {
        $user = $this->userWithPrivateData(false);
        $this->authenticateApi($user, $channel);
        $response = $this->getJson(route($endpoint))->assertOk();

        match ($endpoint) {
            'api.auth.me' => $response->assertJsonPath('data.uuid', $user->getAttribute('uuid')),
            'api.notifications.index' => $response->assertJsonPath('data.0.title', 'SEC013 private notification'),
            'api.clients.index' => $response->assertJsonPath('data.0.name', 'SEC013 private client'),
            default => throw new \LogicException('Unknown synthetic private API endpoint.'),
        };
    }

    #[DataProvider('privateApiRequests')]
    public function test_api_password_restriction_returns_json_even_without_a_json_accept_header(string $channel, string $endpoint): void
    {
        $user = $this->userWithPrivateData(true);
        $this->authenticateApi($user, $channel);

        $this->get(route($endpoint))->assertForbidden()
            ->assertHeader('Content-Type', 'application/json')->assertHeaderMissing('Location')
            ->assertJsonValidationErrors('password')->assertJsonMissingPath('data');
    }

    /** @return array<string, array{string, string}> */
    public static function privateApiRequests(): array
    {
        return [
            'PAT me' => ['pat', 'api.auth.me'],
            'PAT notifications' => ['pat', 'api.notifications.index'],
            'PAT Clients module route' => ['pat', 'api.clients.index'],
            'session me' => ['session', 'api.auth.me'],
            'session notifications' => ['session', 'api.notifications.index'],
            'session Clients module route' => ['session', 'api.clients.index'],
        ];
    }

    #[DataProvider('privateApiEndpoints')]
    public function test_guests_keep_the_unauthenticated_api_response(string $endpoint): void
    {
        $this->getJson(route($endpoint))->assertUnauthorized()
            ->assertHeaderMissing('Location')->assertJsonMissingPath('data');
    }

    /** @return array<string, array{string}> */
    public static function privateApiEndpoints(): array
    {
        return [
            'me' => ['api.auth.me'],
            'notifications' => ['api.notifications.index'],
            'Clients module route' => ['api.clients.index'],
        ];
    }

    #[DataProvider('authenticationChannels')]
    public function test_required_password_prevents_notification_mutation(string $channel): void
    {
        $user = $this->userWithPrivateData(true);
        $notification = AppNotification::query()->where('recipient_id', $user->getKey())->sole();
        $before = $notification->refresh()->getAttributes();
        $this->authenticateApi($user, $channel);

        $this->patchJson(route('api.notifications.mark-read', $notification))
            ->assertForbidden()->assertJsonValidationErrors('password')->assertJsonMissingPath('data');

        $this->assertSame($before, $notification->refresh()->getAttributes());
    }

    /** @return array<string, array{string}> */
    public static function authenticationChannels(): array
    {
        return ['legacy PAT' => ['pat'], 'stateful session' => ['session']];
    }

    public function test_web_access_keeps_the_required_password_redirect_and_completion_page(): void
    {
        $user = $this->userWithPrivateData(true);
        $this->actingAs($user, 'web')->get(route('app.dashboard'))
            ->assertRedirect(route('password.force.edit'));
        $this->get(route('password.force.edit'))->assertOk();
    }

    #[DataProvider('tokenLogoutEndpoints')]
    public function test_required_password_allows_token_logout_only_for_the_current_owner(string $endpoint, bool $all): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $other = User::factory()->create();
        $current = $user->createToken('sec013-current');
        $sibling = $user->createToken('sec013-sibling');
        $other->createToken('sec013-other');
        $otherBefore = $this->tokenSnapshot($other);
        $userBefore = $user->refresh()->getAttributes();

        $this->withToken($current->plainTextToken)->deleteJson(route($endpoint))->assertNoContent();

        $this->assertFalse($user->tokens()->whereKey($current->accessToken->getKey())->exists());
        $this->assertSame(! $all, $user->tokens()->whereKey($sibling->accessToken->getKey())->exists());
        $this->assertSame($otherBefore, $this->tokenSnapshot($other));
        $this->assertSame($userBefore, $user->refresh()->getAttributes());
    }

    #[DataProvider('tokenLogoutEndpoints')]
    public function test_required_password_allows_stateful_token_logout_without_revoking_other_users(string $endpoint, bool $all): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $other = User::factory()->create();
        $user->createToken('sec013-current');
        $other->createToken('sec013-other');
        $otherBefore = $this->tokenSnapshot($other);
        $this->authenticateApi($user, 'session');

        $this->deleteJson(route($endpoint))->assertNoContent();

        $this->assertSame($all ? 0 : 1, $user->tokens()->count());
        $this->assertSame($otherBefore, $this->tokenSnapshot($other));
        $this->assertAuthenticatedAs($user, 'web');
    }

    /** @return array<string, array{string, bool}> */
    public static function tokenLogoutEndpoints(): array
    {
        return ['current token' => ['api.auth.logout', false], 'all own tokens' => ['api.auth.logout-all', true]];
    }

    public function test_required_password_allows_web_logout(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user, 'web')->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest('web');
    }

    #[DataProvider('passwordCompletionEndpoints')]
    public function test_password_completion_revokes_own_tokens_and_preserves_the_session(string $endpoint): void
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => true]);
        $other = User::factory()->create();
        $first = $user->createToken('sec013-legacy-one');
        $second = $user->createToken('sec013-legacy-two');
        $other->createToken('sec013-other');
        $otherBefore = $other->refresh()->getAttributes();
        $otherTokensBefore = $this->tokenSnapshot($other);
        $this->startWebSession($user);

        $this->from(route('password.force.edit'))->put(route($endpoint), $this->validPasswordPayload())
            ->assertRedirect($endpoint === 'password.force.update' ? route('app.dashboard') : route('password.force.edit'))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('Sec013-New-Password!', $user->getAuthPassword()));
        $this->assertFalse($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame($otherBefore, $other->refresh()->getAttributes());
        $this->assertSame($otherTokensBefore, $this->tokenSnapshot($other));
        $this->assertSame($this->sessionPasswordHash($user), session('password_hash_web'));

        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->getJson(route('api.auth.me'))
            ->assertOk()->assertJsonPath('data.uuid', $user->getAttribute('uuid'));
        foreach ([$first->plainTextToken, $second->plainTextToken] as $token) {
            $this->useOnlyToken($token);
            $this->getJson(route('api.auth.me'))->assertUnauthorized()->assertJsonMissingPath('data');
        }
    }

    #[DataProvider('invalidPasswordUpdates')]
    public function test_invalid_password_completion_does_not_mutate_password_flag_or_tokens(string $endpoint, string $failure): void
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => true]);
        $user->createToken('sec013-legacy');
        $before = $user->refresh()->getAttributes();
        $tokensBefore = $this->tokenSnapshot($user);
        $payload = $this->validPasswordPayload();
        $payload[$failure === 'current_password' ? 'current_password' : 'password_confirmation'] = 'incorrect';

        $this->actingAs($user, 'web')->putJson(route($endpoint), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($failure);

        $this->assertSame($before, $user->refresh()->getAttributes());
        $this->assertSame($tokensBefore, $this->tokenSnapshot($user));
    }

    /** @return array<string, array{string, string}> */
    public static function invalidPasswordUpdates(): array
    {
        return [
            'force wrong current password' => ['password.force.update', 'current_password'],
            'force mismatched confirmation' => ['password.force.update', 'password'],
            'security wrong current password' => ['user-password.update', 'current_password'],
            'security mismatched confirmation' => ['user-password.update', 'password'],
        ];
    }

    #[DataProvider('passwordCompletionEndpoints')]
    public function test_token_delete_failure_rolls_back_password_completion_and_retry_recovers(string $endpoint): void
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => true]);
        $other = User::factory()->create();
        $user->createToken('sec013-legacy');
        $other->createToken('sec013-other');
        $this->startWebSession($user);
        $userBefore = $user->refresh()->getAttributes();
        $tokensBefore = $this->tokenSnapshot($user);
        $otherBefore = $other->refresh()->getAttributes();
        $otherTokensBefore = $this->tokenSnapshot($other);
        $sessionHashBefore = session('password_hash_web');
        Exceptions::fake();
        DB::unprepared("CREATE TRIGGER sec013_fail_required_password_token_delete BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'SEC013_SYNTHETIC_TOKEN_DELETE_FAILURE'); END");

        try {
            $this->putJson(route($endpoint), $this->validPasswordPayload())->assertStatus(500);
            Exceptions::assertReported(fn (QueryException $exception): bool => str_contains($exception->getMessage(), 'SEC013_SYNTHETIC_TOKEN_DELETE_FAILURE'));
            $this->assertSame($userBefore, $user->refresh()->getAttributes());
            $this->assertSame($tokensBefore, $this->tokenSnapshot($user));
            $this->assertSame($otherBefore, $other->refresh()->getAttributes());
            $this->assertSame($otherTokensBefore, $this->tokenSnapshot($other));
            $this->assertSame($sessionHashBefore, session('password_hash_web'));
        } finally {
            DB::unprepared('DROP TRIGGER sec013_fail_required_password_token_delete');
        }

        $this->app['auth']->forgetGuards();
        $this->putJson(route($endpoint), $this->validPasswordPayload())->assertRedirect();
        $this->assertTrue(Hash::check('Sec013-New-Password!', $user->refresh()->getAuthPassword()));
        $this->assertFalse($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame($otherTokensBefore, $this->tokenSnapshot($other));
    }

    #[DataProvider('passwordCompletionEndpoints')]
    public function test_normal_password_change_preserves_existing_tokens(string $endpoint): void
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => false]);
        $user->createToken('sec013-normal');
        $tokensBefore = $this->tokenSnapshot($user);

        $this->actingAs($user, 'web')->putJson(route($endpoint), $this->validPasswordPayload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Sec013-New-Password!', $user->refresh()->getAuthPassword()));
        $this->assertFalse($user->must_change_password);
        $this->assertSame($tokensBefore, $this->tokenSnapshot($user));
    }

    #[DataProvider('passwordCompletionEndpoints')]
    public function test_password_completion_uses_the_fresh_required_flag_from_the_database(string $endpoint): void
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => false]);
        $user->createToken('sec013-required-after-session-start');
        $this->actingAs($user, 'web');
        DB::table('users')->where('id', $user->getKey())->update(['must_change_password' => true]);

        $this->putJson(route($endpoint), $this->validPasswordPayload())->assertRedirect();

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('Sec013-New-Password!', $user->getAuthPassword()));
        $this->assertSame(0, $user->tokens()->count());
    }

    #[DataProvider('passwordCompletionEndpoints')]
    public function test_stale_required_flag_does_not_revoke_tokens_after_the_requirement_was_already_cleared(string $endpoint): void
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => true]);
        $user->createToken('sec013-normal-after-requirement-cleared');
        $tokensBefore = $this->tokenSnapshot($user);
        $this->actingAs($user, 'web');
        DB::table('users')->where('id', $user->getKey())->update(['must_change_password' => false]);

        $this->putJson(route($endpoint), $this->validPasswordPayload())->assertRedirect();

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('Sec013-New-Password!', $user->getAuthPassword()));
        $this->assertSame($tokensBefore, $this->tokenSnapshot($user));
    }

    /** @return array<string, array{string}> */
    public static function passwordCompletionEndpoints(): array
    {
        return ['force password form' => ['password.force.update'], 'security settings form' => ['user-password.update']];
    }

    private function userWithPrivateData(bool $required): User
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => $required]);
        $permission = Permission::query()->firstOrCreate(['slug' => 'view-clients'], ['name' => 'view clients']);
        $user->permissions()->attach($permission);
        Client::factory()->create(['name' => 'SEC013 private client']);
        AppNotification::query()->create([
            'recipient_id' => $user->getKey(),
            'type' => NotificationType::SYSTEM,
            'category' => NotificationCategory::GENERAL,
            'title' => 'SEC013 private notification',
            'body' => 'SEC013 private notification body',
        ]);

        return $user;
    }

    private function authenticateApi(User $user, string $channel): void
    {
        if ($channel === 'pat') {
            $token = $user->createToken('sec013-legacy', ['view-clients', 'view-notifications']);
            $this->withToken($token->plainTextToken);

            return;
        }

        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost')
            ->withSession(['password_hash_web' => $this->sessionPasswordHash($user)]);
    }

    private function startWebSession(User $user): void
    {
        $this->postJson(route('login.store'), ['email' => $user->getAttribute('email'), 'password' => 'password'])
            ->assertOk()->assertExactJson(['two_factor' => false]);
        $this->withSession(['password_hash_web' => $this->sessionPasswordHash($user)]);
    }

    private function sessionPasswordHash(User $user): string
    {
        $guard = $this->app['auth']->guard('web');
        $this->assertInstanceOf(SessionGuard::class, $guard);

        return $guard->hashPasswordForCookie($user->getAuthPassword());
    }

    private function useOnlyToken(string $token): void
    {
        $this->flushSession();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
    }

    /** @return array{current_password: string, password: string, password_confirmation: string} */
    private function validPasswordPayload(): array
    {
        return [
            'current_password' => 'password',
            'password' => 'Sec013-New-Password!',
            'password_confirmation' => 'Sec013-New-Password!',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function tokenSnapshot(User $user): array
    {
        return $user->tokens()->orderBy('id')->get()->map(fn (PersonalAccessToken $token): array => $token->getAttributes())->all();
    }
}
