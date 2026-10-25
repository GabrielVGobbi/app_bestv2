<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\Fixtures\SchedulerNativeProcess;
use Tests\TestEnvironment;

class SchedulerNativeTest extends TestCase
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

    public function test_two_real_schedule_workers_share_the_database_mutex_and_release_it_after_success_and_failure(): void
    {
        if (getenv('OPS_SCHEDULER_NATIVE_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Explicit owned scheduler and MySQL opt-ins are required.');
        }

        $this->assertSame('Windows', PHP_OS_FAMILY, 'This profile proves Windows schedule:work only.');
        TestEnvironment::assertSafeEnvironment();
        $binary = getenv('AUTHORITY_MYSQLD_BINARY');
        $this->assertIsString($binary);
        $this->assertFileExists($binary);
        $started = microtime(true);
        $expires = (int) floor($started) + 240;
        $mysql = new DisposableMysql;
        $environment = null;
        $processes = [];
        $project = null;
        $manifest = null;
        $pdo = null;

        try {
            $mysql->start();
            $settings = $mysql->settings();
            $pdo = DisposableMysql::verifiedConnection($settings);
            $environment = new TestEnvironment;
            $app = DisposableMysql::bootApplication($settings, $environment);
            $this->assertSame('authority_sandbox', $app['db']->getDefaultConnection());
            $this->assertSame($settings['token'], $app['db']->connection()->table('isolation_owner')->value('token'));
            $pdo->exec('CREATE TABLE ops_scheduler_cache (`key` VARCHAR(255) PRIMARY KEY, `value` MEDIUMTEXT NOT NULL, expiration INT NOT NULL) ENGINE=InnoDB');
            $pdo->exec('CREATE TABLE ops_scheduler_locks (`key` VARCHAR(255) PRIMARY KEY, owner VARCHAR(255) NOT NULL, expiration INT NOT NULL) ENGINE=InnoDB');
            $pdo->exec('CREATE TABLE ops_scheduler_observations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kind VARCHAR(80) NOT NULL, phase VARCHAR(20) NOT NULL, worker VARCHAR(40) NOT NULL, scenario VARCHAR(20) NOT NULL,
                pid INT NOT NULL, minute BIGINT NOT NULL, observed_at DOUBLE NOT NULL) ENGINE=InnoDB');
            SchedulerNativeProcess::configure($app, $settings);
            $project = $mysql->path.'/scheduler-project';
            $this->assertDirectoryDoesNotExist($project);
            (new Filesystem)->ensureDirectoryExists($project.'/registry');
            file_put_contents($project.'/owner', $settings['token']);
            $source = dirname(__DIR__, 3);
            $fixture = $source.'/tests/Fixtures/SchedulerNativeProcess.php';
            $proxy = $project.'/artisan';
            $code = "<?php\ndeclare(strict_types=1);\nrequire ".var_export($source.'/vendor/autoload.php', true).";\nrequire_once "
                .var_export($fixture, true).";\nTests\\Fixtures\\SchedulerNativeProcess::run(array_values(\$_SERVER['argv']));\n";
            $this->assertNotFalse(file_put_contents($proxy, $code));
            $manifest = $mysql->path.'/scheduler-manifest.json';
            $this->assertNotFalse(file_put_contents($manifest, json_encode([
                'settings' => $settings, 'proxy' => $proxy, 'fixture' => $fixture,
                'proxy_hash' => hash_file('sha256', $proxy), 'fixture_hash' => hash_file('sha256', $fixture),
                'created' => (int) floor($started), 'expires' => $expires, 'token' => $settings['token'],
            ], JSON_THROW_ON_ERROR), LOCK_EX));
            $processes[] = $this->startProxy($proxy, $manifest, 'work', '0', 'schedule:work', $expires);
            $processes[] = $this->startProxy($proxy, $manifest, 'work', '1', 'schedule:work', $expires);
            $this->waitFor(fn (): bool => $this->observationsCount($pdo, 'work', 'entry-schedule:work') === 2, $processes, $expires,
                'Both actual schedule:work processes must boot through the guarded proxy.');
            $this->waitFor(fn (): bool => time() % 60 >= 2 && time() % 60 < 50, $processes, $expires,
                'Release both prepared workers away from a minute-boundary startup race.');
            $this->assertSame(32, file_put_contents($project.'/start-work', $settings['token'], LOCK_EX));
            $this->waitFor(fn (): bool => $this->observationsCount($pdo, 'work', 'effect-start') >= 1, $processes, $expires,
                'The first minute must start the real long-running probe.');
            $firstMinute = (int) $pdo->query("SELECT minute FROM ops_scheduler_observations WHERE kind='effect-start' AND phase='work' ORDER BY id LIMIT 1")->fetchColumn();
            $this->assertSame(1, $this->observationsCount($pdo, 'work', 'effect-start'));
            $this->waitFor(fn (): bool => (int) $pdo->query("SELECT COUNT(*) FROM ops_scheduler_observations WHERE phase='work' AND kind='overlap-skipped' AND minute=".($firstMinute + 1))->fetchColumn() === 2, $processes, $expires,
                'The second real minute must attempt the still-running event and skip its overlap.');
            $this->waitFor(fn (): bool => $this->observationsCount($pdo, 'work', 'effect-finish') === 1
                && $this->observationsCount($pdo, 'work', 'exit-schedule:run') >= 4, $processes, $expires,
                'The first effect and both workers\' two real scheduler children must finish.');
            $this->waitFor(fn (): bool => $this->finiteReceiptsComplete($project, 'work'), $processes, $expires,
                'Finite children must finish their own cleanup before stopping the schedule workers.');

            foreach ($processes as $process) {
                $process->stop(1);
                $this->assertFalse($process->isRunning());
            }

            $this->assertSame(1, $this->observationsCount($pdo, 'work', 'effect-start'));
            $this->assertSame(1, $this->observationsCount($pdo, 'work', 'effect-finish'));
            $this->assertSame(1, $this->observationsCount($pdo, 'work', 'event-start'), 'The long event starts once while the next tick is blocked.');
            $this->assertSame(2, $this->observationsCount($pdo, 'work', 'single-event-start'));
            $this->assertSame(2, $this->observationsCount($pdo, 'work', 'single-effect-start'));
            $this->assertSame(2, $this->observationsCount($pdo, 'work', 'single-effect-finish'));
            $singles = $pdo->query("SELECT minute,COUNT(*) AS effects FROM ops_scheduler_observations WHERE phase='work' AND kind='single-effect-start' GROUP BY minute ORDER BY minute")->fetchAll(PDO::FETCH_ASSOC);
            $this->assertSame([$firstMinute, $firstMinute + 1], array_map(fn (array $row): int => (int) $row['minute'], $singles));
            $this->assertSame([1, 1], array_map(fn (array $row): int => (int) $row['effects'], $singles), 'onOneServer alone admits one short effect per actual minute.');
            $runs = $pdo->query("SELECT minute,worker FROM ops_scheduler_observations WHERE phase='work' AND kind='entry-schedule:run' ORDER BY minute,worker")->fetchAll(PDO::FETCH_ASSOC);
            $this->assertCount(4, $runs);
            $this->assertSame([$firstMinute, $firstMinute, $firstMinute + 1, $firstMinute + 1], array_map(fn (array $row): int => (int) $row['minute'], $runs));
            $this->assertSame(['0', '1', '0', '1'], array_column($runs, 'worker'));
            $duration = (float) $pdo->query("SELECT MAX(observed_at)-MIN(observed_at) FROM ops_scheduler_observations WHERE phase='work' AND kind IN ('effect-start','effect-finish')")->fetchColumn();
            $this->assertGreaterThanOrEqual(70, $duration);
            $this->assertLessThan(90, $duration);
            $this->assertMutexReleased($pdo, $settings['token'], 'long');

            foreach (['success', 'failure'] as $phase) {
                foreach ([0, 1] as $attempt) {
                    $manual = $this->startProxy($proxy, $manifest, $phase, 'manual-'.$phase.'-'.$attempt, 'schedule:run', $expires);
                    $processes[] = $manual;
                    $this->waitFor(fn (): bool => ! $manual->isRunning(), [], $expires, 'A finite release check must finish.');
                    $this->assertSame(0, $manual->getExitCode(), 'schedule:run reports failed task status internally. '.$manual->getErrorOutput());
                    $this->assertSame($attempt + 1, $this->observationsCount($pdo, $phase, 'effect-start'));
                    $this->assertSame($attempt + 1, $this->observationsCount($pdo, $phase, 'effect-finish'));
                    $this->assertMutexReleased($pdo, $settings['token'], $phase);
                }
            }

            $this->assertSame(0, $this->observationsCount($pdo, 'success', 'overlap-skipped'));
            $this->assertSame(0, $this->observationsCount($pdo, 'failure', 'overlap-skipped'));
            $this->assertSame(0, $this->observationsCount($pdo, 'success', 'event-failed'));
            $this->assertSame(2, $this->observationsCount($pdo, 'failure', 'event-failed'));
            DisposableMysql::verifiedConnection($settings);
            Mail::assertNothingOutgoing();
            Http::assertNothingSent();
            $this->assertLessThanOrEqual(240, microtime(true) - $started);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }

                $this->assertFalse($process->isRunning());
            }

            if ($project !== null && $manifest !== null && $pdo !== null) {
                $this->verifyChildrenStopped($project, $mysql->settings()['token'], $manifest, $processes);
                $proof = sys_get_temp_dir().'/ops002-scheduler-proof-'.$mysql->settings()['token'].'.json';
                $this->assertFileDoesNotExist($proof);
                $this->assertNotFalse(file_put_contents($proof, json_encode([
                    'schema' => 'OPS002_NATIVE_SCHEDULER_PROOF_V1',
                    'owner' => $mysql->settings()['token'],
                    'database_backend' => 'owned MySQL / database cache',
                    'observations' => $pdo->query('SELECT * FROM ops_scheduler_observations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
                    'children' => array_map(fn (string $path): array => $this->readReceipt($path), glob($project.'/registry/*.json')),
                    'registered_children_remaining' => 0,
                ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX));
            }

            $environment?->cleanUp();
            $mysql->cleanUp();
        }
    }

    private function startProxy(string $proxy, string $manifest, string $phase, string $worker, string $command, int $expires): Process
    {
        if (time() >= $expires) {
            throw new RuntimeException('Owned scheduler deadline expired before process start.');
        }

        $process = new Process([PHP_BINARY, $proxy, $command, '--no-interaction'], dirname($proxy), [
            'OPS_SCHEDULER_NATIVE_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'OPS_SCHEDULER_MANIFEST' => $manifest,
            'OPS_SCHEDULER_PHASE' => $phase, 'OPS_SCHEDULER_WORKER' => $worker,
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $process->setOptions(['create_new_console' => false, 'create_process_group' => true]);
        $process->setTimeout(max(1, $expires - time()));
        $process->start();

        return $process;
    }

    /** @param list<Process> $workers */
    private function waitFor(callable $condition, array $workers, int $expires, string $message): void
    {
        while (! $condition()) {
            foreach ($workers as $worker) {
                if (! $worker->isRunning()) {
                    $this->fail($message.' '.$worker->getErrorOutput());
                }
            }

            if (time() >= $expires) {
                $this->fail($message.' The 240-second profile deadline expired.');
            }

            usleep(50000);
        }

        $this->assertTrue($condition(), $message);
    }

    private function observationsCount(PDO $pdo, string $phase, string $kind): int
    {
        $query = $pdo->prepare('SELECT COUNT(*) FROM ops_scheduler_observations WHERE phase=? AND kind=?');
        $query->execute([$phase, $kind]);

        return (int) $query->fetchColumn();
    }

    private function assertMutexReleased(PDO $pdo, string $token, string $scenario): void
    {
        $query = $pdo->prepare('SELECT COUNT(*) FROM ops_scheduler_locks WHERE `key`=?');
        $query->execute(['ops002_'.$token.'_ops002-'.$token.'-'.$scenario]);
        $this->assertSame(0, (int) $query->fetchColumn(), 'The overlap lock must be removed after the command exits.');
    }

    private function finiteReceiptsComplete(string $project, string $phase): bool
    {
        $found = 0;

        foreach (glob($project.'/registry/*.json') as $path) {
            $record = $this->readReceipt($path);

            if ($record['phase'] !== $phase || $record['command'] === 'schedule:work') {
                continue;
            }

            $found++;

            if ($record['ended'] === null || $record['stage'] !== 'completed') {
                return false;
            }
        }

        return $found >= 7;
    }

    /** @return array<string, mixed> */
    private function readReceipt(string $path): array
    {
        $stream = fopen($path, 'rb');
        $this->assertIsResource($stream);

        try {
            $this->assertTrue(flock($stream, LOCK_SH));

            return json_decode(stream_get_contents($stream), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            fclose($stream);
        }
    }

    /** @param list<Process> $processes */
    private function verifyChildrenStopped(string $project, string $token, string $manifest, array $processes): void
    {
        $this->assertSame($token, file_get_contents($project.'/owner'));
        $this->assertFalse(is_link($project));
        foreach (glob($project.'/registry/*.json') as $path) {
            $record = $this->readReceipt($path);

            $this->assertSame($token, $record['token']);
            $this->assertSame(hash_file('sha256', $manifest), $record['manifest_hash']);
            $this->assertIsInt($record['pid']);
            $this->assertGreaterThan(0, $record['pid']);
            $this->assertContains($record['command'], ['schedule:work', 'schedule:run', 'ops:probe']);
            $this->assertContains($record['scenario'], ['schedule', 'single', 'long', 'success', 'failure']);
            $this->assertFalse($this->pidIsRunning($record['pid']), 'A registered scheduler child must stop before fixture cleanup.');

            if ($record['command'] !== 'schedule:work') {
                $this->assertNotNull($record['ended'], 'Finite scheduler children must complete their cleanup receipt.');
                $this->assertSame('completed', $record['stage']);
                $this->assertSame($record['command'] === 'ops:probe' && $record['phase'] === 'failure' ? 1 : 0, $record['exit']);
            }

            $environment = $record['environment'];

            if (file_exists($environment)) {
                $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($environment)));
                $this->assertMatchesRegularExpression('/\Astart-kit-tests-[a-f0-9]{32}\z/', basename($environment));
                $this->assertFalse(is_link($environment));
                $this->assertSame($token, file_get_contents($environment.'/authority-owner'));
                (new Filesystem)->deleteDirectory($environment);
                $this->assertDirectoryDoesNotExist($environment);
            }
        }
    }

    private function pidIsRunning(int $pid): bool
    {
        $systemRoot = getenv('SYSTEMROOT');

        if (! is_string($systemRoot) || ! is_file($systemRoot.'/System32/tasklist.exe')) {
            throw new RuntimeException('A trusted Windows process inventory command is required.');
        }

        $query = new Process([$systemRoot.'/System32/tasklist.exe', '/FI', 'PID eq '.$pid, '/FO', 'CSV', '/NH']);
        $query->setOptions(['create_new_console' => false]);
        $query->setTimeout(5);
        $query->run();
        $this->assertTrue($query->isSuccessful());

        foreach (preg_split('/\r?\n/', trim($query->getOutput())) as $line) {
            $fields = str_getcsv($line, ',', '"', '');

            if (isset($fields[1]) && ctype_digit($fields[1]) && (int) $fields[1] === $pid) {
                return true;
            }
        }

        return false;
    }
}
