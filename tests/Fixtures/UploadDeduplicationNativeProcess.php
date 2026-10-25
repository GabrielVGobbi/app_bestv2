<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Services\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$environment = null;
$app = null;
$exitCode = 1;
$stage = 'invocation';

try {
    if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('UPL001_NATIVE_TESTS') !== '1'
        || count($argv) !== 3 || ! in_array($argv[2], ['0', '1'], true)) {
        throw new RuntimeException('Explicit owned upload native invocation required.');
    }

    TestEnvironment::assertSafeEnvironment();
    $stat = lstat($argv[1]);

    if ($stat === false || ! is_file($argv[1]) || is_link($argv[1]) || $stat['nlink'] !== 1 || $stat['size'] > 16384) {
        throw new RuntimeException('Invalid finite upload worker manifest.');
    }

    $manifest = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);

    if (! is_array($manifest) || count($manifest) !== 7 || ! isset($manifest['settings'], $manifest['parent_id'], $manifest['actor_id'], $manifest['tags'], $manifest['collections'], $manifest['created'], $manifest['expires'])
        || ! is_array($manifest['settings']) || ! is_int($manifest['parent_id']) || $manifest['parent_id'] < 1
        || ! is_int($manifest['actor_id']) || $manifest['actor_id'] < 1
        || ! is_int($manifest['created']) || ! is_int($manifest['expires'])
        || $manifest['expires'] <= time() || $manifest['expires'] > $manifest['created'] + 120
        || ! in_array([$manifest['tags'], $manifest['collections']], [[[null, null], [null, null]], [['Receipt', 'receipt'], ['Docs', 'docs']]], true)) {
        throw new RuntimeException('Upload worker manifest escaped its fixed synthetic identities or deadline.');
    }

    $settings = $manifest['settings'];
    DisposableMysql::verifiedConnection($settings);

    if (realpath($argv[1]) !== realpath($settings['path'].'/upload-workers.json')
        || is_link($settings['path'].'/barrier') || realpath(dirname($settings['path'].'/barrier')) !== realpath($settings['path'])
        || is_link($settings['path'].'/upload-private') || realpath(dirname($settings['path'].'/upload-private')) !== realpath($settings['path'])
        || file_get_contents($settings['path'].'/upload-private/owner') !== $settings['token']) {
        throw new RuntimeException('Upload worker escaped its owned local storage or barrier.');
    }

    $worker = (int) $argv[2];
    $environment = new TestEnvironment;
    file_put_contents($environment->path.'/authority-owner', $settings['token']);
    file_put_contents($settings['path'].'/barrier/environment-'.$worker, $environment->path);
    $stage = 'boot';
    $app = DisposableMysql::bootApplication($settings, $environment);
    $app['config']->set([
        'filesystems.disks.local' => ['driver' => 'local', 'root' => $settings['path'].'/upload-private', 'throw' => true, 'visibility' => 'private'],
        'uploads.disk' => 'local', 'uploads.public_rules' => [],
    ]);
    $app->make('filesystem')->forgetDisk('local');
    $actor = User::query()->findOrFail($manifest['actor_id']);
    $parent = Client::query()->findOrFail($manifest['parent_id']);

    if ($actor->email !== 'upl001@example.test' || $parent->email !== 'upl001-parent@example.test' || $parent->name !== 'UPL001 synthetic parent') {
        throw new RuntimeException('Upload worker identities are not the fixed owned fixtures.');
    }

    $app->make('auth')->guard('web')->setUser($actor);
    $connection = $app['db']->connection();
    $connectionId = (int) $connection->scalar('SELECT CONNECTION_ID()');
    $connection->beginTransaction();
    $snapshotRows = Uploaded::query()->count();

    if ($snapshotRows !== 0) {
        throw new RuntimeException('Each worker must establish an empty repeatable-read snapshot before upload.');
    }
    $signal = static function (string $name, string $bytes) use ($settings): void {
        $path = $settings['path'].'/barrier/'.$name;

        if (file_exists($path) || file_put_contents($path.'.tmp', $bytes, LOCK_EX) !== strlen($bytes) || ! rename($path.'.tmp', $path)) {
            throw new RuntimeException('Could not publish a fresh owned upload barrier signal.');
        }
    };
    $wait = static function (string $name) use ($settings, $manifest): void {
        $deadline = microtime(true) + 25;

        while (! is_file($settings['path'].'/barrier/'.$name)) {
            if (microtime(true) >= $deadline || time() >= $manifest['expires']) {
                throw new RuntimeException('Finite upload barrier deadline exceeded.');
            }

            usleep(20000);
        }
    };
    Uploaded::creating(static function (Uploaded $upload) use ($signal, $wait, $worker, $parent): void {
        if ($upload->parentable_type !== Client::class || $upload->parentable_id !== $parent->id) {
            throw new RuntimeException('Upload creation escaped its fixed synthetic parent.');
        }

        $signal('upload-creating-'.$worker, 'before-insert');
        $wait('upload-release-create');
    });
    $signal('upload-ready-'.$worker, (string) $connectionId);
    $stage = 'start-barrier';
    $wait('upload-go-'.$worker);
    $stage = 'upload';
    $upload = $app->make(UploadService::class)->upload(
        $parent,
        UploadedFile::fake()->createWithContent('native.txt', 'UPL001 native identical bytes'),
        tag: $manifest['tags'][$worker], collection: $manifest['collections'][$worker],
    );
    $transactionLevel = $connection->transactionLevel();

    if ($transactionLevel !== 1) {
        throw new RuntimeException('Upload must leave the caller-owned outer transaction intact.');
    }

    $connection->commit();
    Http::assertNothingSent();
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
    $signal('upload-result-'.$worker.'.json', json_encode([
        'id' => $upload->id, 'hash' => $upload->file_hash, 'path' => $upload->path,
        'integrations_isolated' => true, 'environment' => $environment->path,
        'snapshot_rows' => $snapshotRows, 'transaction_level_after_upload' => $transactionLevel,
    ], JSON_THROW_ON_ERROR));
    $exitCode = 0;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['stage' => $stage, 'exception' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL);
} finally {
    if ($app !== null && $app['db']->connection()->transactionLevel() > 0) {
        $app['db']->connection()->rollBack();
    }

    $app?->make('db')->disconnect();
    $app?->flush();
    $environment?->cleanUp();
}

exit($exitCode);
