<?php

use App\Models\User;
use App\Modules\Uploads\Jobs\GenerateZipJob;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process as LaravelProcess;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';

$environment = null;
$app = null;
$exitCode = 0;

/** Publish a complete owned barrier signal without exposing connection settings. */
function writeZipWorkerSignal(string $path, string $bytes): void
{
    $temporary = $path.'.tmp';
    if (file_exists($path) || is_link($path)) {
        throw new RuntimeException('ZIP worker signal must be new.');
    }
    $stream = fopen($temporary, 'xb');
    if ($stream === false) {
        throw new RuntimeException('ZIP worker signal could not be opened.');
    }
    try {
        if (fwrite($stream, $bytes) !== strlen($bytes) || ! fflush($stream)) {
            throw new RuntimeException('ZIP worker signal was incomplete.');
        }
    } finally {
        fclose($stream);
    }
    if (! rename($temporary, $path)) {
        throw new RuntimeException('ZIP worker signal could not be published.');
    }
}

try {
    if (getenv('AUTHORITY_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_ZIP_MYSQL_TESTS') !== '1'
        || ! isset($argv[1], $argv[2]) || ! in_array($argv[2], ['0', '1'], true)) {
        throw new RuntimeException('Explicit disposable ZIP concurrency invocation required.');
    }
    TestEnvironment::assertSafeEnvironment();
    $stat = lstat($argv[1]);
    if ($stat === false || is_link($argv[1]) || ($stat['mode'] & 0170000) !== 0100000 || $stat['nlink'] !== 1 || $stat['size'] > 16384) {
        throw new RuntimeException('Invalid owned ZIP worker manifest.');
    }
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($manifest) || count($manifest) !== 7 || ! isset($manifest['settings'], $manifest['scenario'], $manifest['actor_id'], $manifest['request_id'], $manifest['request_uuid'], $manifest['upload_ids'], $manifest['source_hashes'])
        || ! is_array($manifest['settings']) || ! in_array($manifest['scenario'], ['race', 'interrupted'], true)
        || ! is_int($manifest['actor_id']) || $manifest['actor_id'] < 1 || ! is_int($manifest['request_id']) || $manifest['request_id'] < 1
        || ! is_string($manifest['request_uuid']) || preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\z/', $manifest['request_uuid']) !== 1
        || ! is_array($manifest['upload_ids']) || ! array_is_list($manifest['upload_ids']) || count($manifest['upload_ids']) !== 2
        || ! is_array($manifest['source_hashes']) || ! array_is_list($manifest['source_hashes']) || count($manifest['source_hashes']) !== 2) {
        throw new RuntimeException('Invalid finite ZIP concurrency profile.');
    }
    foreach ($manifest['upload_ids'] as $id) {
        if (! is_int($id) || $id < 1) {
            throw new RuntimeException('ZIP source identifiers must be positive owned fixture identifiers.');
        }
    }
    if ($manifest['upload_ids'][0] === $manifest['upload_ids'][1]) {
        throw new RuntimeException('ZIP fixture requires two distinct inputs.');
    }
    foreach ($manifest['source_hashes'] as $digest) {
        if (! is_string($digest) || preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
            throw new RuntimeException('ZIP fixture source digests must be finite.');
        }
    }
    $settings = $manifest['settings'];
    DisposableMysql::verifiedConnection($settings);
    if (realpath($argv[1]) !== realpath($settings['path'].'/zip-workers.json')
        || ! is_dir($settings['path'].'/barrier') || is_link($settings['path'].'/barrier')
        || realpath(dirname($settings['path'].'/barrier')) !== realpath($settings['path'])) {
        throw new RuntimeException('ZIP worker manifest or barrier escaped its owned directory.');
    }
    foreach (['zip-private', 'zip-source'] as $leaf) {
        $directory = $settings['path'].'/'.$leaf;
        $owner = $directory.'/owner';
        $ownerStat = is_file($owner) ? lstat($owner) : false;
        if (! is_dir($directory) || is_link($directory) || realpath($directory) !== realpath($settings['path']).DIRECTORY_SEPARATOR.$leaf
            || $ownerStat === false || is_link($owner) || $ownerStat['nlink'] !== 1 || $ownerStat['size'] !== 32
            || file_get_contents($owner) !== $settings['token']) {
            throw new RuntimeException('ZIP storage must remain in its marked owned private directory.');
        }
    }
    $worker = (int) $argv[2];
    if ($manifest['scenario'] === 'interrupted' && $worker !== 0) {
        throw new RuntimeException('The finite interruption profile has only one owned worker.');
    }
    $environment = new TestEnvironment;
    file_put_contents($environment->path.'/authority-owner', $settings['token']);
    // Keep the same TEMP as the parent: DisposableMysql verifies that parent ownership boundary.
    // The existing owner-token registry also recovers this environment after a hard process stop.
    writeZipWorkerSignal($settings['path'].'/barrier/environment-'.$worker, $environment->path);
    $app = DisposableMysql::bootApplication($settings, $environment);
    $app['config']->set([
        'filesystems.disks.local' => ['driver' => 'local', 'root' => $settings['path'].'/zip-private', 'throw' => true, 'visibility' => 'private'],
        'filesystems.disks.zip_source' => ['driver' => 'local', 'root' => $settings['path'].'/zip-source', 'throw' => true, 'visibility' => 'private'],
        'uploads.zip.processing_timeout' => 60, 'uploads.zip.pending_timeout' => 120, 'uploads.zip.retention_hours' => 2,
    ]);
    $app->make('filesystem')->forgetDisk(['local', 'zip_source']);
    Notification::fake();
    $actor = User::query()->findOrFail($manifest['actor_id']);
    $request = ZipRequest::query()->findOrFail($manifest['request_id']);
    if ($actor->email !== 'zip001@authority.example.test' || ! $actor->hasVerifiedEmail() || $actor->must_change_password
        || $request->user_id !== $actor->id || $request->uuid !== $manifest['request_uuid']
        || $request->status !== 'pending' || $request->file_ids !== $manifest['upload_ids']) {
        throw new RuntimeException('ZIP worker escaped its fixed pending synthetic request.');
    }
    $uploads = Uploaded::query()->whereIn('id', $manifest['upload_ids'])->orderBy('id')->get();
    if ($uploads->count() !== 2) {
        throw new RuntimeException('ZIP worker requires both finite input records.');
    }
    $knownPaths = [];
    foreach ($uploads as $index => $upload) {
        $expected = ['first', 'second'][$index];
        $absolute = Storage::disk('zip_source')->path('files/'.$expected.'.txt');
        $sourceStat = is_file($absolute) ? lstat($absolute) : false;
        if ($upload->disk !== 'zip_source' || $upload->path !== 'files/'.$expected.'.txt'
            || $sourceStat === false || is_link($absolute) || $sourceStat['nlink'] !== 1
            || realpath($absolute) !== realpath($settings['path'].'/zip-source').DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$expected.'.txt'
            || $upload->user_id !== $actor->id || hash('sha256', Storage::disk('zip_source')->get($upload->path)) !== $manifest['source_hashes'][$index]) {
            throw new RuntimeException('ZIP worker source metadata or bytes changed.');
        }
        $knownPaths[] = $upload->path;
    }
    $observer = (object) ['reads' => 0];
    $source = Storage::disk('zip_source');
    Storage::set('zip_source', new class($source, $observer, $knownPaths) extends FilesystemAdapter
    {
        /** @param list<string> $knownPaths */
        public function __construct(FilesystemAdapter $base, private readonly stdClass $observer, private readonly array $knownPaths)
        {
            parent::__construct($base->getDriver(), $base->getAdapter(), $base->getConfig());
        }

        public function readStream($path): mixed
        {
            if (! in_array($path, $this->knownPaths, true)) {
                throw new RuntimeException('ZIP worker tried an unrelated private source.');
            }
            $this->observer->reads++;

            return parent::readStream($path);
        }
    });
    if ($manifest['scenario'] === 'interrupted') {
        $output = Storage::disk('local');
        Storage::set('local', new class($output, $settings['path'], $manifest['request_uuid'], $manifest['request_id']) extends FilesystemAdapter
        {
            private bool $paused = false;

            public function __construct(FilesystemAdapter $base, private readonly string $ownedRoot, private readonly string $uuid, private readonly int $requestId)
            {
                parent::__construct($base->getDriver(), $base->getAdapter(), $base->getConfig());
            }

            /** @param mixed $path */
            public function put($path, $contents, $options = []): bool
            {
                $request = ZipRequest::query()->findOrFail($this->requestId);
                $attempt = 'zip-requests/'.preg_quote($this->uuid, '~').'/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}';
                if (! is_string($path) || preg_match('~\A'.$attempt.'/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.tmp\z~', $path) !== 1
                    || ! is_string($request->zip_path) || dirname($request->zip_path) !== dirname($path)
                    || realpath(dirname($this->path($path))) !== realpath($this->ownedRoot.'/zip-private').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, dirname($path))) {
                    throw new RuntimeException('ZIP staging write escaped its canonical owned attempt.');
                }
                $written = parent::put($path, $contents, $options);
                if (! $this->paused && $written && is_resource($contents)) {
                    $stageStat = is_file($this->path($path)) ? lstat($this->path($path)) : false;
                    if ($stageStat === false || is_link($this->path($path)) || $stageStat['nlink'] !== 1) {
                        throw new RuntimeException('ZIP staging pause escaped its canonical owned attempt.');
                    }
                    $this->paused = true;
                    writeZipWorkerSignal($this->ownedRoot.'/barrier/zip-staged.json', json_encode(['artifact' => $request->zip_path, 'staged' => $path], JSON_THROW_ON_ERROR));
                    $deadline = microtime(true) + 30;
                    while (! is_file($this->ownedRoot.'/barrier/zip-resume')) {
                        if (microtime(true) >= $deadline) {
                            throw new RuntimeException('The owned ZIP interruption barrier timed out.');
                        }
                        usleep(10000);
                    }
                }

                return $written;
            }
        });
    }
    $job = unserialize(serialize(new GenerateZipJob($request)));
    if (! $job instanceof GenerateZipJob) {
        throw new RuntimeException('ZIP worker could not retain its serialized attempt.');
    }
    $connectionId = (int) $app['db']->connection()->scalar('SELECT CONNECTION_ID()');
    if ($connectionId < 1) {
        throw new RuntimeException('ZIP worker requires its real owned MySQL connection.');
    }
    writeZipWorkerSignal($settings['path'].'/barrier/zip-ready-'.$worker, (string) $connectionId);
    $deadline = microtime(true) + 30;
    while (! is_file($settings['path'].'/barrier/zip-go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('The owned ZIP start barrier timed out.');
        }
        usleep(10000);
    }
    $job->handle();
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
    Notification::assertNothingSent();
    Http::assertNothingSent();
    if (! Http::preventingStrayRequests() || ! LaravelProcess::preventingStrayProcesses()) {
        throw new RuntimeException('ZIP worker lost its external integration guards.');
    }
    writeZipWorkerSignal($settings['path'].'/barrier/zip-result-'.$worker.'.json', json_encode([
        'source_reads' => $observer->reads, 'status' => $request->fresh()->status, 'integrations_isolated' => true,
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    fwrite(STDERR, 'ZIP concurrency worker failed: '.$exception::class."\n");
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
