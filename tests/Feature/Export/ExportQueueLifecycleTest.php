<?php

namespace Tests\Feature\Export;

use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Permissions\Models\Permission;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process as LaravelProcess;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

class ExportQueueLifecycleTest extends TestCase
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

    #[DataProvider('queueScenarios')]
    public function test_real_database_workers_preserve_export_lifecycle(string $scenario): void
    {
        if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_EXPORT_QUEUE_TESTS') !== '1') {
            $this->markTestSkipped('Requires AUTHORITY_MYSQL_TESTS=1 and AUTHORITY_EXPORT_QUEUE_TESTS=1 with the owned Windows runner.');
        }
        $mysql = new DisposableMysql;
        $environment = null;
        $app = null;
        $workers = [];
        $temporary = null;
        $temporaryToken = null;
        $export = null;
        try {
            $mysql->start();
            (new Filesystem)->ensureDirectoryExists($mysql->path.'/export-private');
            file_put_contents($mysql->path.'/export-private/owner', $mysql->settings()['token']);
            $environment = new TestEnvironment;
            $app = DisposableMysql::bootApplication($mysql->settings(), $environment);
            $db = $app['db']->connection();
            $this->assertSame(0, $app->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]));
            ExportQueueFixture::configure($app, $mysql->settings());
            $actor = User::factory()->create(['email' => 'ops001@authority.example.test', 'must_change_password' => false]);
            $actor->permissions()->attach(Permission::firstOrCreate(['slug' => 'view-clients'], ['name' => 'View clients', 'group' => 'clients']));
            $rows = [];
            for ($index = 0; $index < 1207; $index++) {
                $rows[] = ['uuid' => (string) Str::uuid(), 'name' => sprintf('OPS001 %04d', $index), 'email' => sprintf('ops001-%04d@example.test', $index)];
            }
            foreach (array_chunk($rows, 100) as $batch) {
                Client::query()->insert($batch);
            }
            $sourceDigest = ExportQueueFixture::clientDigest();
            $export = Export::factory()->create(['user_id' => $actor->id, 'model_class' => Client::class, 'format' => 'csv',
                'columns' => ['name', 'email'], 'filters' => [], 'search' => 'OPS001', 'sort_column' => 'id', 'sort_direction' => 'asc']);
            $manifest = $mysql->path.'/export-workers.json';
            ExportQueueFixture::signal($manifest, json_encode(['settings' => $mysql->settings(), 'scenario' => $scenario, 'actor_id' => $actor->id,
                'export_id' => $export->id, 'export_uuid' => $export->uuid, 'client_digest' => $sourceDigest], JSON_THROW_ON_ERROR));
            Queue::connection('database')->push(new ProcessExport($export), '', ExportQueueFixture::queue($mysql->settings()));
            $originalQueueId = (int) $db->table('jobs')->sole()->id;
            $workers[] = $this->startWorker($mysql, $manifest, 0);
            $this->waitFor(fn (): bool => is_file($mysql->path.'/barrier/held-0.json'), $workers, 'The real export must reach its controlled boundary.');
            $held = json_decode(file_get_contents($mysql->path.'/barrier/held-0.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(Export::STATUS_PROCESSING, $export->fresh()->status);

            if ($scenario === 'finalization-race') {
                $this->assertTrue($held['inside_transaction']);
                $visible = $export->fresh();
                $this->assertSame(Export::STATUS_PROCESSING, $visible->status);
                $deadline = $visible->processing_expires_at->timestamp;
                $this->waitFor(fn (): bool => time() > $deadline, $workers, 'The committed processing lease must expire while finalization holds its row lock.');
                Queue::connection('database')->push(new ProcessExport($export), '', ExportQueueFixture::queue($mysql->settings()));
                $workers[] = $this->startWorker($mysql, $manifest, 1);
                $this->waitFor(fn (): bool => is_file($mysql->path.'/barrier/connection-1'), $workers, 'The competing worker must identify its owned database connection.');
                $connectionId = (int) file_get_contents($mysql->path.'/barrier/connection-1');
                $this->assertGreaterThan(0, $connectionId);
                $this->waitFor(fn (): bool => $mysql->waitingConnections([$connectionId]) === 1, $workers,
                    'The expired-lease contender must wait on the actual uncommitted completion row lock.');
                ExportQueueFixture::signal($mysql->path.'/barrier/release-0', 'commit');
                $this->finish($workers);
                $this->assertArtifact($export->fresh(), $rows);
                $this->assertSame($held['artifact'], $export->fresh()->file_path);
                $this->assertSame($held['digest'], hash_file('sha256', Storage::disk('local')->path($held['artifact'])));
                $this->assertSame($held['mtime'], filemtime(Storage::disk('local')->path($held['artifact'])));
                $this->assertSame(1, AppNotification::query()->count());
            } elseif ($scenario === 'long-restart') {
                $reserved = $db->table('jobs')->where('id', $originalQueueId)->first();
                $this->assertSame(1, (int) $reserved->attempts);
                $this->assertNotNull($reserved->reserved_at);
                $workers[] = $this->startWorker($mysql, $manifest, 1);
                $this->waitFor(fn (): bool => is_file($mysql->path.'/barrier/idle-1'), $workers, 'The second real worker must poll the reserved queue.');
                $started = hrtime(true);
                $this->waitFor(fn (): bool => (hrtime(true) - $started) / 1e9 >= 95 && is_file($mysql->path.'/barrier/late-idle-1'), $workers,
                    'The second worker must remain unable to reserve the running export beyond the previous 90 second lease.', 110);
                $this->assertFalse(is_file($mysql->path.'/barrier/job-1-1.json'), 'The same queue payload must not start on the second worker.');
                $this->assertSame(1, (int) $db->table('jobs')->where('id', $originalQueueId)->value('attempts'));
                $this->assertSame((int) $reserved->reserved_at, (int) $db->table('jobs')->where('id', $originalQueueId)->value('reserved_at'));
                $this->assertSame(0, $app->make(Kernel::class)->call('queue:restart', ['--no-interaction' => true]));
                ExportQueueFixture::signal($mysql->path.'/barrier/release-0', 'release');
                $this->finish($workers);
                foreach ([0, 1] as $index) {
                    $result = json_decode(file_get_contents($mysql->path.'/barrier/stopped-'.$index.'.json'), true, 512, JSON_THROW_ON_ERROR);
                    $this->assertSame('restart_signal', $result['reason']);
                    $this->assertTrue($result['isolated']);
                }
                $this->assertArtifact($export->fresh(), $rows);
                $this->assertSame(0, $db->table('jobs')->count());
                $replacement = Export::factory()->create(['user_id' => $actor->id, 'model_class' => Client::class, 'format' => 'csv',
                    'columns' => ['name', 'email'], 'filters' => [], 'search' => 'OPS001', 'sort_column' => 'id', 'sort_direction' => 'asc']);
                Queue::connection('database')->push(new ProcessExport($replacement), '', ExportQueueFixture::queue($mysql->settings()));
                $replacementManifest = $mysql->path.'/export-replacement.json';
                ExportQueueFixture::signal($replacementManifest, json_encode(['settings' => $mysql->settings(), 'scenario' => 'replacement', 'actor_id' => $actor->id,
                    'export_id' => $replacement->id, 'export_uuid' => $replacement->uuid, 'client_digest' => $sourceDigest], JSON_THROW_ON_ERROR));
                $workers[] = $this->startWorker($mysql, $replacementManifest, 0, true);
                $this->finish([$workers[2]]);
                $this->assertArtifact($replacement->fresh(), $rows);
                $this->assertSame(2, AppNotification::query()->count());
            } elseif ($scenario === 'kill-published') {
                $temporary = $held['temporary'];
                $oldPath = $held['artifact'];
                $oldToken = $export->fresh()->processing_token;
                $temporaryToken = $oldToken;
                $this->assertNotEmpty($oldToken);
                $this->assertFileExists($temporary);
                Storage::disk('local')->assertExists($oldPath);
                $workers[0]->stop(1);
                $this->assertFalse($workers[0]->isRunning());
                $this->assertSame(Export::STATUS_PROCESSING, $export->fresh()->status);
                $db->table('exports')->where('id', $export->id)->update(['processing_expires_at' => now()->subSecond()]);
                $this->assertSame(0, $app->make(Kernel::class)->call('exports:recover', ['--no-interaction' => true]));
                $this->assertSame(Export::STATUS_PENDING, $export->fresh()->status);
                $this->assertSame(2, $db->table('jobs')->count());
                $this->assertSame(0, $app->make(Kernel::class)->call('exports:recover', ['--no-interaction' => true]));
                $this->assertSame(2, $db->table('jobs')->count(), 'A pending recovery must not be enqueued twice.');
                $workers[] = $this->startWorker($mysql, $manifest, 1);
                $this->waitFor(fn (): bool => is_file($mysql->path.'/barrier/recovered-1'), [$workers[1]], 'Recovery must finish before making the old reserved payload available.');
                $this->assertArtifact($export->fresh(), $rows);
                $this->assertNotSame($oldToken, $export->fresh()->processing_token);
                $completed = $export->fresh()->getRawOriginal();
                $artifact = $export->fresh()->file_path;
                $digest = hash_file('sha256', Storage::disk('local')->path($artifact));
                $mtime = filemtime(Storage::disk('local')->path($artifact));
                $db->table('jobs')->where('id', $originalQueueId)->update(['reserved_at' => now()->subSeconds(1021)->timestamp]);
                ExportQueueFixture::signal($mysql->path.'/barrier/replay-old', 'replay');
                $this->finish([$workers[1]]);
                $this->assertSame($completed, $export->fresh()->getRawOriginal());
                $this->assertSame($digest, hash_file('sha256', Storage::disk('local')->path($artifact)));
                $this->assertSame($mtime, filemtime(Storage::disk('local')->path($artifact)));
                Storage::disk('local')->assertMissing($oldPath);
                $this->assertFileDoesNotExist($temporary);
                $temporary = null;
                $this->assertSame(1, AppNotification::query()->count());
            } elseif ($scenario === 'notice-failure') {
                ExportQueueFixture::signal($mysql->path.'/barrier/release-0', 'release');
                $this->finish($workers);
                $current = $export->fresh();
                $this->assertSame(Export::STATUS_PROCESSING, $current->status);
                $this->assertTrue($current->processing_expires_at->isPast());
                $this->assertNull($current->completed_at);
                $this->assertNull($current->file_path);
                $this->assertSame([], Storage::disk('local')->allFiles('exports/'.$export->uuid));
                $this->assertSame(0, AppNotification::query()->count());
                $released = $db->table('jobs')->sole();
                $this->assertNull($released->reserved_at);
                $this->assertSame(1, (int) $released->attempts);
                $this->assertGreaterThan(time(), (int) $released->available_at);
                $db->table('jobs')->where('id', $released->id)->update(['available_at' => now()->timestamp]);
                $workers[] = $this->startWorker($mysql, $manifest, 1);
                $this->finish([$workers[1]]);
                $this->assertArtifact($export->fresh(), $rows);
                $this->assertSame(1, AppNotification::query()->count());
            } else {
                if ($scenario === 'delete-owner') {
                    $actor->delete();
                } else {
                    $export->delete();
                }
                ExportQueueFixture::signal($mysql->path.'/barrier/release-0', 'release');
                $this->finish($workers);
                $this->assertNull(Export::query()->find($export->id));
                $this->assertSame([], Storage::disk('local')->allFiles('exports/'.$export->uuid));
                $this->assertSame(0, AppNotification::query()->count());
            }
            $this->assertSame($sourceDigest, ExportQueueFixture::clientDigest());
            $this->assertSame(0, $db->table('jobs')->count());
            $this->assertSame(0, $db->table('failed_jobs')->count());
            Mail::assertNothingOutgoing();
            Http::assertNothingSent();
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
                if (is_string($temporary) && is_file($temporary)) {
                    $this->assertInstanceOf(Export::class, $export);
                    ExportQueueFixture::removeOwnedTemporary($temporary, $export->uuid, $temporaryToken);
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
    public static function queueScenarios(): array
    {
        return ['long job and restart' => ['long-restart'], 'killed after publication' => ['kill-published'],
            'delete export while writing' => ['delete-export'], 'cascade owner while writing' => ['delete-owner'],
            'notice transaction rollback' => ['notice-failure'], 'expired contender waits for completion' => ['finalization-race']];
    }

    private function startWorker(DisposableMysql $mysql, string $manifest, int $index, bool $replacement = false): Process
    {
        $registry = $mysql->path.'/barrier/environment-'.$index;
        if ($replacement) {
            $this->assertFileExists($registry);
            $this->assertDirectoryDoesNotExist(file_get_contents($registry));
            $this->assertTrue(unlink($registry));
        }
        $worker = new Process([PHP_BINARY, dirname(__DIR__, 2).'/export-queue-worker.php', $manifest, (string) $index], dirname(__DIR__, 3), [
            'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_EXPORT_QUEUE_TESTS' => '1',
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $worker->setTimeout(180);
        $worker->start();

        return $worker;
    }

    /** @param list<Process> $workers */
    private function finish(array $workers): void
    {
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
        }
    }

    /** @param list<Process> $workers */
    private function waitFor(callable $condition, array $workers, string $message, int $seconds = 25): void
    {
        $deadline = hrtime(true) + $seconds * 1e9;
        while (! $condition()) {
            foreach ($workers as $worker) {
                if (! $worker->isRunning()) {
                    $this->fail($message.' '.$worker->getErrorOutput());
                }
            }
            if (hrtime(true) > $deadline) {
                $this->fail($message);
            }
            usleep(20000);
        }
        $this->assertTrue($condition(), $message);
    }

    /** @param list<array{uuid:string,name:string,email:string}> $expected */
    private function assertArtifact(Export $export, array $expected): void
    {
        $this->assertSame(Export::STATUS_COMPLETED, $export->status);
        $this->assertSame(1207, $export->processed_records);
        $this->assertSame(1207, $export->total_records);
        $this->assertMatchesRegularExpression('~\Aexports/'.preg_quote($export->uuid, '~').'/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.csv\z~', $export->file_path);
        $this->assertSame([$export->file_path], Storage::disk('local')->allFiles('exports/'.$export->uuid));
        $stream = fopen(Storage::disk('local')->path($export->file_path), 'rb');
        $this->assertIsResource($stream);
        try {
            $header = fgetcsv($stream, escape: '');
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            $this->assertSame(['Name', 'Email'], $header);
            foreach ($expected as $row) {
                $this->assertSame([$row['name'], $row['email']], fgetcsv($stream, escape: ''));
            }
            $this->assertFalse(fgetcsv($stream, escape: ''));
        } finally {
            fclose($stream);
        }
        $notice = AppNotification::query()->where('notifiable_type', Export::class)->where('notifiable_id', $export->id)->sole();
        $this->assertSame($export->user_id, $notice->recipient_id);
        $this->assertSame($export->uuid, $notice->data['export_uuid']);
    }
}

/** Shared only by this native test and its finite CLI worker. */
final class ExportQueueFixture
{
    /** @param array<string, mixed> $settings */
    public static function configure(Application $app, array $settings): void
    {
        $source = require dirname(__DIR__, 3).'/config/queue.php';
        $database = $source['connections']['database'];
        $database['connection'] = 'authority_sandbox';
        $database['queue'] = self::queue($settings);
        $database['table'] = 'jobs';
        $app['config']->set([
            'queue.default' => 'database', 'queue.connections' => ['database' => $database],
            'queue.failed' => ['driver' => 'database-uuids', 'database' => 'authority_sandbox', 'table' => 'failed_jobs'],
            'cache.default' => 'database', 'cache.stores.database' => ['driver' => 'database', 'connection' => 'authority_sandbox', 'table' => 'cache', 'lock_connection' => 'authority_sandbox', 'lock_table' => 'cache_locks'],
            'cache.prefix' => 'ops001-'.$settings['token'],
            'filesystems.disks.local' => ['driver' => 'local', 'root' => $settings['path'].'/export-private', 'throw' => true, 'visibility' => 'private'],
        ]);
        if (! LaravelProcess::preventingStrayProcesses() || ! Http::preventingStrayRequests()) {
            throw new RuntimeException('The queue fixture requires its external guards before worker scope resets.');
        }
        LaravelProcess::swap(LaravelProcess::getFacadeRoot());
        $app->forgetInstance('cache.store');
        $app['cache']->forgetDriver('database');
        Cache::clearResolvedInstance('cache');
        $manager = new QueueManager($app);
        $manager->addConnector('database', fn (): DatabaseConnector => new DatabaseConnector($app['db']));
        Queue::swap($manager);
        $app->forgetInstance('queue.connection');
        $app->make('filesystem')->forgetDisk('local');
    }

    /** @param array<string, mixed> $settings */
    public static function queue(array $settings): string
    {
        return 'ops001-'.$settings['token'];
    }

    public static function clientDigest(): string
    {
        return hash('sha256', Client::query()->orderBy('id')->get(['id', 'uuid', 'name', 'email'])->toJson());
    }

    public static function signal(string $path, string $bytes): void
    {
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('An owned export barrier must be new.');
        }
        $stream = fopen($path.'.tmp', 'xb');
        if ($stream === false) {
            throw new RuntimeException('Could not create owned export barrier.');
        }
        try {
            if (fwrite($stream, $bytes) !== strlen($bytes) || ! fflush($stream)) {
                throw new RuntimeException('Incomplete owned export barrier.');
            }
        } finally {
            fclose($stream);
        }
        if (! rename($path.'.tmp', $path)) {
            throw new RuntimeException('Could not publish owned export barrier.');
        }
    }

    public static function removeOwnedTemporary(string $path, string $uuid, ?string $token): void
    {
        $stat = lstat($path);
        if ($stat === false || is_link($path) || $stat['nlink'] !== 1 || realpath(dirname($path)) !== realpath(sys_get_temp_dir())
            || ! Str::isUuid($uuid) || ! is_string($token) || ! Str::isUuid($token) || basename($path) !== 'export-'.$uuid.'-'.$token.'.csv') {
            throw new RuntimeException('Refusing cleanup of an unrelated export temporary file.');
        }
        if (! unlink($path)) {
            throw new RuntimeException('The owned export temporary file could not be removed.');
        }
    }
}
