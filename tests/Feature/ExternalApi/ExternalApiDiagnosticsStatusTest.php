<?php

namespace Tests\Feature\ExternalApi;

use App\Modules\ExternalApi\Core\Exceptions\ExternalApiException;
use App\Modules\ExternalApi\Core\Exceptions\HttpException;
use App\Modules\ExternalApi\Core\Exceptions\RateLimitException;
use App\Modules\ExternalApi\Core\Support\ExternalApiRequestLogger;
use App\Modules\ExternalApi\Modules\Asaas\Configuration\AsaasConfiguration;
use App\Modules\ExternalApi\Modules\Asaas\Http\AsaasHttpClient;
use App\Modules\ExternalApi\Modules\Spotify\Services\AuthService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class ExternalApiDiagnosticsStatusTest extends TestCase
{
    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logs = new TestHandler;
        Log::swap(new LaravelLogger(new Logger('ext001-memory', [$this->logs])));
        Http::preventStrayRequests();
        config([
            'external-apis.fake.enabled' => false,
            'external-apis.cache.enabled' => false,
            'external-apis.logging.enabled' => true,
            'external-apis.logging.channel' => null,
            'external-apis.retry.base_delay_ms' => 0,
            'external-apis.retry.max_delay_ms' => 0,
            'external-apis.retry.jitter_ms' => 0,
            'external-apis.modules.asaas.fake' => false,
            'external-apis.modules.asaas.api_key' => 'EXT001_SYNTHETIC_KEY',
            'external-apis.modules.asaas.base_url' => 'https://ext001-asaas.example.test/v1',
            'external-apis.modules.asaas.retry_attempts' => 2,
            'external-apis.modules.asaas.rate_limit' => 1000,
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => 'EXT001_SYNTHETIC_CLIENT',
            'external-apis.modules.spotify.client_secret' => 'EXT001_SYNTHETIC_SECRET',
            'external-apis.modules.spotify.auth_url' => 'https://ext001-spotify.example.test/auth',
            'external-apis.modules.spotify.retry_attempts' => 9,
        ]);
    }

    #[DataProvider('httpFailures')]
    public function test_diagnostics_preserve_http_status_and_redact_payload(string $method, int $status, int $attempts): void
    {
        Http::fake(['https://ext001-asaas.example.test/*' => Http::response(['error' => 'EXT001_REMOTE_BODY_SECRET'], $status)]);
        $client = new AsaasHttpClient(new AsaasConfiguration);
        $failure = $this->captureFailure(fn () => $method === 'GET'
            ? $client->get('/customers?token=EXT001_QUERY_SECRET')
            : $client->post('/customers?token=EXT001_QUERY_SECRET', ['value' => 'EXT001_REQUEST_SECRET']));

        $this->assertInstanceOf(HttpException::class, $failure);
        $this->assertSame($status, $failure->getCode());
        $entry = app(ExternalApiRequestLogger::class)->recent()[0];
        $this->assertSame($status, $entry['status_code']);
        $this->assertSame('failed', $entry['status']);
        $this->assertSame($attempts - 1, $entry['retry_count']);
        $this->assertSame('External request failed.', $entry['error']);
        $this->assertSame('https://ext001-asaas.example.test/v1/customers', $entry['url']);
        $this->assertSame('/customers', $entry['endpoint']);
        $this->assertSame($method, $entry['method']);
        $this->assertFalse($entry['cache_hit']);
        Http::assertSentCount($attempts);
        $this->assertCount(1, $this->logs->getRecords());
        $this->assertSame($status, $this->logs->getRecords()[0]->context['status_code']);
        $this->assertNoSensitiveDiagnostics();
    }

    /** @return iterable<string, array{string, int, int}> */
    public static function httpFailures(): iterable
    {
        foreach (['GET', 'POST'] as $method) {
            foreach ([400, 401, 403, 404, 422, 500, 502, 503] as $status) {
                yield $method.' '.$status => [$method, $status, $method === 'GET' && $status >= 500 ? 2 : 1];
            }
        }
    }

    public function test_connection_failure_has_no_fabricated_status_and_is_redacted(): void
    {
        Http::fake(['https://ext001-asaas.example.test/*' => Http::failedConnection('EXT001_REMOTE_BODY_SECRET')]);
        $failure = $this->captureFailure(fn () => (new AsaasHttpClient(new AsaasConfiguration))->post('/customers'));
        $this->assertInstanceOf(ConnectionException::class, $failure);
        $entry = app(ExternalApiRequestLogger::class)->recent()[0];
        $this->assertSame(0, $entry['status_code']);
        $this->assertSame('failed', $entry['status']);
        $this->assertSame(0, $entry['retry_count']);
        Http::assertSentCount(1);
        $this->assertNoSensitiveDiagnostics();
    }

    public function test_rate_limit_keeps_429_without_retrying_post(): void
    {
        Http::fake(['https://ext001-asaas.example.test/*' => Http::response(['error' => 'EXT001_REMOTE_BODY_SECRET'], 429)]);
        $failure = $this->captureFailure(fn () => (new AsaasHttpClient(new AsaasConfiguration))->post('/customers'));
        $this->assertInstanceOf(RateLimitException::class, $failure);
        $entry = app(ExternalApiRequestLogger::class)->recent()[0];
        $this->assertSame(429, $entry['status_code']);
        $this->assertSame('rate_limited', $entry['status']);
        Http::assertSentCount(1);
        $this->assertNoSensitiveDiagnostics();
    }

    public function test_success_keeps_its_status_and_has_no_error(): void
    {
        Http::fake(['https://ext001-asaas.example.test/*' => Http::response(['id' => 'synthetic-customer'], 201)]);
        $this->assertSame(201, (new AsaasHttpClient(new AsaasConfiguration))->post('/customers')->getStatusCode());
        $entry = app(ExternalApiRequestLogger::class)->recent()[0];
        $this->assertSame(201, $entry['status_code']);
        $this->assertSame('success', $entry['status']);
        $this->assertNull($entry['error']);
        Http::assertSentCount(1);
    }

    #[DataProvider('safeErrors')]
    public function test_http_exception_does_not_embed_remote_body(int $status): void
    {
        Http::fake(['https://ext001-asaas.example.test/*' => Http::response(['error' => 'EXT001_REMOTE_BODY_SECRET'], $status)]);
        $failure = $this->captureFailure(fn () => (new AsaasHttpClient(new AsaasConfiguration))->post('/customers'));
        $this->assertInstanceOf(HttpException::class, $failure);
        $this->assertSame($status, $failure->getCode());
        $this->assertStringNotContainsString('EXT001_REMOTE_BODY_SECRET', $failure->getMessage());
        $this->assertStringContainsString((string) $status, $failure->getMessage());
    }

    #[DataProvider('safeErrors')]
    public function test_oauth_exception_keeps_status_without_remote_body(int $status): void
    {
        Http::fake(['https://ext001-spotify.example.test/*' => Http::response(['error' => 'EXT001_REMOTE_BODY_SECRET'], $status)]);
        $failure = $this->captureFailure(fn () => app(AuthService::class)->clientCredentialsToken());
        $this->assertInstanceOf(ExternalApiException::class, $failure);
        $this->assertSame($status, $failure->getCode());
        $this->assertStringNotContainsString('EXT001_REMOTE_BODY_SECRET', $failure->getMessage());
        $this->assertStringContainsString((string) $status, $failure->getMessage());
        Http::assertSentCount(1);
    }

    /** @return array<string, array{int}> */
    public static function safeErrors(): array
    {
        return ['client error' => [400], 'server error' => [503]];
    }

    private function captureFailure(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $failure) {
            return $failure;
        }

        $this->fail('Expected the controlled remote failure.');
    }

    private function assertNoSensitiveDiagnostics(): void
    {
        $payload = json_encode([
            Cache::get('external-api:diagnostics:recent-requests'),
            array_map(fn ($record): array => $record->context, $this->logs->getRecords()),
        ], JSON_THROW_ON_ERROR);
        foreach (['EXT001_REMOTE_BODY_SECRET', 'EXT001_QUERY_SECRET', 'EXT001_REQUEST_SECRET', 'EXT001_SYNTHETIC_KEY'] as $marker) {
            $this->assertStringNotContainsString($marker, $payload);
        }
    }
}
