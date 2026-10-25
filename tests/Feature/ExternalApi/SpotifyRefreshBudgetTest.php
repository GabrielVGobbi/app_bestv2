<?php

namespace Tests\Feature\ExternalApi;

use App\Models\User;
use App\Modules\ExternalApi\Core\Exceptions\ConfigurationException;
use App\Modules\ExternalApi\Core\Exceptions\ExternalApiException;
use App\Modules\ExternalApi\Models\ExternalApiConnection;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Cache\ArrayLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class SpotifyRefreshBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateProjectFiles();
        $this->freezeSecond();
        Http::preventStrayRequests();

        config([
            'external-apis.fake.enabled' => false,
            'external-apis.cache.enabled' => false,
            'external-apis.logging.enabled' => false,
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => 'ext001-synthetic-client',
            'external-apis.modules.spotify.client_secret' => 'ext001-synthetic-secret',
            'external-apis.modules.spotify.auth_url' => 'https://ext001-spotify.example.test',
            'external-apis.modules.spotify.timeout' => 30,
            'external-apis.modules.spotify.connect_timeout' => 10,
            'external-apis.modules.spotify.retry_attempts' => 9,
            'external-apis.retry.base_delay_ms' => 0,
            'external-apis.retry.max_delay_ms' => 0,
            'external-apis.retry.jitter_ms' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);

        parent::tearDown();
    }

    #[DataProvider('refreshBudgets')]
    public function test_slow_refresh_keeps_exclusion_through_the_configured_budget_and_persists_rotation(int $timeout, int $connectTimeout, int $elapsed): void
    {
        config([
            'external-apis.modules.spotify.timeout' => $timeout,
            'external-apis.modules.spotify.connect_timeout' => $connectTimeout,
        ]);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $connection = $this->expiredConnection($user);
        $requests = [];

        Http::fake(['https://ext001-spotify.example.test/api/token' => function (Request $request) use ($user, $otherUser, $elapsed, &$requests): PromiseInterface {
            $requests[] = $request;
            $this->travel($elapsed)->seconds();

            $competitor = Cache::lock("external-api:oauth-refresh:spotify:{$user->id}", 1, 'competing-owner');
            try {
                $this->assertFalse($competitor->get(), 'A second refresh must not acquire the same user lock while the first HTTP operation is within its budget.');
            } finally {
                $competitor->release();
            }

            $unrelated = Cache::lock("external-api:oauth-refresh:spotify:{$otherUser->id}", 1, 'other-user-owner');
            try {
                $this->assertTrue($unrelated->get(), 'Refreshing one account must not lock a different account.');
            } finally {
                $unrelated->release();
            }

            return Http::response([
                'access_token' => 'ext001-rotated-access',
                'refresh_token' => 'ext001-rotated-refresh',
                'token_type' => 'Bearer',
                'scope' => 'user-read-email user-library-read',
                'expires_in' => 3600,
            ]);
        }]);

        $auth = app(SpotifyManager::class)->auth();
        $this->assertSame('ext001-rotated-access', $auth->getUserAccessToken($user));
        $this->assertSame('ext001-rotated-access', $auth->getUserAccessToken($user));
        $this->assertCount(1, $requests, 'The subsequent call must reuse the persisted token without another OAuth POST.');
        $this->assertSame('POST', $requests[0]->method());
        $this->assertSame(['grant_type' => 'refresh_token', 'refresh_token' => 'ext001-expired-refresh'], $requests[0]->data());

        $connection->refresh();
        $this->assertSame('ext001-rotated-access', $connection->access_token);
        $this->assertSame('ext001-rotated-refresh', $connection->refresh_token);
        $this->assertSame(['user-read-email', 'user-library-read'], $connection->scopes);
        $this->assertSame('Bearer', $connection->token_type);
        $this->assertSame(now()->addHour()->format('Y-m-d H:i:s'), $connection->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(['fixture' => 'ext001'], $connection->metadata);
        $this->assertLockReleased($user);
    }

    /** @return array<string, array{int, int, int}> */
    public static function refreshBudgets(): array
    {
        return [
            'default total timeout boundary' => [30, 10, 30],
            'connection timeout exceeds total timeout' => [60, 90, 90],
        ];
    }

    public function test_waiting_refresh_reloads_the_winners_rotated_token_after_more_than_ten_seconds(): void
    {
        $user = User::factory()->create();
        $connection = $this->expiredConnection($user);
        $auth = app(SpotifyManager::class)->auth();
        $winner = Cache::lock("external-api:oauth-refresh:spotify:{$user->id}", 40, 'first-refresh-owner');
        $this->assertTrue($winner->get());
        $finishedAt = now()->addSeconds(11);
        $winnerFinished = false;

        Http::fake();
        Sleep::fake(syncWithCarbon: true);
        Sleep::whenFakingSleep(function () use ($connection, $winner, $finishedAt, &$winnerFinished): void {
            if ($winnerFinished || now()->lessThan($finishedAt)) {
                return;
            }

            $connection->forceFill([
                'access_token' => 'ext001-winner-access',
                'refresh_token' => 'ext001-winner-refresh',
                'expires_at' => now()->addHour(),
            ])->save();
            $winnerFinished = true;
            $this->assertTrue($winner->release());
        });

        try {
            $this->assertSame('ext001-winner-access', $auth->getUserAccessToken($user));
        } finally {
            $winner->release();
        }

        $this->assertTrue($winnerFinished);
        $this->assertSame('ext001-winner-refresh', $connection->refresh()->refresh_token);
        Http::assertNothingSent();
        $this->assertLockReleased($user);
    }

    #[DataProvider('refreshFailures')]
    public function test_failed_slow_refresh_releases_the_lock_without_replaying_or_overwriting_credentials(string $failure): void
    {
        $user = User::factory()->create();
        $connection = $this->expiredConnection($user);
        $before = $connection->refresh()->getRawOriginal();
        $requests = 0;

        Http::fake(['https://ext001-spotify.example.test/api/token' => function () use ($user, $failure, &$requests): PromiseInterface {
            $requests++;
            $this->travel(11)->seconds();
            $competitor = Cache::lock("external-api:oauth-refresh:spotify:{$user->id}", 1, 'competing-owner');
            try {
                $this->assertFalse($competitor->get());
            } finally {
                $competitor->release();
            }

            if ($failure === 'connection') {
                throw new ConnectionException('EXT001 synthetic response lost.');
            }

            return Http::response(['error' => 'ext001 synthetic failure'], (int) $failure);
        }]);

        $exception = $this->captureFailure(fn () => app(SpotifyManager::class)->auth()->getUserAccessToken($user));
        $this->assertInstanceOf($failure === 'connection' ? ConnectionException::class : ExternalApiException::class, $exception);
        if ($failure !== 'connection') {
            $this->assertSame((int) $failure, $exception->getCode());
        }
        $this->assertSame(1, $requests, 'OAuth POST must remain single attempt even with nine configured retry attempts.');
        $this->assertSame($before, $connection->refresh()->getRawOriginal());
        $this->assertLockReleased($user);
    }

    /** @return array<string, array{string}> */
    public static function refreshFailures(): array
    {
        return [
            'connection response lost' => ['connection'],
            'provider unavailable' => ['503'],
            'invalid refresh token' => ['400'],
        ];
    }

    #[DataProvider('invalidBudgets')]
    public function test_invalid_timeout_budget_is_rejected_before_waiting_or_sending_a_refresh(int|float $timeout, int|float $connectTimeout): void
    {
        $user = User::factory()->create();
        $connection = $this->expiredConnection($user);
        $before = $connection->refresh()->getRawOriginal();
        $auth = app(SpotifyManager::class)->auth();
        $winner = Cache::lock("external-api:oauth-refresh:spotify:{$user->id}", 40, 'existing-owner');
        $this->assertInstanceOf(ArrayLock::class, $winner);
        $this->assertTrue($winner->get());
        config([
            'external-apis.modules.spotify.timeout' => $timeout,
            'external-apis.modules.spotify.connect_timeout' => $connectTimeout,
        ]);

        Http::fake();
        Sleep::fake(syncWithCarbon: true);
        Sleep::whenFakingSleep(fn () => $this->fail('An invalid budget must be rejected before waiting on a refresh lock.'));

        try {
            $exception = $this->captureFailure(fn () => $auth->getUserAccessToken($user));
            $this->assertInstanceOf(ConfigurationException::class, $exception);
            $this->assertTrue($winner->isOwnedByCurrentProcess());
        } finally {
            $winner->release();
        }

        Http::assertNothingSent();
        $this->assertSame($before, $connection->refresh()->getRawOriginal());
    }

    /** @return array<string, array{int|float, int|float}> */
    public static function invalidBudgets(): array
    {
        return [
            'zero total timeout' => [0, 10],
            'negative total timeout' => [-1, 10],
            'zero connection timeout' => [30, 0],
            'negative connection timeout' => [30, -1],
            'infinite total timeout' => [INF, 10],
            'nonfinite connection timeout' => [30, NAN],
        ];
    }

    private function expiredConnection(User $user): ExternalApiConnection
    {
        return ExternalApiConnection::create([
            'user_id' => $user->id,
            'provider' => 'spotify',
            'provider_user_id' => 'ext001-provider-user',
            'access_token' => 'ext001-expired-access',
            'refresh_token' => 'ext001-expired-refresh',
            'token_type' => 'Bearer',
            'scopes' => ['user-read-email'],
            'expires_at' => now()->subMinute(),
            'metadata' => ['fixture' => 'ext001'],
        ]);
    }

    private function assertLockReleased(User $user): void
    {
        $next = Cache::lock("external-api:oauth-refresh:spotify:{$user->id}", 1, 'subsequent-owner');
        try {
            $this->assertTrue($next->get(), 'Refresh must release the lock when its operation finishes.');
        } finally {
            $next->release();
        }
    }

    private function captureFailure(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            return $exception;
        }

        $this->fail('The synthetic refresh failure unexpectedly succeeded.');
    }
}
