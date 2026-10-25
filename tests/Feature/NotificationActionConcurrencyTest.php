<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

class NotificationActionConcurrencyTest extends TestCase
{
    protected function tearDown(): void
    {
        try {
            HandleExceptions::flushState($this);
            RegisterProviders::flushState();
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('actionRaces')]
    public function test_concurrent_stale_action_requests_commit_one_business_effect(string $scenario): void
    {
        if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_NOTIFICATION_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Opt in with AUTHORITY_MYSQL_TESTS=1 and AUTHORITY_NOTIFICATION_MYSQL_TESTS=1 for disposable native notification concurrency.');
        }

        require_once __DIR__.'/NotificationApiTest.php';
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
            $connection->getSchemaBuilder()->create('ntf_action_probes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('recipient_id');
                $table->unsignedInteger('executions')->default(0);
            });
            $actor = User::factory()->create(['email' => 'ntf002@authority.example.test']);
            $probe = NotificationActionProbe::create(['recipient_id' => $actor->id, 'executions' => 0]);
            $notification = AppNotification::create([
                'recipient_id' => $actor->id, 'type' => 'system', 'category' => 'general',
                'title' => 'NTF002 synthetic concurrency', 'body' => 'Owned local fixture',
                'notifiable_type' => NotificationActionProbe::class, 'notifiable_id' => $probe->id,
                'actions' => [['label' => 'Synthetic action', 'type' => 'probe_action']], 'action_status' => 'pending',
            ]);
            $manifest = $mysql->path.'/workers.json';
            $this->assertSame(strlen($bytes = json_encode([
                'settings' => $mysql->settings(), 'scenario' => $scenario,
                'actor_id' => $actor->id, 'notification_id' => $notification->id, 'probe_id' => $probe->id,
            ], JSON_THROW_ON_ERROR)), file_put_contents($manifest, $bytes));

            $connection->beginTransaction();
            $connection->table('app_notifications')->where('id', $notification->id)->lockForUpdate()->first();
            foreach ([0, 1] as $index) {
                $worker = new Process([PHP_BINARY, dirname(__DIR__).'/notification-action-worker.php', $manifest, (string) $index], dirname(__DIR__, 2), [
                    'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_NOTIFICATION_MYSQL_TESTS' => '1',
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
                ]);
                $worker->setTimeout(45);
                $worker->start();
                $workers[] = $worker;
            }
            $this->waitUntil(fn (): bool => is_file($mysql->path.'/barrier/ready-0') && is_file($mysql->path.'/barrier/ready-1'), $workers, 'Both workers must preload the stale pending notification before release.');
            $ids = [(int) file_get_contents($mysql->path.'/barrier/ready-0'), (int) file_get_contents($mysql->path.'/barrier/ready-1')];
            $this->assertGreaterThan(0, $ids[0]);
            $this->assertGreaterThan(0, $ids[1]);
            $this->assertNotSame($ids[0], $ids[1]);

            file_put_contents($mysql->path.'/barrier/go-0', 'go');
            $this->waitUntil(fn (): bool => $mysql->waitingConnections([$ids[0]]) === 1, $workers, 'The first contender must enter the real notification row-lock queue first.');
            file_put_contents($mysql->path.'/barrier/go-1', 'go');
            $this->waitUntil(fn (): bool => $mysql->waitingConnections($ids) === 2, $workers, 'Both requests must be observed waiting on InnoDB row locks.');
            $connection->commit();

            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
            }
            $results = [];
            foreach ([0, 1] as $index) {
                $results[] = json_decode(file_get_contents($mysql->path.'/barrier/result-'.$index.'.json'), true, 512, JSON_THROW_ON_ERROR);
            }
            if ($scenario === 'rollback-first') {
                $this->assertSame([500, 200], array_column($results, 'status'));
                $this->assertSame('controlled-handler-failure', $results[0]['result']);
            } else {
                $statuses = array_column($results, 'status');
                sort($statuses);
                $this->assertSame([200, 409], $statuses);
            }
            foreach ($results as $result) {
                $this->assertTrue($result['stale_pending']);
                $this->assertTrue($result['integrations_isolated']);
            }
            $this->assertSame(1, $probe->fresh()->executions);
            $this->assertSame('executed', $notification->fresh()->action_status);
            $this->assertSame(1, AppNotification::query()->count());
            $this->assertSame(1, NotificationActionProbe::query()->count());
        } finally {
            try {
                foreach ($workers as $worker) {
                    if ($worker->isRunning()) {
                        $worker->stop(2);
                    }
                }
                foreach ($workers as $worker) {
                    $this->assertFalse($worker->isRunning());
                }
                if ($connection?->transactionLevel() > 0) {
                    $connection->rollBack();
                }
                $app?->make('db')->disconnect();
                $app?->flush();
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

    /** @return array<string, array{string}> */
    public static function actionRaces(): array
    {
        return ['two successful contenders' => ['success'], 'first transaction rolls back' => ['rollback-first']];
    }

    /** @param list<Process> $workers */
    private function waitUntil(callable $condition, array $workers, string $failureMessage): void
    {
        $deadline = microtime(true) + 20;
        while (! $condition()) {
            foreach ($workers as $worker) {
                if (! $worker->isRunning()) {
                    $this->fail($failureMessage.' '.$worker->getErrorOutput());
                }
            }
            if (microtime(true) >= $deadline) {
                $this->fail($failureMessage);
            }
            usleep(20000);
        }
        $this->assertTrue($condition(), $failureMessage);
    }
}
