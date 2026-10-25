<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Uploads\Models\Uploaded;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

class UploadDeduplicationNativeTest extends TestCase
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

    #[DataProvider('nativeIdentities')]
    public function test_competing_uploads_share_one_row_and_file_under_a_real_parent_lock(
        ?string $firstTag,
        ?string $secondTag,
        ?string $firstCollection,
        ?string $secondCollection,
    ): void {
        if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('UPL001_NATIVE_TESTS') !== '1') {
            $this->markTestSkipped('Native upload deduplication requires AUTHORITY_MYSQL_TESTS=1 and UPL001_NATIVE_TESTS=1.');
        }

        $mysql = new DisposableMysql;
        $environment = null;
        $app = null;
        $workers = [];

        try {
            $mysql->start();
            (new Filesystem)->ensureDirectoryExists($mysql->path.'/upload-private');
            file_put_contents($mysql->path.'/upload-private/owner', $mysql->settings()['token']);
            $environment = new TestEnvironment;
            $app = DisposableMysql::bootApplication($mysql->settings(), $environment);
            $app['config']->set('filesystems.disks.local', ['driver' => 'local', 'root' => $mysql->path.'/upload-private', 'throw' => true, 'visibility' => 'private']);
            $app->make('filesystem')->forgetDisk('local');
            $connection = $app['db']->connection();
            $this->assertSame('REPEATABLE-READ', $connection->scalar('SELECT @@transaction_isolation'));
            $this->assertSame('utf8mb4_unicode_ci', $connection->scalar('SELECT @@collation_database'));
            $this->assertSame(0, $app->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]));
            $actor = User::factory()->create(['email' => 'upl001@example.test']);
            $parent = Client::factory()->create(['name' => 'UPL001 synthetic parent', 'email' => 'upl001-parent@example.test']);
            $manifest = $mysql->path.'/upload-workers.json';
            $bytes = json_encode([
                'settings' => $mysql->settings(), 'parent_id' => $parent->id, 'actor_id' => $actor->id,
                'tags' => [$firstTag, $secondTag], 'collections' => [$firstCollection, $secondCollection],
                'created' => time(), 'expires' => time() + 120,
            ], JSON_THROW_ON_ERROR);
            $this->assertSame(strlen($bytes), file_put_contents($manifest, $bytes));

            foreach ([0, 1] as $index) {
                $worker = new Process([
                    PHP_BINARY, dirname(__DIR__).'/Fixtures/UploadDeduplicationNativeProcess.php', $manifest, (string) $index,
                ], dirname(__DIR__, 2), [
                    'AUTHORITY_MYSQL_TESTS' => '1', 'UPL001_NATIVE_TESTS' => '1',
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
                ]);
                $worker->setTimeout(45);
                $worker->start();
                $workers[] = $worker;
            }

            $this->waitUntil(fn (): bool => is_file($mysql->path.'/barrier/upload-ready-0') && is_file($mysql->path.'/barrier/upload-ready-1'), $workers, 'Both owned PHP processes must be ready.');
            $ids = [(int) file_get_contents($mysql->path.'/barrier/upload-ready-0'), (int) file_get_contents($mysql->path.'/barrier/upload-ready-1')];
            $this->assertGreaterThan(0, $ids[0]);
            $this->assertGreaterThan(0, $ids[1]);
            $this->assertNotSame($ids[0], $ids[1]);
            file_put_contents($mysql->path.'/barrier/upload-go-0', 'go');
            $this->waitUntil(fn (): bool => is_file($mysql->path.'/barrier/upload-creating-0'), $workers, 'First upload must pause after its file write and before its row insert.');
            file_put_contents($mysql->path.'/barrier/upload-go-1', 'go');
            $waiting = false;
            $this->waitUntil(function () use ($mysql, $ids, &$waiting): bool {
                $waiting = $mysql->waitingConnections([$ids[1]]) === 1;

                return $waiting || is_file($mysql->path.'/barrier/upload-creating-1');
            }, $workers, 'Second contender must reach the real lock or expose the original pre-insert race.');
            file_put_contents($mysql->path.'/barrier/upload-release-create', 'release');

            $results = [];
            foreach ($workers as $index => $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $this->assertSame('', $worker->getErrorOutput());
                $results[] = json_decode(file_get_contents($mysql->path.'/barrier/upload-result-'.$index.'.json'), true, 32, JSON_THROW_ON_ERROR);
            }

            $this->assertSame($results[0]['id'], $results[1]['id'], 'Two simultaneous identical uploads must return the same logical row.');
            $this->assertSame(1, Uploaded::query()->count());
            $this->assertCount(1, Storage::disk('local')->allFiles('uploads'));
            $this->assertTrue($waiting, 'The second process must be observed waiting on a real InnoDB lock.');
            $this->assertFileDoesNotExist($mysql->path.'/barrier/upload-creating-1', 'The second contender must reuse the committed row before another insert/file write.');
            $upload = Uploaded::query()->sole();
            $this->assertSame($firstTag, $upload->tag);
            $this->assertSame($firstCollection, $upload->collection);
            $this->assertSame('UPL001 native identical bytes', Storage::disk('local')->get($upload->path));

            foreach ($results as $result) {
                $this->assertTrue($result['integrations_isolated']);
                $this->assertSame(0, $result['snapshot_rows']);
                $this->assertSame(1, $result['transaction_level_after_upload']);
                $this->assertDirectoryDoesNotExist($result['environment']);
                $this->assertSame(hash('sha256', 'UPL001 native identical bytes'), $result['hash']);
            }
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

    /** @return array<string, array{?string, ?string, ?string, ?string}> */
    public static function nativeIdentities(): array
    {
        return [
            'both nullable identity fields' => [null, null, null, null],
            'existing case insensitive DB equality' => ['Receipt', 'receipt', 'Docs', 'docs'],
        ];
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
    }
}
