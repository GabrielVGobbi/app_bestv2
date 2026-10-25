<?php

namespace Tests\Feature\ExternalApi;

use App\Models\User;
use App\Modules\ExternalApi\Core\DTOs\BaseResponse;
use App\Modules\ExternalApi\Core\Exceptions\ConfigurationException;
use App\Modules\ExternalApi\Core\Exceptions\ExternalApiException;
use App\Modules\ExternalApi\Core\Exceptions\HttpException;
use App\Modules\ExternalApi\Core\Exceptions\RateLimitException;
use App\Modules\ExternalApi\Models\ExternalApiConnection;
use App\Modules\ExternalApi\Modules\Asaas\Services\AsaasManager;
use App\Modules\ExternalApi\Modules\Iugu\Configuration\IuguConfiguration;
use App\Modules\ExternalApi\Modules\Iugu\Http\IuguHttpClient;
use App\Modules\ExternalApi\Modules\Iugu\Services\IuguManager;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class ExternalApiRetrySafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateProjectFiles();
        Http::preventStrayRequests();

        config([
            'external-apis.fake.enabled' => false,
            'external-apis.cache.enabled' => false,
            'external-apis.logging.enabled' => false,
            'external-apis.retry.base_delay_ms' => 0,
            'external-apis.retry.max_delay_ms' => 0,
            'external-apis.retry.jitter_ms' => 0,
            'external-apis.modules.spotify.client_id' => 'sec019-dummy-client',
            'external-apis.modules.spotify.client_secret' => 'sec019-dummy-secret',
            'external-apis.modules.spotify.auth_url' => 'https://sec019-spotify.example.test/auth',
            'external-apis.modules.spotify.redirect_uri' => 'https://sec019-app.example.test/callback',
        ]);

        foreach (['asaas', 'iugu', 'spotify'] as $module) {
            config([
                "external-apis.modules.{$module}.fake" => false,
                "external-apis.modules.{$module}.base_url" => "https://sec019-{$module}.example.test/v1",
                "external-apis.modules.{$module}.api_key" => 'sec019-dummy-key',
                "external-apis.modules.{$module}.retry_attempts" => 3,
                "external-apis.modules.{$module}.rate_limit" => 1000,
            ]);
        }
    }

    #[DataProvider('financialMutations')]
    public function test_financial_mutations_are_sent_once_even_when_the_response_is_lost_or_failed(string $operation, string $failure): void
    {
        $requests = [];
        $effects = [];
        $module = $operation === 'iugu-post' ? 'iugu' : 'asaas';
        $path = $operation === 'customer' || $operation === 'iugu-post' ? 'customers' : 'payments';
        $method = match ($operation) {
            'put' => 'PUT',
            'delete' => 'DELETE',
            default => 'POST',
        };
        $payload = $path === 'customers'
            ? ['name' => 'SEC019 Synthetic Customer', 'externalReference' => 'sec019-correlation']
            : ['customer' => 'sec019-customer', 'billingType' => 'BOLETO', 'value' => 12.5, 'dueDate' => '2030-01-01', 'externalReference' => 'sec019-correlation'];

        Http::fake(["https://sec019-{$module}.example.test/*" => function (Request $request) use (&$requests, &$effects, $failure, $payload): PromiseInterface {
            $requests[] = $request;

            if ($request->method() === 'GET') {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return Http::response(['data' => ($query['externalReference'] ?? null) === $payload['externalReference']
                    ? array_map(fn (string $id): array => ['id' => $id, 'externalReference' => $payload['externalReference']], $effects)
                    : []]);
            }

            $effects[] = 'sec019-accepted-'.(count($effects) + 1);

            return $this->failedResponse($failure);
        }]);

        $exception = $this->captureFailure(fn () => $this->financialOperation($operation, $payload));
        $this->assertTransportFailure($exception, $failure);
        $this->assertCount(1, $requests, 'An uncertain mutation must not be sent again automatically.');
        $this->assertSame(['sec019-accepted-1'], $effects);
        $this->assertSame($method, $requests[0]->method());
        $this->assertSame($method === 'DELETE' ? [] : $payload, $requests[0]->data());
        $this->assertTrue($requests[0]->hasHeader($module === 'iugu' ? 'Authorization' : 'access_token', $module === 'iugu' ? 'Basic '.base64_encode('sec019-dummy-key:') : 'sec019-dummy-key'));
        $this->assertFalse($requests[0]->hasHeader('Idempotency-Key'));

        if ($failure === 'connection' && in_array($operation, ['customer', 'payment'], true)) {
            $manager = app(AsaasManager::class);
            $recovered = $operation === 'customer'
                ? $manager->customers()->list(['externalReference' => $payload['externalReference']])
                : $manager->payments()->list(['externalReference' => $payload['externalReference']]);

            $this->assertCount(1, $recovered['data']);
            $this->assertSame('sec019-accepted-1', $recovered['data'][0]['id']);
            $this->assertSame($payload['externalReference'], $recovered['data'][0]['externalReference']);
            $this->assertCount(2, $requests);
            $this->assertSame(['POST', 'GET'], array_map(fn (Request $request): string => $request->method(), $requests));
            $this->assertSame("https://sec019-asaas.example.test/v1/{$path}?externalReference=sec019-correlation", $requests[1]->url());
            $this->assertSame(['sec019-accepted-1'], $effects, 'Consulting an accepted operation must not create another one.');
        }
    }

    /** @return array<string, array{string, string}> */
    public static function financialMutations(): array
    {
        return [
            'payment response lost' => ['payment', 'connection'],
            'payment server failure' => ['payment', '500'],
            'payment rate limited' => ['payment', '429'],
            'customer response lost' => ['customer', 'connection'],
            'customer server failure' => ['customer', '500'],
            'iugu generic post response lost' => ['iugu-post', 'connection'],
            'iugu generic post rate limited' => ['iugu-post', '429'],
            'put server failure' => ['put', '500'],
            'delete rate limited' => ['delete', '429'],
        ];
    }

    #[DataProvider('rawRequests')]
    public function test_raw_transport_retries_only_reads(string $method, string $failure): void
    {
        $requests = [];
        $effects = 0;
        Http::fake(['https://sec019-iugu.example.test/*' => function (Request $request) use (&$requests, &$effects, $method, $failure): PromiseInterface {
            $requests[] = $request;

            if ($method !== 'GET') {
                $effects++;
            } elseif (count($requests) === 2) {
                return Http::response(['id' => 'sec019-read-recovered']);
            }

            return $this->failedResponse($failure);
        }]);

        $operation = $this->rawOperation($method);

        if ($method === 'GET') {
            $this->assertSame('sec019-read-recovered', $operation()->getData()['id']);
        } else {
            $this->assertTransportFailure($this->captureFailure($operation), $failure);
        }

        $this->assertCount($method === 'GET' ? 2 : 1, $requests);
        $this->assertSame($method === 'GET' ? 0 : 1, $effects);
        foreach ($requests as $request) {
            $this->assertSame($method, $request->method());
            $this->assertSame('https://sec019-iugu.example.test/v1/raw-probe', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Basic '.base64_encode('sec019-dummy-key:')));
            $this->assertFalse($request->hasHeader('Idempotency-Key'));
            $this->assertSame($method === 'GET' ? [] : ['reference' => 'sec019-raw'], $request->data());
        }
    }

    /** @return array<string, array{string, string}> */
    public static function rawRequests(): array
    {
        return [
            'raw post connection' => ['POST', 'connection'],
            'raw put server error' => ['PUT', '500'],
            'raw delete rate limit' => ['DELETE', '429'],
            'raw get recovers' => ['GET', 'connection'],
        ];
    }

    #[DataProvider('readRequests')]
    public function test_get_recovery_and_attempt_limits_remain_available(string $failure, bool $recover, int $configuredAttempts, int $expectedAttempts): void
    {
        config(['external-apis.modules.asaas.retry_attempts' => $configuredAttempts]);
        $requests = [];
        Http::fake(['https://sec019-asaas.example.test/*' => function (Request $request) use (&$requests, $failure, $recover): PromiseInterface {
            $requests[] = $request;

            if ($recover && count($requests) === 2) {
                return Http::response(['data' => [['id' => 'sec019-existing']]]);
            }

            return $this->failedResponse($failure);
        }]);

        $operation = fn (): array => app(AsaasManager::class)->customers()->list(['limit' => 2, 'tag' => 'sec019']);

        if ($recover) {
            $this->assertSame(['data' => [['id' => 'sec019-existing']]], $operation());
        } else {
            $this->assertTransportFailure($this->captureFailure($operation), $failure);
        }

        $this->assertCount($expectedAttempts, $requests);
        foreach ($requests as $request) {
            $this->assertSame('GET', $request->method());
            $this->assertSame($requests[0]->url(), $request->url());
            $this->assertSame('https://sec019-asaas.example.test/v1/customers', strtok($request->url(), '?'));
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame(['limit' => '2', 'tag' => 'sec019'], $query);
            $this->assertTrue($request->hasHeader('access_token', 'sec019-dummy-key'));
        }
    }

    /** @return array<string, array{string, bool, int, int}> */
    public static function readRequests(): array
    {
        return [
            'connection recovery' => ['connection', true, 3, 2],
            'server recovery' => ['500', true, 3, 2],
            'rate limit recovery' => ['429', true, 3, 2],
            'server exhaustion' => ['500', false, 3, 3],
            'bad request is terminal' => ['400', false, 3, 1],
            'zero configured retries still sends once' => ['500', false, 0, 1],
        ];
    }

    #[DataProvider('oauthGrants')]
    public function test_oauth_token_posts_are_not_replayed(string $grant, string $failure): void
    {
        $requests = [];
        $issuedTokens = 0;
        Http::fake(['https://sec019-spotify.example.test/auth/api/token' => function (Request $request) use (&$requests, &$issuedTokens, $failure): PromiseInterface {
            $requests[] = $request;
            $issuedTokens++;

            return $this->failedResponse($failure);
        }]);

        $user = User::factory()->create();
        $auth = app(SpotifyManager::class)->auth();
        $connection = null;
        $expectedBody = ['grant_type' => $grant];

        if ($grant === 'authorization_code') {
            parse_str((string) parse_url($auth->getAuthorizationUrl($user), PHP_URL_QUERY), $query);
            $this->assertIsString($query['state']);
            $state = $query['state'];
            $expectedBody += ['code' => 'sec019-code', 'redirect_uri' => 'https://sec019-app.example.test/callback'];
            $operation = fn () => $auth->handleCallback($user, 'sec019-code', $state);
        } elseif ($grant === 'refresh_token') {
            $connection = ExternalApiConnection::create([
                'user_id' => $user->id,
                'provider' => 'spotify',
                'provider_user_id' => 'sec019-spotify-user',
                'access_token' => 'sec019-expired-access',
                'refresh_token' => 'sec019-refresh',
                'token_type' => 'Bearer',
                'scopes' => ['user-read-email'],
                'expires_at' => now()->subMinute(),
                'metadata' => ['fixture' => 'sec019'],
            ]);
            $expectedBody['refresh_token'] = 'sec019-refresh';
            $operation = fn () => $auth->getUserAccessToken($user);
        } else {
            $operation = fn () => $auth->getClientAccessToken();
        }

        $before = $connection?->refresh()->getRawOriginal();
        $exception = $this->captureFailure($operation);
        $this->assertInstanceOf($failure === 'connection' ? ConnectionException::class : ExternalApiException::class, $exception);
        if ($failure !== 'connection') {
            $this->assertSame((int) $failure, $exception->getCode());
        }
        $this->assertCount(1, $requests, 'OAuth grants must not issue replacement tokens automatically after an uncertain result.');
        $this->assertSame(1, $issuedTokens);
        $this->assertSame('POST', $requests[0]->method());
        $this->assertSame($expectedBody, $requests[0]->data());
        $this->assertTrue($requests[0]->hasHeader('Authorization', 'Basic '.base64_encode('sec019-dummy-client:sec019-dummy-secret')));
        $this->assertFalse($requests[0]->hasHeader('Idempotency-Key'));
        $this->assertSame($before, $connection?->refresh()->getRawOriginal());
        $this->assertSame($connection === null ? 0 : 1, ExternalApiConnection::query()->count());
        $this->assertNull(Cache::get('external-api:spotify:client-token:'.hash('xxh128', 'sec019-dummy-client')));
    }

    /** @return array<string, array{string, string}> */
    public static function oauthGrants(): array
    {
        return [
            'authorization code response lost' => ['authorization_code', 'connection'],
            'refresh response lost' => ['refresh_token', 'connection'],
            'client credentials response lost' => ['client_credentials', 'connection'],
            'client credentials server error' => ['client_credentials', '500'],
            'refresh rate limit' => ['refresh_token', '429'],
        ];
    }

    public function test_fake_guards_and_supported_fake_auth_send_no_http(): void
    {
        Http::fake();
        $realClient = app(IuguManager::class)->getHttpClient();
        $raw = $this->rawOperation('POST');
        config([
            'external-apis.modules.iugu.fake' => true,
            'external-apis.modules.spotify.fake' => true,
        ]);

        foreach ([fn () => $realClient->post('/customers', ['name' => 'SEC019 Synthetic']), $raw] as $operation) {
            $exception = $this->captureFailure($operation);
            $this->assertInstanceOf(ConfigurationException::class, $exception);
            $this->assertSame('Fake mode is not supported by this HTTP client.', $exception->getMessage());
        }

        $this->assertNotEmpty(app(SpotifyManager::class)->auth()->getClientAccessToken());
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function financialOperation(string $operation, array $payload): array
    {
        return match ($operation) {
            'customer' => app(AsaasManager::class)->customers()->create($payload),
            'payment' => app(AsaasManager::class)->payments()->create($payload),
            'iugu-post' => app(IuguManager::class)->getHttpClient()->post('/customers', $payload)->getData(),
            'put' => app(AsaasManager::class)->getHttpClient()->put('/payments/sec019-existing', $payload)->getData(),
            'delete' => app(AsaasManager::class)->getHttpClient()->delete('/payments/sec019-existing')->getData(),
            default => throw new LogicException('Unknown SEC019 financial fixture.'),
        };
    }

    /** @return Closure(): BaseResponse */
    private function rawOperation(string $method): Closure
    {
        $client = new class(app(IuguConfiguration::class)) extends IuguHttpClient
        {
            public function transmit(string $method): BaseResponse
            {
                return $this->rawRequest($method, 'https://sec019-iugu.example.test/v1/raw-probe', $method === 'GET' ? [] : ['json' => ['reference' => 'sec019-raw']]);
            }
        };

        return fn (): BaseResponse => $client->transmit($method);
    }

    private function failedResponse(string $failure): PromiseInterface
    {
        if ($failure === 'connection') {
            throw new ConnectionException('SEC019 synthetic accepted response lost.');
        }

        return Http::response(['error' => 'SEC019 synthetic failure'], (int) $failure, ['Retry-After' => '0']);
    }

    private function captureFailure(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            return $exception;
        }

        $this->fail('The synthetic failed request unexpectedly succeeded.');
    }

    private function assertTransportFailure(Throwable $exception, string $failure): void
    {
        $this->assertInstanceOf(match ($failure) {
            'connection' => ConnectionException::class,
            '429' => RateLimitException::class,
            default => HttpException::class,
        }, $exception);

        if ($failure !== 'connection' && $failure !== '429') {
            $this->assertSame((int) $failure, $exception->getCode());
        }
    }
}
