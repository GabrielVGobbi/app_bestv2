<?php

namespace Tests\Feature\Email;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Models\EmailWebhookEvent;
use App\Modules\Email\Services\EmailWebhookService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\Fixtures\Email\SvixMessageFixture;
use Tests\TestEnvironment;

class EmailWebhookConcurrencyTest extends TestCase
{
    public function test_native_innodb_serializes_duplicate_event_and_out_of_order_projection(): void
    {
        if (getenv('MAIL_WEBHOOK_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Opt in MAIL_WEBHOOK_MYSQL_TESTS=1 and AUTHORITY_MYSQL_TESTS=1 for owned MySQL.');
        }
        $mysql = new DisposableMysql;
        $environment = null;
        $app = null;
        $connection = null;
        $workers = [];
        try {
            $mysql->start();
            $environment = new TestEnvironment;
            $app = DisposableMysql::bootApplication($mysql->settings(), $environment);
            $connection = $app['db']->connection();
            $this->assertSame('REPEATABLE-READ', $connection->scalar('SELECT @@transaction_isolation'));
            $this->assertSame(0, $app->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]));
            foreach (['duplicate', 'out-of-order'] as $scenario) {
                $workers = [];
                $messageId = 'mail002-'.$scenario;
                $log = EmailLog::factory()->create(['mailer' => 'resend', 'status' => EmailStatus::Sent, 'message_id' => $messageId, 'dispatch_token' => (string) Str::uuid()]);
                $manifest = $mysql->path.'/workers.json';
                file_put_contents($manifest, json_encode(['settings' => $mysql->settings(), 'scenario' => $scenario, 'message_id' => $messageId], JSON_THROW_ON_ERROR));
                $connection->beginTransaction();
                if ($scenario === 'duplicate') {
                    $connection->table('email_webhook_events')->where('provider', 'resend')->where('event_id', 'evt-mail002-duplicate')->lockForUpdate()->first();
                } else {
                    $connection->table('email_logs')->where('id', $log->id)->lockForUpdate()->first();
                }
                foreach ([0, 1] as $index) {
                    $worker = new Process([PHP_BINARY, dirname(__DIR__, 2).'/email-webhook-worker.php', $manifest, (string) $index], dirname(__DIR__, 3), [
                        'MAIL_WEBHOOK_MYSQL_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1',
                        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
                    ]);
                    $worker->setTimeout(45);
                    $worker->start();
                    $workers[] = $worker;
                }
                $barrier = $mysql->path.'/barrier/'.$scenario;
                $this->waitUntil(fn (): bool => is_file($barrier.'-ready-0') && is_file($barrier.'-ready-1'), $workers);
                $ids = [(int) file_get_contents($barrier.'-ready-0'), (int) file_get_contents($barrier.'-ready-1')];
                $this->assertNotSame($ids[0], $ids[1]);
                file_put_contents($barrier.'-go', 'go');
                $this->waitUntil(fn (): bool => $mysql->waitingConnections($ids) === 2, $workers);
                $this->assertSame(2, $mysql->waitingConnections($ids));
                $connection->commit();
                $results = [];
                foreach ($workers as $index => $worker) {
                    $worker->wait();
                    $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                    $this->assertSame('', $worker->getOutput());
                    $results[] = json_decode(file_get_contents($barrier.'-result-'.$index), true, 512, JSON_THROW_ON_ERROR);
                }
                $events = EmailWebhookEvent::where('message_id', $messageId)->get();
                $this->assertCount($scenario === 'duplicate' ? 1 : 2, $events);
                $this->assertTrue($events->every(fn ($event): bool => $event->processed_at !== null && $event->authenticated_at !== null));
                $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
                $this->assertSame($scenario === 'duplicate' ? '2026-10-17 00:00:00.123456' : '2026-10-17 00:00:00.654321', $log->fresh()->webhook_occurred_at->format('Y-m-d H:i:s.u'));
                $this->assertSame(0, array_sum(array_column($results, 'sent')));
                $this->assertSame(0, array_sum(array_column($results, 'queued')));
                $writes = array_sum(array_column($results, 'projection_writes'));
                $this->assertGreaterThanOrEqual(1, $writes);
                $this->assertLessThanOrEqual($scenario === 'duplicate' ? 1 : 2, $writes);
            }

            config(['services.resend.webhook_secret' => SvixMessageFixture::secret()]);
            $exact = EmailLog::factory()->create(['mailer' => 'resend', 'status' => EmailStatus::Sent, 'message_id' => 'CaseSensitiveMessage']);
            $send = function (string $id, array $payload): ?string {
                $raw = SvixMessageFixture::raw($payload);
                $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw);
                foreach (SvixMessageFixture::headers($raw, $id, (string) now()->timestamp) as $key => $value) {
                    $request->headers->set($key, $value);
                }
                try {
                    app(EmailWebhookService::class)->handle('resend', $request);

                    return null;
                } catch (HttpException $exception) {
                    return (string) $exception->getStatusCode();
                } catch (\Throwable $exception) {
                    return get_class($exception);
                }
            };
            $send('evt-exact-case', ['type' => 'email.delivered', 'created_at' => '2026-10-17T00:00:00Z', 'data' => ['email_id' => 'casesensitivemessage ']]);
            $futureFailure = $send('evt-outside-timestamp-range', ['type' => 'email.delivered', 'created_at' => '2099-01-01T00:00:00.123456Z', 'data' => ['email_id' => $exact->message_id]]);
            $original = EmailWebhookEvent::where('event_id', 'evt-mail002-duplicate')->firstOrFail();
            $collision = $send('EVT-MAIL002-DUPLICATE', json_decode($original->raw_payload, true, 512, JSON_THROW_ON_ERROR));
            $future = EmailWebhookEvent::where('event_id', 'evt-outside-timestamp-range')->first();
            $this->assertSame(['sent', null, true, null, '409'], [
                $exact->fresh()->status->value, $futureFailure, $future !== null, $future?->processed_at, $collision,
            ], 'Native collation and datetime boundaries must preserve exact identity and durable pending evidence.');

            $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_18_003725_add_processing_contract_to_email_webhook_events.php';
            $migration->down();
            $connection->table('email_webhook_events')->where('event_id', 'evt-outside-timestamp-range')->delete();
            $connection->statement("SET time_zone = '+00:00'");
            $connection->statement('ALTER TABLE email_webhook_events MODIFY occurred_at TIMESTAMP(6) NULL');
            $legacy = EmailWebhookEvent::create([
                'provider' => 'resend', 'event_id' => 'evt-legacy-clock', 'payload' => ['type' => 'domain.created'],
                'received_at' => now(), 'occurred_at' => '2026-10-17 06:00:00.123456',
            ]);
            $connection->statement("SET time_zone = '-03:00'");
            $migration->up();
            $this->assertSame('-03:00', $connection->scalar('SELECT @@session.time_zone'));
            $this->assertSame('2026-10-17 06:00:00.123456', $connection->table('email_webhook_events')->where('id', $legacy->id)->value('occurred_at'));
            $this->assertNull($legacy->fresh()->authenticated_at);
            $this->assertNull($legacy->fresh()->payload_hash);
        } finally {
            try {
                foreach ($workers as $worker) {
                    if ($worker->isRunning()) {
                        $worker->stop(2);
                    } $this->assertFalse($worker->isRunning());
                }
                if ($connection?->transactionLevel() > 0) {
                    $connection->rollBack();
                }
                $app?->make('db')->disconnect();
                $app?->flush();
                HandleExceptions::flushState($this);
                RegisterProviders::flushState();
                Facade::clearResolvedInstances();
                Facade::setFacadeApplication(null);
            } finally {
                try {
                    $environment?->cleanUp();
                } finally {
                    $mysql->cleanUp();
                }
            }
        }
        $this->assertDirectoryDoesNotExist($mysql->path);
        $this->assertDirectoryDoesNotExist($environment->path);
    }

    /** @param list<Process> $workers */
    private function waitUntil(callable $condition, array $workers): void
    {
        $deadline = microtime(true) + 20;
        while (! $condition()) {
            foreach ($workers as $worker) {
                $this->assertTrue($worker->isRunning(), $worker->getErrorOutput());
            }
            if (microtime(true) >= $deadline) {
                $this->fail('Owned webhook barrier timed out.');
            }
            usleep(50000);
        }
    }
}
