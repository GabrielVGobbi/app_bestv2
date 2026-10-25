<?php

namespace Tests\Feature\Email;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

class EmailDeliveryConcurrencyTest extends TestCase
{
    public function test_real_innodb_races_create_retry_and_claim_only_once(): void
    {
        if (getenv('MAIL_DELIVERY_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Opt in with MAIL_DELIVERY_MYSQL_TESTS=1 and AUTHORITY_MYSQL_TESTS=1 for owned MySQL.');
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

            $columns = $connection->getSchemaBuilder()->getColumns('email_logs');
            $scheduledColumn = collect($columns)->firstWhere('name', 'scheduled_at');
            $this->assertSame('datetime(6)', $scheduledColumn['type']);
            $directory = $environment->path.'/schedule-views';
            mkdir($directory);
            file_put_contents($directory.'/delivery.blade.php', '<p>Owned schedule fixture</p>');
            $app['view']->addNamespace('mail003-native', $directory);
            $due = CarbonImmutable::parse('2040-10-18 12:00:00.900000', 'UTC');
            $connection->statement("SET SESSION time_zone = '+03:00'");
            $scheduled = EmailLog::factory()->queued()->create([
                'scheduled_at' => $due, 'reply_to' => 'native@example.test',
                'dispatch_token' => (string) Str::uuid(), 'attempts' => 0,
                'template' => 'mail003-native::delivery', 'mailer' => 'array',
                'sent_at' => null, 'message_id' => null,
            ]);
            $connection->statement("SET SESSION time_zone = '+00:00'");
            $this->assertTrue($due->equalTo($scheduled->fresh()->scheduled_at));
            $this->assertSame('2040-10-18 12:00:00.900000', $scheduled->fresh()->getRawOriginal('scheduled_at'));
            $due = CarbonImmutable::parse('2026-10-18 12:00:00.900000', 'UTC');
            $scheduled->update(['scheduled_at' => $due]);
            $app['config']->set(['queue.default' => 'scheduled', 'queue.connections.scheduled' => ['driver' => 'database']]);
            Carbon::setTestNow($due->subMicrosecond());
            try {
                $job = unserialize(serialize(new ProcessEmailJob($scheduled)))->withFakeQueueInteractions();
                $job->handle($app->make(EmailProviderMessageIdResolver::class));
                $job->assertReleased(1);
                $this->assertSame(0, $scheduled->fresh()->attempts);
                Mail::assertNothingOutgoing();
                Carbon::setTestNow($due);
                $job->handle($app->make(EmailProviderMessageIdResolver::class));
                $this->assertSame(EmailStatus::Sent, $scheduled->fresh()->status);
                $this->assertSame(1, $scheduled->fresh()->attempts);
                Mail::assertSentCount(1);
            } finally {
                Carbon::setTestNow();
                Mail::fake();
                $scheduled->delete();
            }

            foreach (['create', 'retry', 'job'] as $scenario) {
                $workers = [];
                $key = 'mail001-'.$scenario;
                $log = $scenario === 'create' ? null : EmailLog::factory()->create([
                    'status' => $scenario === 'retry' ? EmailStatus::Failed : EmailStatus::Queued,
                    'retry_safe' => $scenario === 'retry', 'dispatch_token' => (string) Str::uuid(),
                    'idempotency_key' => $key, 'attempts' => $scenario === 'job' ? 255 : 0, 'message_id' => null, 'sent_at' => null,
                    'template' => 'mail-race::delivery', 'variables' => null, 'mailer' => 'array',
                ]);
                $manifest = $mysql->path.'/workers.json';
                file_put_contents($manifest, json_encode(['settings' => $mysql->settings(), 'scenario' => $scenario, 'key' => $key, 'log_id' => $log?->id], JSON_THROW_ON_ERROR));
                file_put_contents($mysql->path.'/delivery.blade.php', '<p>Owned delivery fixture</p>');
                $connection->beginTransaction();
                $connection->table('email_logs')->where('idempotency_key', $key)->lockForUpdate()->first();

                foreach ([0, 1] as $index) {
                    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/email-delivery-worker.php', $manifest, (string) $index], dirname(__DIR__, 3), [
                        'MAIL_DELIVERY_MYSQL_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1',
                        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
                    ]);
                    $process->setTimeout(45);
                    $process->start();
                    $workers[] = $process;
                }

                $barrier = $mysql->path.'/barrier/'.$scenario;
                $this->waitUntil(fn (): bool => is_file($barrier.'-ready-0') && is_file($barrier.'-ready-1'), $workers);
                $ids = [(int) file_get_contents($barrier.'-ready-0'), (int) file_get_contents($barrier.'-ready-1')];
                $this->assertNotSame($ids[0], $ids[1]);
                file_put_contents($barrier.'-go', 'go');
                $this->waitUntil(fn (): bool => $mysql->waitingConnections($ids) === 2, $workers);
                $this->assertSame(2, $mysql->waitingConnections($ids), 'Both independent connections must wait on real InnoDB locks.');
                $connection->commit();

                $results = [];
                foreach ($workers as $index => $worker) {
                    $worker->wait();
                    $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                    $this->assertSame('', $worker->getOutput());
                    $results[] = json_decode(file_get_contents($barrier.'-result-'.$index), true, 512, JSON_THROW_ON_ERROR);
                }
                $log = EmailLog::where('idempotency_key', $key)->firstOrFail();
                $this->assertSame(1, EmailLog::where('idempotency_key', $key)->count());
                $this->assertSame($scenario === 'job' ? EmailStatus::Sent : EmailStatus::Queued, $log->status);
                $this->assertSame($scenario === 'job' ? 256 : 0, $log->attempts);
                $this->assertSame($scenario === 'job' ? 0 : 1, array_sum(array_column($results, 'queued')));
                $this->assertSame($scenario === 'job' ? 1 : 0, array_sum(array_column($results, 'sent')));
                $this->assertTrue(Str::isUuid($log->dispatch_token));
            }
        } finally {
            try {
                foreach ($workers as $worker) {
                    if ($worker->isRunning()) {
                        $worker->stop(2);
                    }
                    $this->assertFalse($worker->isRunning());
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
                $this->assertTrue($worker->isRunning(), 'Worker exited before barrier: '.$worker->getErrorOutput());
            }
            if (microtime(true) >= $deadline) {
                $this->fail('Owned concurrency barrier timed out.');
            }
            usleep(50000);
        }
    }
}
