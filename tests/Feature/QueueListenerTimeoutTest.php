<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Queue\Listener;
use Illuminate\Queue\ListenerOptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process as LaravelProcess;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Tests\TestCase;

use function Illuminate\Support\artisan_binary;
use function Illuminate\Support\php_binary;

class QueueListenerTimeoutTest extends TestCase
{
    public function test_listener_enforces_an_external_timeout_on_its_owned_windows_child_without_pcntl(): void
    {
        $this->runOwnedListenerFixture(timeout: 1, microseconds: 3000000, shouldTimeOut: true);
    }

    public function test_listener_allows_its_owned_child_to_finish_within_the_external_budget(): void
    {
        $this->runOwnedListenerFixture(timeout: 5, microseconds: 200000, shouldTimeOut: false);
    }

    private function runOwnedListenerFixture(int $timeout, int $microseconds, bool $shouldTimeOut): void
    {
        $this->assertSame('Windows', PHP_OS_FAMILY);
        $this->assertFalse(extension_loaded('pcntl'));
        $this->assertSame('artisan', artisan_binary(), 'Refuse an ARTISAN_BINARY override that could execute the application.');
        $this->assertSame(realpath(PHP_BINARY), realpath(php_binary()), 'The Listener must use the same guarded PHP executable as PHPUnit.');
        $this->isolateProjectFiles();
        $project = $this->testEnvironment->path.'/project';
        $this->assertSame(realpath($this->testEnvironment->path).DIRECTORY_SEPARATOR.'project', realpath($project));
        $this->assertFalse(is_link($project));
        $this->assertFileDoesNotExist($project.'/.env');
        $this->assertFileDoesNotExist($project.'/artisan');
        $this->assertDirectoryDoesNotExist($project.'/vendor');

        $token = bin2hex(random_bytes(16));
        $files = new Filesystem;
        $files->ensureDirectoryExists($project.'/listener-ini-scan');
        $this->assertSame(realpath($project).DIRECTORY_SEPARATOR.'listener-ini-scan', realpath($project.'/listener-ini-scan'));
        $profile = json_encode(['token' => $token, 'microseconds' => $microseconds], JSON_THROW_ON_ERROR);
        $this->assertSame(strlen($profile), file_put_contents($project.'/listener-profile.json', $profile));
        $this->assertSame(32, file_put_contents($project.'/listener-owner', $token));
        $ini = "[PHP]\nauto_prepend_file=\nauto_append_file=\ndisplay_errors=0\nlog_errors=0\nmax_execution_time=0\n";
        $this->assertSame(strlen($ini), file_put_contents($project.'/listener-php.ini', $ini));
        $stub = $this->syntheticArtisan();
        $this->assertSame(strlen($stub), file_put_contents($project.'/artisan', $stub));
        foreach (['artisan', 'listener-profile.json', 'listener-owner', 'listener-php.ini'] as $leaf) {
            $stat = lstat($project.'/'.$leaf);
            $this->assertSame(1, $stat['nlink']);
            $this->assertFalse(is_link($project.'/'.$leaf));
            $this->assertSame(realpath($project).DIRECTORY_SEPARATOR.$leaf, realpath($project.'/'.$leaf));
        }

        $listener = new Listener($project);
        $options = new ListenerOptions(name: 'ops001-listener-fixture', environment: 'testing', memory: PHP_INT_MAX,
            timeout: $timeout, sleep: 0, maxTries: 1);
        $process = $listener->makeProcess('database', 'ops001-listener-'.$token, $options);
        $process->setOptions(['create_new_console' => false]);
        $process->setEnv([
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'PHPRC' => $project.'/listener-php.ini', 'PHP_INI_SCAN_DIR' => $project.'/listener-ini-scan',
        ]);
        $this->assertSame($project, $process->getWorkingDirectory());
        $this->assertSame((float) $timeout, $process->getTimeout());
        $started = hrtime(true);
        $failure = null;
        try {
            try {
                $listener->runProcess($process, PHP_INT_MAX);
            } catch (ProcessTimedOutException $exception) {
                $failure = $exception;
            }
            $elapsed = (hrtime(true) - $started) / 1e9;
            $this->assertFalse($process->isRunning());
            $this->assertSame("OPS001 synthetic listener child ready\n", $process->getOutput());
            $this->assertSame('', $process->getErrorOutput());
            $this->assertFileExists($project.'/listener-started.json');
            $marker = json_decode(file_get_contents($project.'/listener-started.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($token, $marker['token']);
            $this->assertIsInt($marker['pid']);
            $this->assertGreaterThan(0, $marker['pid']);
            $this->assertSame(realpath($project), $marker['directory']);
            if ($shouldTimeOut) {
                $this->assertInstanceOf(ProcessTimedOutException::class, $failure);
                $this->assertSame($process, $failure->getProcess());
                $this->assertTrue($failure->isGeneralTimeout());
                $this->assertSame(1.0, $failure->getExceededTimeout());
                $this->assertGreaterThanOrEqual(0.9, $elapsed);
                $this->assertLessThan(2.8, $elapsed, 'The external timeout must stop the child before its finite three second completion. Exit code: '
                    .var_export($process->getExitCode(), true).'; finished marker: '.(is_file($project.'/listener-finished.json') ? 'present' : 'absent'));
                $this->assertFileDoesNotExist($project.'/listener-finished.json');
                $this->assertNotSame(0, $process->getExitCode());
            } else {
                $this->assertNull($failure);
                $this->assertSame(0, $process->getExitCode());
                $this->assertGreaterThanOrEqual(0.15, $elapsed);
                $this->assertLessThan(5, $elapsed);
                $this->assertFileExists($project.'/listener-finished.json');
                $this->assertSame($marker, json_decode(file_get_contents($project.'/listener-finished.json'), true, 512, JSON_THROW_ON_ERROR));
            }
            $this->assertFileDoesNotExist($project.'/.env');
            Mail::assertNothingOutgoing();
            Queue::assertNothingPushed();
            Http::assertNothingSent();
            $this->assertTrue(Http::preventingStrayRequests());
            $this->assertTrue(LaravelProcess::preventingStrayProcesses());
        } finally {
            if ($process->isRunning()) {
                $process->stop(0);
            }
            $this->assertFalse($process->isRunning());
        }
    }

    private function syntheticArtisan(): string
    {
        return <<<'PHP'
<?php

// Standalone finite fixture: no autoload, application bootstrap, environment file or database.
$root = realpath(__DIR__);
if ($root === false || realpath(getcwd()) !== $root || is_link(__FILE__) || realpath(__FILE__) !== $root.DIRECTORY_SEPARATOR.'artisan'
    || ini_get('auto_prepend_file') !== '' || ini_get('auto_append_file') !== ''
    || php_ini_loaded_file() === false || realpath(php_ini_loaded_file()) !== $root.DIRECTORY_SEPARATOR.'listener-php.ini') {
    exit(80);
}
foreach (['listener-owner', 'listener-profile.json'] as $leaf) {
    $path = $root.DIRECTORY_SEPARATOR.$leaf;
    $stat = is_file($path) ? lstat($path) : false;
    if ($stat === false || is_link($path) || $stat['nlink'] !== 1 || $stat['size'] > 256 || realpath($path) !== $path) {
        exit(81);
    }
}
$profile = json_decode(file_get_contents($root.'/listener-profile.json'), true, 8, JSON_THROW_ON_ERROR);
if (! is_array($profile) || count($profile) !== 2 || ! isset($profile['token'], $profile['microseconds'])
    || ! is_string($profile['token']) || preg_match('/\A[a-f0-9]{32}\z/', $profile['token']) !== 1
    || ! in_array($profile['microseconds'], [200000, 3000000], true)
    || file_get_contents($root.'/listener-owner') !== $profile['token']) {
    exit(82);
}
$expected = ['queue:work', 'database', '--once', '--name=ops001-listener-fixture', '--queue=ops001-listener-'.$profile['token'],
    '--backoff=0', '--memory='.PHP_INT_MAX, '--sleep=0', '--tries=1', '--env=testing'];
if (array_slice($argv, 1) !== $expected) {
    exit(83);
}
$marker = json_encode(['token' => $profile['token'], 'pid' => getmypid(), 'directory' => $root], JSON_THROW_ON_ERROR);
$write = static function (string $leaf) use ($root, $marker): void {
    $path = $root.DIRECTORY_SEPARATOR.$leaf;
    if (! in_array($leaf, ['listener-started.json', 'listener-finished.json'], true) || file_exists($path) || is_link($path)) {
        exit(84);
    }
    $stream = fopen($path.'.tmp', 'xb');
    if ($stream === false || fwrite($stream, $marker) !== strlen($marker) || ! fflush($stream)) {
        exit(85);
    }
    fclose($stream);
    if (! rename($path.'.tmp', $path)) {
        exit(86);
    }
};
$write('listener-started.json');
fwrite(STDOUT, "OPS001 synthetic listener child ready\n");
fflush(STDOUT);
usleep($profile['microseconds']);
$write('listener-finished.json');
exit(0);
PHP;
    }
}
