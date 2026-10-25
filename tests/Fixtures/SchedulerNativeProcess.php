<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process as LaravelProcess;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use RuntimeException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Tests\DisposableMysql;
use Tests\TestEnvironment;
use Throwable;

/** Finite CLI fixture shared only by SchedulerNativeTest and its private Artisan proxy. */
final class SchedulerNativeProcess
{
    /** @param array<string, mixed> $settings */
    public static function configure(Application $app, array $settings): void
    {
        DisposableMysql::verifiedConnection($settings);
        $app['config']->set([
            'cache.default' => 'ops_scheduler',
            'cache.schedule_store' => 'ops_scheduler',
            'cache.prefix' => 'ops002_'.$settings['token'].'_',
            'cache.stores.ops_scheduler' => [
                'driver' => 'database', 'connection' => 'authority_sandbox', 'lock_connection' => 'authority_sandbox',
                'table' => 'ops_scheduler_cache', 'lock_table' => 'ops_scheduler_locks',
                'prefix' => 'ops002_'.$settings['token'].'_', 'lock_lottery' => [0, 100],
            ],
            'app.timezone' => 'UTC', 'app.schedule_timezone' => 'UTC',
        ]);
        $app['cache']->forgetDriver('ops_scheduler');
        $store = $app['cache']->store('ops_scheduler')->getStore();

        if (! $store instanceof DatabaseStore || $store->getConnection() !== $app['db']->connection('authority_sandbox')
            || $store->getLockConnection() !== $app['db']->connection('authority_sandbox')) {
            throw new RuntimeException('Synthetic scheduler cache and locks must share the owned connection.');
        }
    }

    /** @param list<string> $argv */
    public static function run(array $argv): never
    {
        $environment = null;
        $registry = null;
        $record = [];
        $exit = 1;

        try {
            if (getenv('OPS_SCHEDULER_NATIVE_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
                throw new RuntimeException('Explicit owned scheduler profile is required.');
            }

            TestEnvironment::assertSafeEnvironment();
            $phase = getenv('OPS_SCHEDULER_PHASE');
            $worker = getenv('OPS_SCHEDULER_WORKER');
            $command = $argv[1] ?? '';
            $arguments = array_slice($argv, 2);

            if (! in_array($phase, ['work', 'success', 'failure'], true)
                || ! is_string($worker) || preg_match('/\A(?:[01]|manual-(?:success|failure)-[01])\z/', $worker) !== 1
                || ! in_array($command, ['schedule:work', 'schedule:run', 'ops:probe'], true)
                || ($command === 'schedule:work' && ($phase !== 'work' || ! in_array($worker, ['0', '1'], true)))
                || ($command !== 'ops:probe' && ! in_array($arguments, [[], ['--no-interaction']], true))
                || ($command === 'ops:probe' && (count($arguments) !== 1
                    || ! in_array($arguments[0], $phase === 'work' ? ['single', 'long'] : [$phase], true)))) {
                throw new RuntimeException('Scheduler proxy rejected its command arguments before application boot.');
            }

            $path = getenv('OPS_SCHEDULER_MANIFEST');
            $stat = is_string($path) ? lstat($path) : false;

            if (! is_string($path) || $stat === false || is_link($path) || ! is_file($path)
                || $stat['nlink'] !== 1 || $stat['size'] > 16384) {
                throw new RuntimeException('A finite regular scheduler manifest is required.');
            }

            $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($manifest) || count($manifest) !== 8 || ! isset($manifest['settings'], $manifest['proxy'], $manifest['fixture'],
                $manifest['proxy_hash'], $manifest['fixture_hash'], $manifest['created'], $manifest['expires'], $manifest['token'])
                || ! is_array($manifest['settings']) || ! is_int($manifest['created']) || ! is_int($manifest['expires'])
                || $manifest['expires'] <= time() || $manifest['expires'] > $manifest['created'] + 240
                || ! is_string($manifest['token']) || preg_match('/\A[a-f0-9]{32}\z/', $manifest['token']) !== 1) {
                throw new RuntimeException('Scheduler manifest escaped its finite schema or deadline.');
            }

            $settings = $manifest['settings'];
            DisposableMysql::verifiedConnection($settings);
            $project = $settings['path'].'/scheduler-project';

            if ($manifest['token'] !== $settings['token'] || realpath($path) !== realpath($settings['path'].'/scheduler-manifest.json')
                || realpath($argv[0]) !== realpath($project.'/artisan') || realpath($manifest['proxy']) !== realpath($project.'/artisan')
                || realpath($manifest['fixture']) !== realpath(__FILE__)
                || hash_file('sha256', $manifest['fixture']) !== $manifest['fixture_hash']
                || hash_file('sha256', $manifest['proxy']) !== $manifest['proxy_hash']
                || is_link($project) || realpath($project) !== realpath($settings['path']).DIRECTORY_SEPARATOR.'scheduler-project'
                || is_link($project.'/registry') || realpath($project.'/registry') !== realpath($project).DIRECTORY_SEPARATOR.'registry'
                || file_get_contents($project.'/owner') !== $settings['token']) {
                throw new RuntimeException('Scheduler proxy escaped its marked private project.');
            }

            if (defined('ARTISAN_BINARY')) {
                throw new RuntimeException('The private proxy must own Artisan resolution before boot.');
            }

            define('ARTISAN_BINARY', $manifest['proxy']);
            $environment = new TestEnvironment;
            file_put_contents($environment->path.'/authority-owner', $settings['token']);
            $registry = $project.'/registry/'.getmypid().'-'.bin2hex(random_bytes(8)).'.json';
            $record = ['token' => $settings['token'], 'pid' => getmypid(), 'command' => $command, 'phase' => $phase,
                'worker' => $worker, 'environment' => $environment->path, 'started' => microtime(true), 'ended' => null,
                'scenario' => $command === 'ops:probe' ? $arguments[0] : 'schedule', 'argv' => array_slice($argv, 1),
                'stage' => 'validated', 'manifest_hash' => hash_file('sha256', $path)];
            self::receipt($registry, $record);
            $app = DisposableMysql::bootApplication($settings, $environment);
            self::configure($app, $settings);
            $kernel = $app->make(Kernel::class);
            $kernel->all();
            $app->setBasePath($project);
            $kernel->registerCommand(new SchedulerNativeProbe($app, $settings, $phase, $worker, $manifest['expires']));
            $schedule = new Schedule('UTC');
            $schedule->useCache('ops_scheduler');
            $scenario = $phase === 'work' ? 'long' : $phase;

            if ($phase === 'work') {
                $schedule->command('ops:probe single')->everyMinute()->onOneServer()
                    ->createMutexNameUsing('ops002-'.$settings['token'].'-single');
            }

            $event = $schedule->command('ops:probe '.$scenario)->everyMinute()->withoutOverlapping(3)
                ->createMutexNameUsing('ops002-'.$settings['token'].'-'.$scenario);

            if ($phase === 'work') {
                $event->onOneServer();
            }

            $app->instance(Schedule::class, $schedule);
            ScheduleFacade::clearResolvedInstance(Schedule::class);

            if (count($schedule->events()) !== ($phase === 'work' ? 2 : 1)) {
                throw new RuntimeException('The proxy must expose only its finite synthetic scheduler events.');
            }

            $expectedScenarios = $phase === 'work' ? ['single', 'long'] : [$phase];

            foreach ($schedule->events() as $index => $synthetic) {
                if ($synthetic->command !== ConsoleApplication::formatCommandString('ops:probe '.$expectedScenarios[$index])
                    || $synthetic->mutexName() !== 'ops002-'.$settings['token'].'-'.$expectedScenarios[$index]) {
                    throw new RuntimeException('The scheduler event escaped its exact private command and mutex allowlist.');
                }
            }

            $app['events']->listen(CommandStarting::class, function (CommandStarting $starting): void {
                if (! in_array($starting->command, ['schedule:work', 'schedule:run', 'ops:probe'], true)) {
                    throw new RuntimeException('An unrelated console command escaped the finite proxy.');
                }
            });
            $app['events']->listen(ScheduledTaskStarting::class, function (ScheduledTaskStarting $starting) use ($app, $phase, $worker): void {
                $scenario = str_ends_with($starting->task->mutexName(), '-single') ? 'single' : ($phase === 'work' ? 'long' : $phase);
                self::observe($app, $scenario === 'single' ? 'single-event-start' : 'event-start', $phase, $worker, $scenario);
            });
            $app['events']->listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $finished) use ($app, $phase, $worker): void {
                $kind = str_ends_with($finished->task->mutexName(), '-single') ? 'single-event-finish' : 'event-finish';
                $scenario = str_ends_with($finished->task->mutexName(), '-single') ? 'single' : ($phase === 'work' ? 'long' : $phase);
                self::observe($app, $finished->task->skippedBecauseOverlapping ? 'overlap-skipped' : $kind, $phase, $worker, $scenario);
            });
            $app['events']->listen(ScheduledTaskSkipped::class, function (ScheduledTaskSkipped $skipped) use ($app, $phase, $worker, $settings): void {
                if ($phase !== 'work' || $skipped->task->mutexName() !== 'ops002-'.$settings['token'].'-long'
                    || ! $skipped->task->mutex->exists($skipped->task)) {
                    throw new RuntimeException('A synthetic skipped task must retain its actual overlap mutex.');
                }

                self::observe($app, 'overlap-skipped', $phase, $worker, 'long');
            });
            $app['events']->listen(ScheduledTaskFailed::class, function () use ($app, $phase, $worker): void {
                self::observe($app, 'event-failed', $phase, $worker, $phase);
            });
            $record['stage'] = 'booted';
            self::receipt($registry, $record);
            self::observe($app, 'entry-'.$command, $phase, $worker, $record['scenario']);

            if ($command === 'schedule:work') {
                while (! is_file($project.'/start-work')) {
                    if (time() >= $manifest['expires']) {
                        throw new RuntimeException('The scheduler startup barrier exceeded the finite deadline.');
                    }

                    usleep(20000);
                }

                if (is_link($project.'/start-work') || file_get_contents($project.'/start-work') !== $settings['token']) {
                    throw new RuntimeException('The scheduler startup barrier is not owned.');
                }
            }

            $parameters = $command === 'ops:probe' ? ['scenario' => $arguments[0]] : ['--no-interaction' => true];
            $exit = $kernel->call($command, $parameters, new ConsoleOutput);
            self::observe($app, 'exit-'.$command, $phase, $worker, $record['scenario']);
            Mail::assertNothingOutgoing();
            Http::assertNothingSent();

            if (! LaravelProcess::preventingStrayProcesses() || ! Http::preventingStrayRequests()) {
                throw new RuntimeException('The scheduler fixture lost its integration fences.');
            }

            $record['stage'] = 'completed';
        } catch (Throwable $exception) {
            $record['error'] = $exception::class;
            fwrite(STDERR, 'Owned scheduler fixture failed at '.($record['stage'] ?? 'preflight').".\n");
            $exit = 1;
        } finally {
            try {
                $environment?->cleanUp();
            } finally {
                if ($registry !== null) {
                    $record['ended'] = microtime(true);
                    $record['exit'] = $exit;
                    self::receipt($registry, $record);
                }
            }
        }

        exit($exit);
    }

    public static function observe(Application $app, string $kind, string $phase, string $worker, string $scenario): void
    {
        $app['db']->connection('authority_sandbox')->table('ops_scheduler_observations')->insert([
            'kind' => $kind, 'phase' => $phase, 'worker' => $worker, 'scenario' => $scenario, 'pid' => getmypid(),
            'minute' => intdiv(time(), 60), 'observed_at' => microtime(true),
        ]);
    }

    /** @param array<string, mixed> $record */
    private static function receipt(string $path, array $record): void
    {
        if (file_put_contents($path, json_encode($record, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Owned scheduler process registration failed.');
        }
    }
}

final class SchedulerNativeProbe extends Command
{
    protected $signature = 'ops:probe {scenario}';

    /** @param array<string, mixed> $settings */
    public function __construct(private readonly Application $application, private readonly array $settings,
        private readonly string $phase, private readonly string $worker, private readonly int $expires)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $scenario = $this->argument('scenario');

        if (! in_array($scenario, $this->phase === 'work' ? ['single', 'long'] : [$this->phase], true)) {
            throw new RuntimeException('An unrelated scheduler probe scenario was refused.');
        }

        DisposableMysql::verifiedConnection($this->settings);
        SchedulerNativeProcess::observe($this->application, $scenario === 'single' ? 'single-effect-start' : 'effect-start', $this->phase, $this->worker, $scenario);

        if ($scenario === 'long') {
            $deadline = hrtime(true) + 70 * 1000000000;

            while (hrtime(true) < $deadline) {
                if (time() >= $this->expires) {
                    throw new RuntimeException('The finite scheduler probe exceeded its global deadline.');
                }

                usleep(100000);
            }
        }

        SchedulerNativeProcess::observe($this->application, $scenario === 'single' ? 'single-effect-finish' : 'effect-finish', $this->phase, $this->worker, $scenario);

        return $scenario === 'failure' ? self::FAILURE : self::SUCCESS;
    }
}
