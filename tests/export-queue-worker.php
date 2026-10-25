<?php

use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\WorkerIdle;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process as LaravelProcess;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\NullOutput;
use Tests\DisposableMysql;
use Tests\Feature\Export\ExportQueueFixture;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/Feature/Export/ExportQueueLifecycleTest.php';

$environment = null;
$app = null;
$exitCode = 0;

function waitExportQueueBarrier(string $path, int $seconds = 25): void
{
    $deadline = hrtime(true) + $seconds * 1e9;
    while (! is_file($path)) {
        if (hrtime(true) > $deadline) {
            throw new RuntimeException('Owned export queue barrier timed out.');
        }
        usleep(10000);
    }
}

try {
    if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_EXPORT_QUEUE_TESTS') !== '1'
        || ! isset($argv[1], $argv[2]) || ! in_array($argv[2], ['0', '1'], true)) {
        throw new RuntimeException('Explicit disposable export queue invocation required.');
    }
    TestEnvironment::assertSafeEnvironment();
    $stat = lstat($argv[1]);
    if ($stat === false || ! is_file($argv[1]) || is_link($argv[1]) || $stat['nlink'] !== 1 || $stat['size'] > 16384) {
        throw new RuntimeException('Invalid finite export queue manifest.');
    }
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $scenarios = ['long-restart', 'kill-published', 'delete-export', 'delete-owner', 'notice-failure', 'finalization-race', 'replacement'];
    $uuidPattern = '[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}';
    if (! is_array($manifest) || count($manifest) !== 6
        || ! isset($manifest['settings'], $manifest['scenario'], $manifest['actor_id'], $manifest['export_id'], $manifest['export_uuid'], $manifest['client_digest'])
        || ! is_array($manifest['settings']) || ! in_array($manifest['scenario'], $scenarios, true)
        || ! is_int($manifest['actor_id']) || $manifest['actor_id'] < 1 || ! is_int($manifest['export_id']) || $manifest['export_id'] < 1
        || ! is_string($manifest['export_uuid']) || preg_match('~\A'.$uuidPattern.'\z~', $manifest['export_uuid']) !== 1
        || ! is_string($manifest['client_digest']) || preg_match('/\A[a-f0-9]{64}\z/', $manifest['client_digest']) !== 1) {
        throw new RuntimeException('Export worker profile escaped its finite schema.');
    }
    $settings = $manifest['settings'];
    DisposableMysql::verifiedConnection($settings);
    $scenario = $manifest['scenario'];
    $index = (int) $argv[2];
    $expectedManifest = $settings['path'].($scenario === 'replacement' ? '/export-replacement.json' : '/export-workers.json');
    $root = $settings['path'].'/export-private';
    $owner = $root.'/owner';
    $ownerStat = is_file($owner) ? lstat($owner) : false;
    if (realpath($argv[1]) !== realpath($expectedManifest)
        || realpath($settings['path'].'/barrier') !== realpath($settings['path']).DIRECTORY_SEPARATOR.'barrier'
        || realpath($root) !== realpath($settings['path']).DIRECTORY_SEPARATOR.'export-private'
        || is_link($root) || is_link($settings['path'].'/barrier') || $ownerStat === false || is_link($owner)
        || $ownerStat['nlink'] !== 1 || $ownerStat['size'] !== 32 || file_get_contents($owner) !== $settings['token']
        || ($scenario === 'replacement' && $index !== 0) || (str_starts_with($scenario, 'delete-') && $index !== 0)) {
        throw new RuntimeException('Export worker escaped its marked owned runtime.');
    }
    $environment = new TestEnvironment;
    file_put_contents($environment->path.'/authority-owner', $settings['token']);
    ExportQueueFixture::signal($settings['path'].'/barrier/environment-'.$index, $environment->path);
    $app = DisposableMysql::bootApplication($settings, $environment);
    ExportQueueFixture::configure($app, $settings);
    $actor = User::query()->findOrFail($manifest['actor_id']);
    $export = Export::query()->findOrFail($manifest['export_id']);
    if ($actor->email !== 'ops001@authority.example.test' || ! $actor->hasVerifiedEmail() || $actor->must_change_password
        || $export->uuid !== $manifest['export_uuid'] || $export->user_id !== $actor->id || $export->model_class !== Client::class
        || $export->format !== 'csv' || $export->columns !== ['name', 'email'] || $export->search !== 'OPS001'
        || ! in_array($export->status, [Export::STATUS_PENDING, Export::STATUS_PROCESSING], true)
        || Client::query()->count() !== 1207 || ExportQueueFixture::clientDigest() !== $manifest['client_digest']) {
        throw new RuntimeException('Export worker requires its fixed synthetic actor, source and export.');
    }
    $barrier = $settings['path'].'/barrier';
    $suffix = $scenario === 'replacement' ? '-replacement' : '';
    $observer = new class
    {
        public int $jobs = 0;

        public int $loaded = 0;

        public bool $held = false;

        public bool $idle = false;

        public bool $late = false;
    };
    $started = hrtime(true);
    $app['events']->listen(JobProcessing::class, function (JobProcessing $event) use ($observer, $settings, $barrier, $index, $suffix): void {
        $payload = $event->job->payload();
        if ($event->connectionName !== 'database' || $event->job->getQueue() !== ExportQueueFixture::queue($settings)
            || ($payload['data']['commandName'] ?? null) !== ProcessExport::class || ++$observer->jobs > 2) {
            throw new RuntimeException('The finite worker encountered an unrelated queued command.');
        }
        ExportQueueFixture::signal($barrier.'/job-'.$index.'-'.$observer->jobs.$suffix.'.json', json_encode([
            'id' => $event->job->getJobId(), 'attempts' => $event->job->attempts(),
        ], JSON_THROW_ON_ERROR));
    });
    $app['events']->listen(WorkerIdle::class, function () use ($observer, $barrier, $index, $started, $suffix): void {
        if (! $observer->idle) {
            $observer->idle = true;
            ExportQueueFixture::signal($barrier.'/idle-'.$index.$suffix, 'idle');
        }
        if (! $observer->late && (hrtime(true) - $started) / 1e9 >= 91) {
            $observer->late = true;
            ExportQueueFixture::signal($barrier.'/late-idle-'.$index.$suffix, 'idle beyond the old lease');
        }
    });
    $app['events']->listen(WorkerStopping::class, function (WorkerStopping $event) use ($barrier, $index, $suffix): void {
        Mail::assertNothingOutgoing();
        Http::assertNothingSent();
        if (! Http::preventingStrayRequests() || ! LaravelProcess::preventingStrayProcesses()) {
            throw new RuntimeException('Export worker external guards were lost.');
        }
        ExportQueueFixture::signal($barrier.'/stopped-'.$index.$suffix.'.json', json_encode([
            'status' => $event->status, 'reason' => $event->reason?->value, 'isolated' => true,
        ], JSON_THROW_ON_ERROR));
    });
    if ($index === 0 && in_array($scenario, ['long-restart', 'delete-export', 'delete-owner', 'notice-failure'], true)) {
        $app['events']->listen('eloquent.retrieved: '.Client::class, function () use ($observer, $manifest, $barrier, $scenario): void {
            if (! $observer->held && ++$observer->loaded === 501) {
                $current = Export::query()->findOrFail($manifest['export_id']);
                if ($current->processed_records !== 500 || $current->getConnection()->transactionLevel() !== 0) {
                    throw new RuntimeException('The progress barrier must run between committed batches without holding a row lock.');
                }
                $observer->held = true;
                ExportQueueFixture::signal($barrier.'/held-0.json', json_encode(['processed' => $current->processed_records], JSON_THROW_ON_ERROR));
                waitExportQueueBarrier($barrier.'/release-0', $scenario === 'long-restart' ? 150 : 25);
            }
        });
    }
    if ($scenario === 'finalization-race' && $index === 0) {
        $app['events']->listen('eloquent.updated: '.Export::class, function (Export $updated) use ($manifest, $app): void {
            if ($updated->id === $manifest['export_id'] && $updated->status === Export::STATUS_PROCESSING && $updated->wasChanged('total_records')) {
                $app['db']->connection()->table('exports')->where('id', $updated->id)->update(['processing_expires_at' => now()->addSeconds(3)]);
            }
        });
        $app['events']->listen('eloquent.creating: '.AppNotification::class, function (AppNotification $notice) use ($manifest, $barrier, $app): void {
            $current = Export::query()->findOrFail($manifest['export_id']);
            if ($notice->notifiable_type !== Export::class || $notice->notifiable_id !== $manifest['export_id']
                || $app['db']->connection()->transactionLevel() < 1 || $current->status !== Export::STATUS_COMPLETED) {
                throw new RuntimeException('The finalization fixture requires the real completion transaction.');
            }
            $absolute = Storage::disk('local')->path($current->file_path);
            ExportQueueFixture::signal($barrier.'/held-0.json', json_encode(['inside_transaction' => true, 'artifact' => $current->file_path,
                'digest' => hash_file('sha256', $absolute), 'mtime' => filemtime($absolute)], JSON_THROW_ON_ERROR));
            waitExportQueueBarrier($barrier.'/release-0');
        });
    }
    if ($scenario === 'notice-failure' && $index === 0) {
        $app['events']->listen('eloquent.creating: '.AppNotification::class, function (AppNotification $notification) use ($manifest): void {
            if ($notification->notifiable_type === Export::class && $notification->notifiable_id === $manifest['export_id']) {
                throw new RuntimeException('OPS001 controlled notice persistence failure.');
            }
            throw new RuntimeException('An unrelated notification escaped the export fixture.');
        });
    }
    if ($scenario === 'kill-published' && $index === 0) {
        $disk = Storage::disk('local');
        Storage::set('local', new class($disk, $barrier, $manifest['export_uuid'], $uuidPattern) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $base, private readonly string $barrier, private readonly string $uuid, private readonly string $uuidPattern)
            {
                parent::__construct($base->getDriver(), $base->getAdapter(), $base->getConfig());
            }

            public function put($path, $contents, $options = []): bool
            {
                if (preg_match('~\Aexports/'.preg_quote($this->uuid, '~').'/'.$this->uuidPattern.'\.csv\z~', $path) !== 1 || ! is_resource($contents)) {
                    throw new RuntimeException('Export publication escaped its owned UUID and attempt.');
                }
                $root = realpath($this->getConfig()['root']);
                foreach (['', 'exports', 'exports/'.$this->uuid] as $relative) {
                    $directory = $relative === '' ? $root : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    if (is_link($directory) || (file_exists($directory) && realpath($directory) !== $directory)) {
                        throw new RuntimeException('Export publication ancestor escaped the marked private root.');
                    }
                }
                if (file_exists($this->path($path)) || is_link($this->path($path))) {
                    throw new RuntimeException('The interrupted attempt must publish a new private artifact.');
                }
                $metadata = stream_get_meta_data($contents);
                $temporary = $metadata['uri'] ?? null;
                $attempt = pathinfo($path, PATHINFO_FILENAME);
                $stat = is_string($temporary) && is_file($temporary) ? lstat($temporary) : false;
                if ($stat === false || is_link($temporary) || $stat['nlink'] !== 1
                    || realpath(dirname($temporary)) !== realpath(sys_get_temp_dir()) || basename($temporary) !== 'export-'.$this->uuid.'-'.$attempt.'.csv') {
                    throw new RuntimeException('Export publication requires its exclusive owned temporary stream.');
                }
                $written = parent::put($path, $contents, $options);
                if (! $written) {
                    throw new RuntimeException('The real publication must succeed before interruption.');
                }
                ExportQueueFixture::signal($this->barrier.'/held-0.json', json_encode(['artifact' => $path, 'temporary' => $temporary], JSON_THROW_ON_ERROR));
                waitExportQueueBarrier($this->barrier.'/release-0');

                return $written;
            }
        });
    }
    if ($scenario === 'kill-published' && $index === 1) {
        $app['events']->listen(JobProcessed::class, function () use ($observer, $barrier): void {
            if ($observer->jobs === 1) {
                ExportQueueFixture::signal($barrier.'/recovered-1', 'completed');
                waitExportQueueBarrier($barrier.'/replay-old');
            }
        });
    }
    $long = $scenario === 'long-restart';
    ExportQueueFixture::signal($barrier.'/connection-'.$index.$suffix, (string) $app['db']->connection()->scalar('SELECT CONNECTION_ID()'));
    $exitCode = $app->make(Kernel::class)->call('queue:work', [
        'connection' => 'database', '--queue' => ExportQueueFixture::queue($settings), '--name' => 'ops001-'.$index,
        '--sleep' => 1, '--timeout' => 900, '--tries' => 3, '--max-time' => $long ? 150 : 35,
        '--max-jobs' => $long ? 0 : ($scenario === 'kill-published' && $index === 1 ? 2 : 1),
        '--memory' => 256, '--no-interaction' => true, '--quiet' => true,
    ], new NullOutput);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Export queue worker failed: '.$exception::class.' '.$exception->getMessage()."\n");
    $exitCode = 1;
} finally {
    try {
        $app?->make('db')->disconnect();
        $app?->flush();
    } finally {
        $environment?->cleanUp();
    }
}

exit($exitCode);
