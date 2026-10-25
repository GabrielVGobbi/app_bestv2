<?php

namespace Tests\Feature\ExternalApi;

use App\Modules\ExternalApi\Core\Abstracts\AbstractHttpClient;
use App\Modules\ExternalApi\Core\Exceptions\HttpException;
use App\Modules\ExternalApi\Core\Support\ExternalApiRequestLogger;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use App\Modules\Slack\DTOs\SlackMessageDTO;
use App\Modules\Slack\Enums\SlackAlertLevel;
use App\Modules\Slack\Services\SlackService;
use App\Supports\AppSettings;
use App\Supports\RedactSlackLogs;
use App\Supports\SensitiveDataRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as RequestFacade;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Processor\PsrLogMessageProcessor;
use RuntimeException;
use Tests\TestCase;
use Tests\TestEnvironment;

class IntegrationRedactionTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.com/services/TSEC004/BSEC004/SEC004_PRIVATE_WEBHOOK';

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->logs = new TestHandler;
        Log::swap(new LaravelLogger(new Logger('sec004-memory', [$this->logs])));
        config([
            'slack.enabled' => true, 'slack.webhook_url' => self::WEBHOOK,
            'slack.app_name' => 'Aplicação Café', 'slack.app_url' => 'https://example.test/app', 'slack.environment' => 'testing',
            'external-apis.logging.enabled' => true, 'external-apis.logging.channel' => null,
            'external-apis.modules.spotify.client_secret' => 'SEC004_KNOWN_CREDENTIAL',
            'external-apis.modules.spotify.base_url' => 'https://api.spotify.com/v1',
        ]);
        Cache::forget('external-api:diagnostics:recent-requests');
    }

    public function test_request_logger_projects_operational_fields_before_log_and_cache(): void
    {
        $configuration = app(SpotifyManager::class)->getConfiguration();
        app(ExternalApiRequestLogger::class)->record($configuration, [
            'method' => 'GET', 'url' => 'https://user:SEC004_URL_PASSWORD@api.example.test/v1/records?token=SEC004_QUERY#SEC004_FRAGMENT',
            'endpoint' => '/v1/records?api_key=SEC004_ENDPOINT#SEC004_ENDPOINT_FRAGMENT',
            'status' => 'failed', 'status_code' => 503, 'duration_ms' => 42, 'cache_hit' => false, 'retry_count' => 2,
            'error' => 'Remote response contains SEC004_RAW_ERROR_BODY',
            'headers' => ['Authorization' => 'Bearer SEC004_HEADER'],
            'body' => ['password' => 'SEC004_BODY'], 'metadata' => ['free' => 'SEC004_METADATA'],
        ]);
        $entry = app(ExternalApiRequestLogger::class)->recent()[0];
        $allowed = ['id', 'module', 'mode', 'user_id', 'recorded_at', 'method', 'url', 'endpoint', 'status', 'status_code', 'duration_ms', 'cache_hit', 'retry_count', 'error'];
        $this->assertSame([], array_diff(array_keys($entry), $allowed));
        $this->assertSame('https://api.example.test/v1/records', $entry['url']);
        $this->assertSame('/v1/records', $entry['endpoint']);
        $this->assertSame('spotify', $entry['module']);
        $this->assertSame(503, $entry['status_code']);
        $this->assertSame(42, $entry['duration_ms']);
        $this->assertSame(2, $entry['retry_count']);
        $this->assertFalse($entry['cache_hit']);
        $this->assertIsString($entry['error']);
        $this->assertNotSame('', $entry['error']);
        $markers = ['SEC004_URL_PASSWORD', 'SEC004_QUERY', 'SEC004_FRAGMENT', 'SEC004_ENDPOINT', 'SEC004_RAW_ERROR_BODY', 'SEC004_HEADER', 'SEC004_BODY', 'SEC004_METADATA'];
        $this->assertMarkersAbsent(Cache::get('external-api:diagnostics:recent-requests'), $markers);
        $this->assertCount(1, $this->logs->getRecords());
        $this->assertMarkersAbsent($this->logs->getRecords()[0]->context, $markers);
    }

    public function test_old_diagnostic_cache_is_projected_again_when_read(): void
    {
        Cache::put('external-api:diagnostics:recent-requests', [[
            'id' => '00000000-0000-4000-8000-000000000004', 'module' => 'spotify', 'mode' => 'real', 'user_id' => null,
            'recorded_at' => '2026-10-16T12:00:00+00:00', 'method' => 'GET', 'status' => 'failed', 'status_code' => 500,
            'url' => 'https://user:SEC004_OLD_PASSWORD@api.example.test/v1/records?access_token=SEC004_OLD_QUERY#SEC004_OLD_FRAGMENT',
            'endpoint' => '/v1/records?secret=SEC004_OLD_ENDPOINT', 'error' => 'SEC004_OLD_RESPONSE_BODY',
            'body' => 'SEC004_OLD_BODY', 'headers' => ['Cookie' => 'SEC004_OLD_COOKIE'], 'metadata' => ['private' => 'SEC004_OLD_METADATA'],
            'duration_ms' => 13, 'cache_hit' => false, 'retry_count' => 1,
        ]], 60);
        $entry = app(ExternalApiRequestLogger::class)->recent()[0];
        $this->assertSame('https://api.example.test/v1/records', $entry['url']);
        $this->assertSame('/v1/records', $entry['endpoint']);
        foreach (['body', 'headers', 'metadata'] as $key) {
            $this->assertArrayNotHasKey($key, $entry);
        }
        $this->assertSame(500, $entry['status_code']);
        $this->assertSame(13, $entry['duration_ms']);
        $this->assertMarkersAbsent($entry, ['SEC004_OLD_PASSWORD', 'SEC004_OLD_QUERY', 'SEC004_OLD_FRAGMENT', 'SEC004_OLD_ENDPOINT', 'SEC004_OLD_RESPONSE_BODY', 'SEC004_OLD_BODY', 'SEC004_OLD_COOKIE', 'SEC004_OLD_METADATA']);
    }

    public function test_real_client_failure_body_is_not_retained_and_successful_retry_recovers(): void
    {
        config([
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => 'sec018-dummy-client',
            'external-apis.modules.spotify.client_secret' => 'SEC004_KNOWN_CREDENTIAL',
            'external-apis.modules.spotify.retry_attempts' => 1,
            'external-apis.cache.enabled' => false,
        ]);
        Http::fake(['https://api.spotify.com/v1/records*' => Http::sequence()
            ->push(['message' => 'SEC004_REMOTE_PRIVATE_BODY'], 500)
            ->push(['label' => 'Café recuperado'], 200)]);
        $client = new class(app(SpotifyManager::class)->getConfiguration()) extends AbstractHttpClient
        {
            protected function getHeaders(): array
            {
                return [];
            }
        };
        try {
            $client->get('/records?token=SEC004_CLIENT_URL_TOKEN');
            $this->fail('The synthetic upstream HTTP error must still be reported to its caller.');
        } catch (HttpException $exception) {
            $this->assertSame(500, $exception->getCode());
        }
        $this->assertMarkersAbsent(app(ExternalApiRequestLogger::class)->recent(), ['SEC004_REMOTE_PRIVATE_BODY', 'SEC004_CLIENT_URL_TOKEN']);
        $this->assertMarkersAbsent($this->logs->getRecords()[0]->context, ['SEC004_REMOTE_PRIVATE_BODY', 'SEC004_CLIENT_URL_TOKEN']);
        $this->assertSame(['label' => 'Café recuperado'], $client->get('/records')->getData());
        $this->assertSame('success', app(ExternalApiRequestLogger::class)->recent()[0]['status']);
        Http::assertSentCount(2);
    }

    public function test_slack_send_redacts_every_payload_channel_and_keeps_the_original_destination_internal(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok')]);
        $sent = app(SlackService::class)->send(new SlackMessageDTO(
            text: 'Processamento Café concluído. token=SEC004_TEXT_SECRET SEC004_KNOWN_CREDENTIAL https://user:SEC004_MESSAGE_PASSWORD@example.test/status?token=SEC004_MESSAGE_QUERY#SEC004_MESSAGE_FRAGMENT',
            title: 'Lote password=SEC004_TITLE_SECRET',
            fields: [
                ['title' => 'Authorization', 'value' => 'SEC004_FIELD_SECRET'],
                ['title' => 'Observação', 'value' => 'Café normal'],
                ['title' => 'Cookie', 'value' => 'SEC004_COOKIE_SECRET'],
                ['title' => 'Destino', 'value' => self::WEBHOOK],
            ],
            footer: 'Aplicação Café | https://example.test/app?api_key=SEC004_FOOTER_SECRET',
            fallback: 'Fallback Café password=SEC004_FALLBACK_SECRET',
        ));
        $this->assertTrue($sent);
        $payload = $this->sentSlackPayload();
        $this->assertMarkersAbsent($payload, ['SEC004_TEXT_SECRET', 'SEC004_KNOWN_CREDENTIAL', 'SEC004_MESSAGE_PASSWORD', 'SEC004_MESSAGE_QUERY', 'SEC004_MESSAGE_FRAGMENT', 'SEC004_TITLE_SECRET', 'SEC004_FIELD_SECRET', 'SEC004_COOKIE_SECRET', 'SEC004_PRIVATE_WEBHOOK', 'SEC004_FOOTER_SECRET', 'SEC004_FALLBACK_SECRET']);
        $attachment = $payload['attachments'][0];
        $this->assertStringContainsString('Processamento Café concluído.', $attachment['text']);
        $this->assertStringContainsString('https://example.test/status', $attachment['text']);
        $this->assertStringContainsString('Fallback Café', $attachment['fallback']);
        $this->assertStringContainsString('Aplicação Café', $attachment['footer']);
        $fields = array_column($attachment['fields'], 'value', 'title');
        $this->assertSame('Café normal', $fields['Observação']);
    }

    public function test_slack_alert_redacts_nested_context_before_serializing_fields(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok')]);
        $this->assertTrue(app(SlackService::class)->alert('Lote Café concluído', SlackAlertLevel::Info, [
            'authorization' => 'SEC004_CONTEXT_AUTH',
            'details' => ['api_key' => 'SEC004_NESTED_KEY', 'nested' => ['password' => 'SEC004_NESTED_PASSWORD'], 'label' => 'Café preservado'],
            'item_count' => 3,
        ]));
        $payload = $this->sentSlackPayload();
        $this->assertMarkersAbsent($payload, ['SEC004_CONTEXT_AUTH', 'SEC004_NESTED_KEY', 'SEC004_NESTED_PASSWORD']);
        $fields = array_column($payload['attachments'][0]['fields'], 'value', 'title');
        $this->assertStringContainsString('Café preservado', $fields['Details']);
        $this->assertSame('3', $fields['Item_count']);
        $this->assertSame('Lote Café concluído', $payload['attachments'][0]['text']);
    }

    public function test_slack_exception_does_not_publish_raw_external_message_or_request_query(): void
    {
        Http::fake([self::WEBHOOK => Http::response('ok')]);
        RequestFacade::swap(Request::create('https://example.test/diagnostics?access_token=SEC004_REQUEST_QUERY', 'GET'));
        $exception = new RuntimeException('SEC004_UNSTRUCTURED_EXTERNAL_BODY');
        $this->assertTrue(app(SlackService::class)->exception($exception, 'password=SEC004_EXCEPTION_CONTEXT'));
        $payload = $this->sentSlackPayload();
        $this->assertMarkersAbsent($payload, ['SEC004_UNSTRUCTURED_EXTERNAL_BODY', 'SEC004_REQUEST_QUERY', 'SEC004_EXCEPTION_CONTEXT']);
        $fields = array_column($payload['attachments'][0]['fields'], 'value', 'title');
        $this->assertSame(RuntimeException::class, $fields['Exceção']);
        $this->assertSame($exception->getFile().':'.$exception->getLine(), $fields['Arquivo']);
        $this->assertSame('https://example.test/diagnostics', $fields['URL']);
        $this->assertSame('GET', $fields['Método']);
    }

    public function test_slack_transport_failure_logs_no_webhook_or_remote_message_and_recovers(): void
    {
        Http::fake([self::WEBHOOK => static function (): never {
            throw new ConnectionException('Failure at '.self::WEBHOOK.' SEC004_TRANSPORT_BODY');
        }]);
        $this->assertFalse(app(SlackService::class)->alert('Alerta Café'));
        $this->assertCount(1, $this->logs->getRecords());
        $record = $this->logs->getRecords()[0];
        $this->assertMarkersAbsent(['message' => $record->message, 'context' => $record->context], ['SEC004_PRIVATE_WEBHOOK', 'SEC004_TRANSPORT_BODY']);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK => Http::response('ok')]);
        $this->assertTrue(app(SlackService::class)->alert('Alerta Café recuperado'));
        $this->assertSame('Alerta Café recuperado', $this->sentSlackPayload()['attachments'][0]['text']);
    }

    public function test_slack_kill_switch_and_db_override_fallback_keep_their_existing_contract(): void
    {
        $dbWebhook = 'https://hooks.slack.com/services/TSEC004/BSEC004/SEC004_DB_WEBHOOK';
        Http::fake([self::WEBHOOK => Http::response('ok'), $dbWebhook => Http::response('ok')]);
        AppSettings::set('slack', 'enabled', false);
        $this->assertFalse(app(SlackService::class)->send(new SlackMessageDTO('Café desativado')));
        Http::assertNothingSent();
        AppSettings::set('slack', 'enabled', true);
        AppSettings::set('slack', 'webhook_url', $dbWebhook);
        $this->assertTrue(app(SlackService::class)->alert('Café override'));
        $this->assertSame($dbWebhook, Http::recorded()[0][0]->url());
        AppSettings::set('slack', 'webhook_url', null);
        $this->assertTrue(app(SlackService::class)->alert('Café fallback'));
        $this->assertSame(self::WEBHOOK, Http::recorded()[1][0]->url());
        Http::assertSentCount(2);
        config(['slack.webhook_url' => null]);
        $this->assertFalse(app(SlackService::class)->isEnabled());
        $this->assertFalse(app(SlackService::class)->alert('Café sem destino'));
        Http::assertSentCount(2);
    }

    public function test_slack_monolog_sink_is_tapped_and_redacts_without_constructing_a_network_handler(): void
    {
        TestEnvironment::assertSafeEnvironment();
        $sourceConfig = require dirname(__DIR__, 3).'/config/logging.php';
        $this->assertContains(RedactSlackLogs::class, $sourceConfig['channels']['slack']['tap']);
        $handler = new TestHandler;
        $monolog = new Logger('sec004-slack-sink', [$handler]);
        $logger = new LaravelLogger($monolog);
        app(RedactSlackLogs::class)($logger);
        $object = new class
        {
            public string $value = 'SEC004_MONOLOG_OBJECT_PRIVATE';

            public int $calls = 0;

            public function __toString(): string
            {
                $this->calls++;

                return $this->value;
            }
        };
        $monolog->pushProcessor(static fn (LogRecord $record): LogRecord => $record->with(extra: [
            'Authorization' => 'SEC004_MONOLOG_EXTRA_AUTH',
            'object' => $object,
            'exception' => new RuntimeException('SEC004_MONOLOG_EXTRA_EXCEPTION'),
        ]));
        $logger->error('Lote Café token=SEC004_MONOLOG_TOKEN '.self::WEBHOOK, [
            'Authorization' => 'SEC004_MONOLOG_AUTH',
            'nested' => ['api_key' => 'SEC004_MONOLOG_KEY', 'label' => 'Café contexto'],
            'exception' => new RuntimeException('SEC004_MONOLOG_EXCEPTION_BODY'),
            'object' => $object,
        ]);
        $this->assertCount(1, $handler->getRecords());
        $record = $handler->getRecords()[0];
        $this->assertMarkersAbsent(['message' => $record->message, 'context' => $record->context, 'extra' => $record->extra], ['SEC004_MONOLOG_TOKEN', 'SEC004_PRIVATE_WEBHOOK', 'SEC004_MONOLOG_AUTH', 'SEC004_MONOLOG_KEY', 'SEC004_MONOLOG_EXCEPTION_BODY', 'SEC004_MONOLOG_OBJECT_PRIVATE', 'SEC004_MONOLOG_EXTRA_AUTH', 'SEC004_MONOLOG_EXTRA_EXCEPTION']);
        $this->assertStringContainsString('Lote Café', $record->message);
        $this->assertSame('Café contexto', $record->context['nested']['label']);
        $this->assertNotInstanceOf(\Throwable::class, $record->context['exception'] ?? null);
        $this->assertNotInstanceOf(\Throwable::class, $record->extra['exception'] ?? null);
        $this->assertNotSame($object, $record->context['object'] ?? null);
        $this->assertNotSame($object, $record->extra['object'] ?? null);
        $safe = ['message' => $record->message, 'context' => $record->context, 'extra' => $record->extra];
        $this->assertSame($safe, app(SensitiveDataRedactor::class)->value($safe), 'Redaction must be idempotent across the Logger and sink.');
        $previous = [];
        foreach (['LOG_STACK', 'SLACK_ENABLED'] as $key) {
            $previous[$key] = ['env_present' => array_key_exists($key, $_ENV), 'server_present' => array_key_exists($key, $_SERVER), 'env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null, 'process' => getenv($key)];
        }
        try {
            $_ENV['LOG_STACK'] = $_SERVER['LOG_STACK'] = 'single,slack';
            $_ENV['SLACK_ENABLED'] = $_SERVER['SLACK_ENABLED'] = 'false';
            putenv('LOG_STACK=single,slack');
            putenv('SLACK_ENABLED=false');
            $withSlack = require dirname(__DIR__, 3).'/config/logging.php';
            $this->assertSame(['single', 'slack'], $withSlack['channels']['stack']['channels']);
            $this->assertContains(RedactSlackLogs::class, $withSlack['channels']['stack']['tap']);
            $_ENV['LOG_STACK'] = $_SERVER['LOG_STACK'] = 'single';
            putenv('LOG_STACK=single');
            $withoutSlack = require dirname(__DIR__, 3).'/config/logging.php';
            $this->assertSame(['single'], $withoutSlack['channels']['stack']['channels']);
            $this->assertSame([], $withoutSlack['channels']['stack']['tap']);
        } finally {
            foreach ($previous as $key => $values) {
                if (! $values['env_present']) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $values['env'];
                }
                if (! $values['server_present']) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $values['server'];
                }
                $values['process'] === false ? putenv($key) : putenv($key.'='.$values['process']);
            }
        }
        config([
            'logging.channels.single' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'processors' => [PsrLogMessageProcessor::class]],
            'logging.channels.slack' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'processors' => [PsrLogMessageProcessor::class], 'tap' => $withSlack['channels']['slack']['tap']],
            'logging.channels.stack' => $withSlack['channels']['stack'],
        ]);
        $manager = new LogManager($this->app);
        $stack = $manager->channel('stack');
        $this->assertInstanceOf(LaravelLogger::class, $stack);
        $stackMonolog = $stack->getLogger();
        $this->assertInstanceOf(Logger::class, $stackMonolog);
        $stackMonolog->pushProcessor(static fn (LogRecord $record): LogRecord => $record->with(extra: [
            'object' => $object, 'exception' => new RuntimeException('SEC004_STACK_EXTRA_BODY'),
        ]));
        $stack->error('Lote Café {exception} {object} {Authorization}', [
            'exception' => new RuntimeException('SEC004_STACK_UNSTRUCTURED_BODY'),
            'object' => $object, 'Authorization' => 'SEC004_STACK_AUTH',
        ]);
        foreach (['single', 'slack'] as $name) {
            $channel = $manager->channel($name);
            $this->assertInstanceOf(LaravelLogger::class, $channel);
            $underlying = $channel->getLogger();
            $this->assertInstanceOf(Logger::class, $underlying);
            $sink = $underlying->getHandlers()[0];
            $this->assertInstanceOf(TestHandler::class, $sink);
            $this->assertCount(1, $sink->getRecords());
            $observed = $sink->getRecords()[0];
            $this->assertStringContainsString('Lote Café', $observed->message);
            $safe = ['message' => $observed->message, 'context' => $observed->context, 'extra' => $observed->extra];
            $this->assertMarkersAbsent($safe, ['SEC004_STACK_UNSTRUCTURED_BODY', 'SEC004_MONOLOG_OBJECT_PRIVATE', 'SEC004_STACK_AUTH', 'SEC004_STACK_EXTRA_BODY']);
            $this->assertSame($safe, app(SensitiveDataRedactor::class)->value($safe));
        }
        $this->assertSame(0, $object->calls, 'Objects must be replaced before the real interpolation processor calls __toString.');
        config(['logging.channels.stack' => $withoutSlack['channels']['stack']]);
        $withoutSlackManager = new LogManager($this->app);
        $plain = $withoutSlackManager->channel('stack');
        $this->assertInstanceOf(LaravelLogger::class, $plain);
        $plain->info('Café sem Slack');
        $plainMonolog = $plain->getLogger();
        $this->assertInstanceOf(Logger::class, $plainMonolog);
        $plainSink = $plainMonolog->getHandlers()[0];
        $this->assertInstanceOf(TestHandler::class, $plainSink);
        $this->assertSame('Café sem Slack', $plainSink->getRecords()[0]->message);
        Http::assertNothingSent();
    }

    /** @return array<string, mixed> */
    private function sentSlackPayload(): array
    {
        Http::assertSentCount(1);
        $request = Http::recorded()[0][0];
        $this->assertSame(self::WEBHOOK, $request->url());

        return $request->data();
    }

    /** @param list<string> $markers */
    private function assertMarkersAbsent(mixed $value, array $markers): void
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($markers as $marker) {
            $this->assertStringNotContainsString($marker, $json);
        }
    }
}
