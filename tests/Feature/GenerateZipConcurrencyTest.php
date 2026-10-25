<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Uploads\Http\Controllers\ZipRequestController;
use App\Modules\Uploads\Http\Requests\StoreZipRequestRequest;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\TestEnvironment;
use ZipArchive;

class GenerateZipConcurrencyTest extends TestCase
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

    #[DataProvider('nativeScenarios')]
    public function test_native_zip_claim_and_interrupted_worker_recovery(string $scenario): void
    {
        if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_ZIP_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Native ZIP validation requires AUTHORITY_MYSQL_TESTS=1 and AUTHORITY_ZIP_MYSQL_TESTS=1.');
        }
        $mysql = new DisposableMysql;
        $environment = null;
        $app = null;
        $connection = null;
        $workers = [];
        try {
            $mysql->start();
            foreach (['zip-private', 'zip-source'] as $directory) {
                (new Filesystem)->ensureDirectoryExists($mysql->path.'/'.$directory);
                file_put_contents($mysql->path.'/'.$directory.'/owner', $mysql->settings()['token']);
            }
            $environment = new TestEnvironment;
            $app = DisposableMysql::bootApplication($mysql->settings(), $environment);
            $app['config']->set([
                'filesystems.disks.local' => ['driver' => 'local', 'root' => $mysql->path.'/zip-private', 'throw' => true, 'visibility' => 'private'],
                'filesystems.disks.zip_source' => ['driver' => 'local', 'root' => $mysql->path.'/zip-source', 'throw' => true, 'visibility' => 'private'],
                'uploads.zip.processing_timeout' => 60, 'uploads.zip.pending_timeout' => 120, 'uploads.zip.retention_hours' => 2,
            ]);
            $app->make('filesystem')->forgetDisk(['local', 'zip_source']);
            Notification::fake();
            $connection = $app['db']->connection();
            $this->assertSame('REPEATABLE-READ', $connection->scalar('SELECT @@transaction_isolation'));
            $this->assertSame(0, $app->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]));
            $actor = User::factory()->create(['email' => 'zip001@authority.example.test', 'must_change_password' => false]);
            $actor->permissions()->attach(Permission::firstOrCreate(['slug' => 'view-clients'], ['name' => 'View clients', 'group' => 'clients']));
            $parent = Client::factory()->create();
            $uploads = [];
            foreach (['first', 'second'] as $name) {
                $bytes = 'ZIP001 native '.$name."\n";
                $path = 'files/'.$name.'.txt';
                Storage::disk('zip_source')->put($path, $bytes);
                $uploads[] = Uploaded::query()->create([
                    'user_id' => $actor->id, 'uploader_id' => $actor->id, 'parentable_type' => Client::class, 'parentable_id' => $parent->id,
                    'name' => $name, 'file_name' => $name.'.txt', 'original_name' => $name.'.txt', 'mime_type' => 'text/plain',
                    'path' => $path, 'extension' => 'txt', 'disk' => 'zip_source', 'size' => strlen($bytes),
                ]);
            }
            $request = ZipRequest::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $actor->id, 'label' => 'ZIP001 native fixture',
                'file_ids' => array_map(fn (Uploaded $upload): int => $upload->id, $uploads), 'file_count' => 2, 'status' => 'pending']);
            $sourceHashes = array_map(fn (Uploaded $upload): string => hash('sha256', Storage::disk('zip_source')->get($upload->path)), $uploads);
            $manifest = $mysql->path.'/zip-workers.json';
            $bytes = json_encode(['settings' => $mysql->settings(), 'scenario' => $scenario, 'actor_id' => $actor->id, 'request_id' => $request->id,
                'request_uuid' => $request->uuid, 'upload_ids' => $request->file_ids, 'source_hashes' => $sourceHashes], JSON_THROW_ON_ERROR);
            $this->assertSame(strlen($bytes), file_put_contents($manifest, $bytes));
            if ($scenario === 'race') {
                $connection->beginTransaction();
                $connection->table('zip_requests')->where('id', $request->id)->lockForUpdate()->first();
            }
            foreach ($scenario === 'race' ? [0, 1] : [0] as $index) {
                $worker = new Process([PHP_BINARY, dirname(__DIR__).'/zip-generation-worker.php', $manifest, (string) $index], dirname(__DIR__, 2), [
                    'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_ZIP_MYSQL_TESTS' => '1',
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
                ]);
                $worker->setTimeout(45);
                $worker->start();
                $workers[] = $worker;
            }
            $this->waitUntil(fn (): bool => is_file($mysql->path.'/barrier/zip-ready-0') && ($scenario !== 'race' || is_file($mysql->path.'/barrier/zip-ready-1')), $workers, 'ZIP workers must load the same pending request before entering the claim.');
            $ids = [(int) file_get_contents($mysql->path.'/barrier/zip-ready-0')];
            if ($scenario === 'race') {
                $ids[] = (int) file_get_contents($mysql->path.'/barrier/zip-ready-1');
                $this->assertNotSame($ids[0], $ids[1]);
            }
            file_put_contents($mysql->path.'/barrier/zip-go', 'go');
            if ($scenario === 'race') {
                $this->waitUntil(fn (): bool => $mysql->waitingConnections($ids) === 2, $workers, 'Both ZIP deliveries must be observed waiting on real InnoDB locks before release.');
                $connection->commit();
                foreach ($workers as $worker) {
                    $worker->wait();
                    $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                }
                $reads = [];
                foreach ([0, 1] as $index) {
                    $result = json_decode(file_get_contents($mysql->path.'/barrier/zip-result-'.$index.'.json'), true, 512, JSON_THROW_ON_ERROR);
                    $this->assertTrue($result['integrations_isolated']);
                    $reads[] = $result['source_reads'];
                }
                sort($reads);
                $this->assertSame([0, 2], $reads, 'Exactly one delivery may open the two private inputs.');
                $this->assertReadyArchive($request->fresh());
            } else {
                $this->waitUntil(fn (): bool => is_file($mysql->path.'/barrier/zip-staged.json'), $workers, 'The owned worker must pause only after a real staged member write.');
                $staged = json_decode(file_get_contents($mysql->path.'/barrier/zip-staged.json'), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame('processing', $request->fresh()->status);
                $this->assertSame($request->fresh()->zip_path, $staged['artifact']);
                Storage::disk('local')->assertExists($staged['staged']);
                $workers[0]->stop(1);
                $this->assertFalse($workers[0]->isRunning());
                $this->assertSame('processing', $request->fresh()->status, 'The killed worker must not execute its failed callback.');
                Storage::disk('local')->assertExists($staged['staged']);
                $connection->table('zip_requests')->where('id', $request->id)->update(['expires_at' => now()->subSecond()]);
                $this->assertSame(0, $app->make(Kernel::class)->call('uploads:prune-zips', ['--no-interaction' => true]));
                $this->assertSame('failed', $request->fresh()->status);
                $this->assertNull($request->fresh()->zip_path);
                $this->assertSame([], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
                $terminal = $request->fresh()->getRawOriginal();
                Bus::fake([GenerateZipJob::class]);
                $app->make('auth')->guard('web')->setUser($actor);
                $post = StoreZipRequestRequest::create('/api/v1/uploads/zip-requests', 'POST', ['ids' => $request->file_ids, 'label' => 'ZIP001 recovered request']);
                $post->setContainer($app)->setRedirector($app->make('redirect'))->setUserResolver(fn (): User => $actor);
                $post->validateResolved();
                $response = $app->make(ZipRequestController::class)->store($post);
                $this->assertSame(202, $response->getStatusCode());
                $replacement = ZipRequest::query()->whereUuid($response->getData(true)['data']['uuid'])->sole();
                $this->assertNotSame($request->uuid, $replacement->uuid);
                Bus::assertDispatched(GenerateZipJob::class, 1);
                (new GenerateZipJob($replacement))->handle();
                $this->assertReadyArchive($replacement->fresh());
                (new GenerateZipJob($request))->handle();
                $this->assertSame($terminal, $request->fresh()->getRawOriginal());
                $this->assertSame([], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
            }
            foreach ($uploads as $index => $upload) {
                $this->assertSame($sourceHashes[$index], hash('sha256', Storage::disk('zip_source')->get($upload->path)));
            }
            Mail::assertNothingOutgoing();
            Queue::assertNothingPushed();
            Notification::assertNothingSent();
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
    public static function nativeScenarios(): array
    {
        return ['competing deliveries' => ['race'], 'interrupted owned worker' => ['interrupted']];
    }

    private function assertReadyArchive(ZipRequest $request): void
    {
        $this->assertSame('ready', $request->status);
        $this->assertMatchesRegularExpression('~\Azip-requests/'.preg_quote($request->uuid, '~').'/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}/archive\.zip\z~', $request->zip_path);
        $this->assertSame([$request->zip_path], Storage::disk('local')->allFiles('zip-requests/'.$request->uuid));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($request->zip_path), ZipArchive::CHECKCONS));
        try {
            $this->assertSame(2, $zip->numFiles);
            $this->assertSame("ZIP001 native first\n", $zip->getFromName('first.txt'));
            $this->assertSame("ZIP001 native second\n", $zip->getFromName('second.txt'));
        } finally {
            $this->assertTrue($zip->close());
        }
    }

    /** @param list<Process> $workers */
    private function waitUntil(callable $condition, array $workers, string $message): void
    {
        $deadline = microtime(true) + 20;
        while (! $condition()) {
            foreach ($workers as $worker) {
                if (! $worker->isRunning()) {
                    $this->fail($message.' '.$worker->getErrorOutput());
                }
            }
            if (microtime(true) >= $deadline) {
                $this->fail($message);
            }
            usleep(20000);
        }

        $this->assertTrue($condition(), $message);
    }
}
