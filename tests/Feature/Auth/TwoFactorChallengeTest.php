<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Supports\AtomicTwoFactorAuthenticationProvider;
use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;
use Tests\TestEnvironment;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/two-factor-challenge'),
            );
    }

    public function test_web_totp_challenge_rejects_invalid_code_accepts_real_code_and_refuses_replay(): void
    {
        $user = $this->confirmedMfaUser();
        $secret = Fortify::currentEncrypter()->decrypt($user->getAttribute('two_factor_secret'));
        $code = (new Google2FA)->getCurrentOtp($secret);
        Cache::forget('fortify.2fa_codes.'.md5($code));
        $this->startWebMfaChallenge($user);

        $this->postJson(route('two-factor.login.store'), ['code' => 'invalid-code'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertGuest();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson(route('two-factor.login.store'), ['code' => $code])->assertNoContent();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->post(route('logout'));
        $this->assertGuest();
        $this->startWebMfaChallenge($user);
        $this->postJson(route('two-factor.login.store'), ['code' => $code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertGuest();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson(route('two-factor.login.store'), ['recovery_code' => 'abcde12345-fghij67890'])->assertNoContent();
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_recovery_challenge_consumes_one_code_and_preserves_other_codes_for_recovery(): void
    {
        $user = $this->confirmedMfaUser();
        $before = $user->recoveryCodes();
        $this->startWebMfaChallenge($user);
        $this->postJson(route('two-factor.login.store'), ['recovery_code' => 'not-a-recovery-code'])
            ->assertUnprocessable()->assertJsonValidationErrors('recovery_code');
        $this->assertGuest();
        $this->assertSame($before, $user->refresh()->recoveryCodes());
        $this->postJson(route('two-factor.login.store'), ['recovery_code' => $before[0]])->assertNoContent();
        $this->assertAuthenticatedAs($user);
        $remaining = $user->refresh()->recoveryCodes();
        $this->assertCount(count($before), $remaining);
        $this->assertNotContains($before[0], $remaining);
        $this->assertContains($before[1], $remaining);
        $this->post(route('logout'));
        $this->assertGuest();
        $this->startWebMfaChallenge($user);
        $this->postJson(route('two-factor.login.store'), ['recovery_code' => $before[0]])
            ->assertUnprocessable()->assertJsonValidationErrors('recovery_code');
        $this->assertGuest();
        $this->assertSame($remaining, $user->refresh()->recoveryCodes());
        $this->postJson(route('two-factor.login.store'), ['recovery_code' => $before[1]])->assertNoContent();
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($before[1], $user->refresh()->recoveryCodes());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_recovery_consumption_rechecks_a_stale_user_and_cannot_restore_used_codes(): void
    {
        $user = $this->confirmedMfaUser();
        $first = User::query()->findOrFail($user->getKey());
        $stale = User::query()->findOrFail($user->getKey());
        $code = $first->recoveryCodes()[0];
        $first->replaceRecoveryCode($code);
        $after = $user->refresh()->recoveryCodes();
        $this->assertNotContains($code, $after);

        try {
            $stale->replaceRecoveryCode($code);
            $this->fail('A stale model must not consume the same recovery code a second time.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recovery_code', $exception->errors());
        }
        $this->assertSame($after, $user->refresh()->recoveryCodes());
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_shared_totp_provider_refuses_a_held_replay_lock_and_recovers_after_release(): void
    {
        $user = $this->confirmedMfaUser();
        $secret = Fortify::currentEncrypter()->decrypt($user->getAttribute('two_factor_secret'));
        $code = (new Google2FA)->getCurrentOtp($secret);
        $key = 'fortify.2fa_codes.'.md5($code);
        Cache::forget($key);
        $lock = Cache::lock('fortify.2fa_code_locks.'.md5($code), 10);
        $this->assertTrue($lock->get());

        try {
            $this->assertFalse(app(TwoFactorAuthenticationProvider::class)->verify($secret, $code), 'A busy shared replay guard must refuse verification rather than bypass serialization.');
            $this->assertNull(Cache::get($key));
        } finally {
            $lock->release();
        }
        $this->assertTrue(app(TwoFactorAuthenticationProvider::class)->verify($secret, $code));
        $this->assertFalse(app(TwoFactorAuthenticationProvider::class)->verify($secret, $code));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_totp_provider_refuses_a_cache_write_that_does_not_retain_replay_state(): void
    {
        $cache = new class(new ArrayStore) extends Repository
        {
            public function put(mixed $key, mixed $value, mixed $ttl = null): bool
            {
                return false;
            }
        };
        $engine = new Google2FA;
        $secret = $engine->generateSecretKey();
        $code = $engine->getCurrentOtp($secret);
        $provider = new AtomicTwoFactorAuthenticationProvider($engine, $cache);

        $this->assertFalse($provider->verify($secret, $code), 'An ordinary failed replay-cache write must never authenticate the factor.');
        $this->assertNull($cache->get('fortify.2fa_codes.'.md5($code)));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_replay_lock_remains_owned_during_a_delay_while_the_same_totp_is_still_valid(): void
    {
        $previousWindow = config('fortify-options.two-factor-authentication.window');
        $this->travelTo(Carbon::create(2026, 1, 1, 12, 0, 0, 'UTC'));
        config(['fortify-options.two-factor-authentication.window' => 1]);
        $cache = new class(new ArrayStore) extends Repository
        {
            public ?Closure $beforePut = null;

            public function put(mixed $key, mixed $value, mixed $ttl = null): bool
            {
                if ($this->beforePut !== null && is_int($value) && $value > 0) {
                    $callback = $this->beforePut;
                    $this->beforePut = null;
                    $callback();
                }

                return parent::put($key, $value, $ttl);
            }
        };
        $engine = new class extends Google2FA
        {
            public function getTimestamp(): int
            {
                return intdiv(Carbon::now()->getTimestamp(), $this->getKeyRegeneration());
            }
        };
        $secret = $engine->generateSecretKey();
        $code = $engine->getCurrentOtp($secret);
        $first = new AtomicTwoFactorAuthenticationProvider($engine, $cache);
        $second = new AtomicTwoFactorAuthenticationProvider($engine, $cache);
        $concurrent = [];
        $stillValid = null;
        $cache->beforePut = function () use ($second, $engine, $secret, $code, &$concurrent, &$stillValid): void {
            $this->travel(11)->seconds();
            $stillValid = $engine->verifyKey($secret, $code);
            $concurrent[] = $second->verify($secret, $code);
        };

        try {
            $this->assertTrue($first->verify($secret, $code));
            $this->assertTrue($stillValid, 'The competing attempt must still carry a valid real TOTP.');
            $this->assertSame([false], $concurrent, 'A valid delayed factor must not outlive its replay lock.');
            $this->assertFalse($second->verify($secret, $code));
            $this->assertDatabaseCount('personal_access_tokens', 0);
            $this->assertGuest();
        } finally {
            $this->travelBack();
            config(['fortify-options.two-factor-authentication.window' => $previousWindow]);
        }
    }

    public function test_replay_cache_accepts_canonical_numeric_string_timestamps_returned_by_a_store(): void
    {
        $store = new class extends ArrayStore
        {
            public function get(mixed $key): mixed
            {
                $value = parent::get($key);

                return is_int($value) ? (string) $value : $value;
            }
        };
        $cache = new Repository($store);
        $engine = new Google2FA;
        $secret = $engine->generateSecretKey();
        $code = $engine->getCurrentOtp($secret);
        $provider = new AtomicTwoFactorAuthenticationProvider($engine, $cache);

        $this->assertTrue($provider->verify($secret, $code));
        $this->assertIsString($cache->get('fortify.2fa_codes.'.md5($code)));
        $this->assertFalse($provider->verify($secret, $code));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_future_tick_totp_is_consumed_once_from_an_empty_replay_cache(): void
    {
        $previousWindow = config('fortify-options.two-factor-authentication.window');
        $this->travelTo(Carbon::create(2026, 1, 1, 12, 0, 0, 'UTC'));
        config(['fortify-options.two-factor-authentication.window' => 1]);

        try {
            $engine = $this->clockDrivenMfaEngine();
            $engine->setWindow(1);
            $secret = 'JBSWY3DPEHPK3PXP';
            $code = $engine->oathTotp($secret, $engine->getTimestamp() + 1);
            $cache = new Repository(new ArrayStore);
            $provider = new AtomicTwoFactorAuthenticationProvider($engine, $cache);
            $this->assertNotSame($engine->getCurrentOtp($secret), $code);
            $this->assertTrue($engine->verifyKey($secret, $code));
            $this->assertNull($cache->get('fortify.2fa_codes.'.md5($code)));
            $this->assertTrue($provider->verify($secret, $code));
            $this->assertFalse($provider->verify($secret, $code), 'A tolerated future tick must not be accepted twice on its first cache entry.');
            $this->assertDatabaseCount('personal_access_tokens', 0);
            $this->assertGuest();
        } finally {
            $this->travelBack();
            config(['fortify-options.two-factor-authentication.window' => $previousWindow]);
        }
    }

    public function test_totp_provider_refuses_when_only_the_final_replay_retention_write_fails(): void
    {
        $cache = new class(new ArrayStore) extends Repository
        {
            public int $writes = 0;

            public function put(mixed $key, mixed $value, mixed $ttl = null): bool
            {
                $this->writes++;
                if ($this->writes === 3) {
                    return false;
                }

                return parent::put($key, $value, $ttl);
            }
        };
        $engine = new Google2FA;
        $secret = $engine->generateSecretKey();
        $code = $engine->getCurrentOtp($secret);
        $provider = new AtomicTwoFactorAuthenticationProvider($engine, $cache);

        $this->assertFalse($provider->verify($secret, $code), 'A failed final TTL renewal must refuse authentication even after the parent write succeeds.');
        $this->assertSame(3, $cache->writes);
        $this->assertIsInt($cache->get('fortify.2fa_codes.'.md5($code)));
        $this->assertFalse($provider->verify($secret, $code));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_future_tick_replay_stays_refused_after_the_vendor_sixty_second_data_ttl(): void
    {
        $previousWindow = config('fortify-options.two-factor-authentication.window');
        $this->travelTo(Carbon::create(2026, 1, 1, 12, 0, 0, 'UTC'));
        config(['fortify-options.two-factor-authentication.window' => 1]);

        try {
            $engine = $this->clockDrivenMfaEngine();
            $engine->setWindow(1);
            $secret = 'JBSWY3DPEHPK3PXP';
            $code = $engine->oathTotp($secret, $engine->getTimestamp() + 1);
            $cache = new Repository(new ArrayStore);
            $provider = new AtomicTwoFactorAuthenticationProvider($engine, $cache);
            $this->assertTrue($provider->verify($secret, $code));
            $this->travel(61)->seconds();
            $this->assertTrue($engine->verifyKey($secret, $code), 'The previously consumed future tick is still within the real configured TOTP window.');
            $this->assertFalse($provider->verify($secret, $code), 'Replay protection must cover the whole accepted code lifetime, including time beyond the vendor data TTL.');
            $this->assertDatabaseCount('personal_access_tokens', 0);
            $this->assertGuest();
        } finally {
            $this->travelBack();
            config(['fortify-options.two-factor-authentication.window' => $previousWindow]);
        }
    }

    #[DataProvider('invalidRecoveryPayloads')]
    public function test_encrypted_non_array_recovery_state_is_refused_without_mutating_the_user(mixed $payload): void
    {
        $user = $this->confirmedMfaUser();
        $user->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($payload, JSON_THROW_ON_ERROR)),
        ])->save();
        $before = $user->refresh()->getAttributes();

        try {
            $user->replaceRecoveryCode('abcde12345-fghij67890');
            $this->fail('An encrypted JSON scalar or null must be refused as invalid recovery state.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recovery_code', $exception->errors());
        }
        $this->assertSame($before, $user->refresh()->getAttributes());
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    /** @return array<string, array{mixed}> */
    public static function invalidRecoveryPayloads(): array
    {
        return ['encrypted JSON null' => [null], 'encrypted JSON scalar' => ['invalid recovery state']];
    }

    private function clockDrivenMfaEngine(): Google2FA
    {
        return new class extends Google2FA
        {
            public function getTimestamp(): int
            {
                return intdiv(Carbon::now()->getTimestamp(), $this->getKeyRegeneration());
            }
        };
    }

    private function confirmedMfaUser(): User
    {
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        $user = User::factory()->create(['password' => 'password']);
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt((new Google2FA)->generateSecretKey()),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['abcde12345-fghij67890', 'klmno12345-pqrst67890'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function startWebMfaChallenge(User $user): void
    {
        $this->postJson(route('login.store'), ['email' => $user->getAttribute('email'), 'password' => 'password'])
            ->assertOk()->assertExactJson(['two_factor' => true])->assertSessionHas('login.id', $user->getKey());
        $this->assertGuest();
    }
}
