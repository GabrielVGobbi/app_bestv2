<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Webhooks\SesWebhookHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Fixtures\Email\SnsMessageFixture;
use Tests\TestCase;
use Tests\TestEnvironment;
use Throwable;

class SesTransportIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('STABILIZATION_SNS_NATIVE_TRANSPORT') !== '1') {
            $this->markTestSkipped('Optional owned loopback cURL integration requires STABILIZATION_SNS_NATIVE_TRANSPORT=1.');
        }

        TestEnvironment::assertSafeEnvironment();
        TestEnvironment::assertSafeDatabase($this->app->make('config'));
        $this->assertTrue(extension_loaded('curl'), 'Native profile requires cURL; no fallback or skip.');
        $this->assertTrue(function_exists('proc_open'), 'Native profile requires proc_open; no fallback or skip.');
        $this->isolateProjectFiles();
    }

    public function test_chunked_response_aborts_before_downloading_entire_oversized_body(): void
    {
        $options = $this->verifiedTransportOptions();
        $token = bin2hex(random_bytes(16));
        $root = $this->testEnvironment->path.'/sns-transport-'.$token;
        $this->assertDirectoryDoesNotExist($root);
        $this->assertTrue(mkdir($root, 0700));
        $this->assertSame(32, file_put_contents($root.'/.sns-transport-owner', $token, LOCK_EX));
        $this->assertOwnedRoot($root, $token);
        $process = null;
        $pipes = [];
        $stats = null;
        $failure = null;
        $status = null;
        $diagnostics = [];
        $harnessFailure = null;
        $processState = null;
        $exitCode = null;

        try {
            $process = proc_open([
                PHP_BINARY, '-n', '-r', $this->serverCode(), $root, $token, dirname(__DIR__, 2).'/TestEnvironment.php',
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $this->childEnvironment(), ['bypass_shell' => true]);
            $this->assertIsResource($process, 'Cannot create owned loopback server.');
            fclose($pipes[0]);
            unset($pipes[0]);
            foreach ($pipes as $pipe) {
                stream_set_blocking($pipe, false);
            }
            $ready = $this->awaitOwnedJson($root, $token, 'ready.json', $process, 8);
            $this->assertIsInt($ready['port'] ?? null);
            $port = $ready['port'];
            $this->assertGreaterThan(0, $port);
            $this->assertLessThanOrEqual(65535, $port);
            $this->assertSame(proc_get_status($process)['pid'], $ready['pid'] ?? null);
            $url = 'http://127.0.0.1:'.$port.'/probe';
            $this->assertSame('127.0.0.1', parse_url($url, PHP_URL_HOST));

            $options['on_stats'] = static function (TransferStats $transfer) use (&$stats): void {
                $stats = [
                    'url' => (string) $transfer->getEffectiveUri(),
                    'downloaded' => $transfer->getHandlerStat('size_download'),
                    'error' => $transfer->getHandlerErrorData(),
                    'primary_ip' => $transfer->getHandlerStat('primary_ip'),
                ];
            };
            $options['handler'] = new CurlHandler;

            try {
                (new Client)->request('GET', $url, $options);
            } catch (Throwable $exception) {
                $failure = $exception;
            }
            $status = $this->awaitOwnedJson($root, $token, 'status.json', $process, 5);
        } catch (Throwable $exception) {
            $harnessFailure = $exception;
        } finally {
            if (is_resource($process)) {
                $state = proc_get_status($process);
                $processState = ['pid' => $state['pid'], 'running' => $state['running'], 'exitcode' => $state['exitcode']];
                if ($state['running']) {
                    proc_terminate($process);
                }
                foreach ($pipes as $descriptor => $pipe) {
                    if (is_resource($pipe)) {
                        $diagnostics[$descriptor] = stream_get_contents($pipe, 16384);
                        fclose($pipe);
                    }
                }
                $exitCode = proc_close($process);
            }
        }

        if ($harnessFailure !== null) {
            throw new AssertionFailedError($harnessFailure->getMessage()."\nOwned server diagnostics: ".json_encode([
                'process' => $processState, 'close_exitcode' => $exitCode,
                'stdout' => $diagnostics[1] ?? null, 'stderr' => $diagnostics[2] ?? null,
            ], JSON_INVALID_UTF8_SUBSTITUTE), 0, $harnessFailure);
        }

        $this->assertSame('', $diagnostics[1] ?? null, 'Unexpected owned server stdout.');
        $this->assertSame('', $diagnostics[2] ?? null, 'Unexpected owned server stderr.');
        $this->assertIsArray($status);
        $this->assertSame(262144, $status['intended_bytes'] ?? null);
        $this->assertNull($status['error'] ?? null, 'Owned server failed: '.json_encode($status));
        $this->assertNotNull($failure, 'Oversized native transfer must fail.');
        $this->assertIsArray($stats, 'Native cURL must reach on_stats; server status: '.json_encode($status));
        $this->assertSame($url, $stats['url']);
        $this->assertSame('127.0.0.1', $stats['primary_ip']);
        $this->assertIsNumeric($stats['downloaded']);
        $this->assertGreaterThanOrEqual(65536, (float) $stats['downloaded'], 'Probe must reach the cap before abort.');
        $this->assertLessThan(262144, (float) $stats['downloaded'], 'A final-body rejection after full download does not satisfy the cap.');
        $this->assertIsInt($stats['error']);
        $this->assertSame(CURLE_ABORTED_BY_CALLBACK, $stats['error'], 'Expected deliberate cap abort; server status: '.json_encode($status));
        Http::assertSentCount(1);
    }

    /** @return array<string, mixed> */
    private function verifiedTransportOptions(): array
    {
        $fixture = SnsMessageFixture::create($this->testEnvironment->path);
        config(['services.ses.allowed_topic_arns' => [SnsMessageFixture::TOPIC_ARN]]);
        SnsMessageFixture::bindResolver($this->app);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $options = null;
        Http::fake(function (ClientRequest $request, array $captured) use ($fixture, &$options) {
            $this->assertSame(SnsMessageFixture::CERT_URL, $request->url());
            $options = $captured;

            return Http::response($fixture->certificate(), 200);
        });
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], SnsMessageFixture::raw($fixture->notification()));
        $this->assertTrue($this->app->make(SesWebhookHandler::class)->verifySignature($request));
        $this->assertIsArray($options);
        $this->assertTrue($options['verify'] ?? false);
        $this->assertFalse($options['allow_redirects'] ?? true);
        $this->assertFalse($options['stream'] ?? true);
        $this->assertSame('', $options['proxy'] ?? null);
        $this->assertSame('', $options['curl'][CURLOPT_PROXY] ?? null);
        $this->assertSame('*', $options['curl'][CURLOPT_NOPROXY] ?? null);
        $this->assertSame(CURL_IPRESOLVE_V4, $options['curl'][CURLOPT_IPRESOLVE] ?? null);
        $this->assertIsCallable($options['on_headers'] ?? null);

        return $options;
    }

    /** @return array<string, string> */
    private function childEnvironment(): array
    {
        $temporary = realpath(sys_get_temp_dir());
        $this->assertIsString($temporary);
        $this->assertSame($temporary, realpath(dirname($this->testEnvironment->path)));
        $environment = [
            'APP_ENV' => 'testing', 'APP_MAINTENANCE_DRIVER' => 'file', 'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null', 'CACHE_STORE' => 'array', 'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array', 'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false', 'LARAVEL_CLOUD' => '0', 'STABILIZATION_SNS_NATIVE_TRANSPORT' => '1',
            'TEMP' => $temporary, 'TMP' => $temporary, 'TMPDIR' => $temporary,
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $systemRoot = realpath((string) getenv('SystemRoot'));
            $this->assertIsString($systemRoot);
            $this->assertSame('windows', strtolower(basename($systemRoot)));
            $this->assertDirectoryExists($systemRoot.DIRECTORY_SEPARATOR.'System32');
            $environment['SystemRoot'] = $systemRoot;
        }

        return $environment;
    }

    private function assertOwnedRoot(string $root, string $token): void
    {
        $this->assertMatchesRegularExpression('/\Asns-transport-[a-f0-9]{32}\z/', basename($root));
        $this->assertFalse(is_link($root));
        $this->assertSame(realpath($this->testEnvironment->path), realpath(dirname($root)));
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($this->testEnvironment->path)));
        $this->assertFalse(is_link($root.'/.sns-transport-owner'));
        $this->assertSame($token, file_get_contents($root.'/.sns-transport-owner'));
    }

    /** @param resource $process
     * @return array<string, mixed>
     */
    private function awaitOwnedJson(string $root, string $token, string $name, $process, int $seconds): array
    {
        $this->assertContains($name, ['ready.json', 'status.json']);
        $deadline = microtime(true) + $seconds;
        $path = $root.'/'.$name;
        do {
            $this->assertOwnedRoot($root, $token);
            clearstatcache(true, $path);
            if (is_file($path)) {
                $this->assertFalse(is_link($path));
                $stat = lstat($path);
                $this->assertSame(1, $stat['nlink']);
                $payload = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
                $this->assertSame($token, $payload['owner'] ?? null);

                return $payload;
            }
            $state = proc_get_status($process);
            $this->assertTrue($state['running'], 'Owned server exited without '.$name.'; exitcode='.$state['exitcode'].'; pid='.$state['pid'].'.');
            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->fail('Owned loopback server deadline exceeded awaiting '.$name.'.');
    }

    private function serverCode(): string
    {
        return <<<'PHP'
declare(strict_types=1);
$required = [
    'APP_ENV' => 'testing', 'APP_MAINTENANCE_DRIVER' => 'file', 'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null', 'CACHE_STORE' => 'array', 'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array', 'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false', 'LARAVEL_CLOUD' => '0', 'STABILIZATION_SNS_NATIVE_TRANSPORT' => '1',
];
foreach ($required as $key => $expected) {
    $actual = getenv($key);
    $emptyDatabaseUrlOmitted = $key === 'DB_URL' && $actual === false;
    if ($actual !== $expected && ! $emptyDatabaseUrlOmitted) {
        throw new RuntimeException('Explicit safe server environment required: '.$key.'.');
    }
    foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
        if ($value !== null && $value !== $expected) {
            throw new RuntimeException('Conflicting server environment: '.$key.'.');
        }
    }
}
if (count($argv) !== 4 || preg_match('/\A[a-f0-9]{32}\z/', $argv[2]) !== 1) {
    throw new RuntimeException('Explicit owned server arguments required; argc='.count($argv).'.');
}
[$script, $root, $owner, $environmentClass] = $argv;
$rootReal = realpath($root);
$parent = realpath(dirname($root));
$temporary = realpath(sys_get_temp_dir());
foreach (['TEMP', 'TMP', 'TMPDIR'] as $key) {
    if (! is_string(getenv($key)) || realpath(getenv($key)) !== $temporary) {
        throw new RuntimeException('Explicit canonical server temporary directory required.');
    }
}
if (PHP_OS_FAMILY === 'Windows') {
    $systemRoot = realpath((string) getenv('SystemRoot'));
    if ($systemRoot === false || strtolower(basename($systemRoot)) !== 'windows' || ! is_dir($systemRoot.DIRECTORY_SEPARATOR.'System32')) {
        throw new RuntimeException('Explicit valid Windows infrastructure directory required.');
    }
}
if ($rootReal === false || $parent === false || is_link($root) || is_link(dirname($root))
    || basename($rootReal) !== 'sns-transport-'.$owner
    || preg_match('/\Astart-kit-tests-[a-f0-9]{32}\z/', basename($parent)) !== 1
    || $temporary === false || realpath(dirname($parent)) !== $temporary
    || is_link($root.'/.sns-transport-owner') || ! is_file($root.'/.sns-transport-owner')
    || file_get_contents($root.'/.sns-transport-owner') !== $owner
    || ! is_file($environmentClass) || is_link($environmentClass) || basename($environmentClass) !== 'TestEnvironment.php') {
    throw new RuntimeException('Owned loopback server guard failed.');
}
require $environmentClass;
Tests\TestEnvironment::assertSafeEnvironment();
$publish = static function (string $name, array $data) use ($root, $owner): void {
    if (! in_array($name, ['ready.json', 'status.json'], true) || is_link($root)
        || file_get_contents($root.'/.sns-transport-owner') !== $owner) {
        throw new RuntimeException('Server publication guard failed.');
    }
    $temporary = $root.'/'.$name.'.tmp';
    $target = $root.'/'.$name;
    if (file_exists($temporary) || is_link($temporary) || file_exists($target) || is_link($target)) {
        throw new RuntimeException('Server publication already exists.');
    }
    $handle = fopen($temporary, 'x');
    if ($handle === false) {
        throw new RuntimeException('Cannot publish owned server status.');
    }
    try {
        $json = json_encode(['owner' => $owner] + $data, JSON_THROW_ON_ERROR);
        if (fwrite($handle, $json) !== strlen($json)) {
            throw new RuntimeException('Incomplete server publication.');
        }
    } finally {
        fclose($handle);
    }
    if (! rename($temporary, $target)) {
        throw new RuntimeException('Cannot finalize owned server status.');
    }
};
$server = null;
$connection = null;
$written = 0;
$error = null;
$writeFailure = null;
$deadline = microtime(true) + 15;
try {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $message);
    if ($server === false) {
        throw new RuntimeException('Cannot bind owned loopback server.');
    }
    $address = stream_socket_get_name($server, false);
    if (! is_string($address) || preg_match('/\A127\.0\.0\.1:([0-9]+)\z/', $address, $match) !== 1) {
        throw new RuntimeException('Unexpected loopback bind address.');
    }
    $port = (int) $match[1];
    $publish('ready.json', ['port' => $port, 'pid' => getmypid()]);
    $connection = stream_socket_accept($server, 8, $peer);
    if ($connection === false || ! is_string($peer) || ! str_starts_with($peer, '127.0.0.1:')) {
        throw new RuntimeException('Owned server accepts only loopback peer.');
    }
    stream_set_timeout($connection, 1);
    $request = '';
    while (! str_contains($request, "\r\n\r\n") && strlen($request) < 4096 && microtime(true) < $deadline) {
        $part = fread($connection, 512);
        if ($part === false || $part === '') {
            throw new RuntimeException('Incomplete owned server request.');
        }
        $request .= $part;
    }
    if (! preg_match('/\AGET \/probe HTTP\/1\.[01]\r\n/', $request)
        || ! str_contains($request, "\r\nHost: 127.0.0.1:".$port."\r\n")
        || ! str_ends_with($request, "\r\n\r\n")) {
        throw new RuntimeException('Only one literal owned loopback GET is accepted.');
    }
    $write = static function (string $bytes) use ($connection, $deadline, &$writeFailure): bool {
        while ($bytes !== '' && microtime(true) < $deadline) {
            error_clear_last();
            $count = @fwrite($connection, $bytes);
            if ($count === false || $count === 0) {
                $writeFailure = error_get_last()['message'] ?? 'Peer stopped accepting response bytes.';
                if (stream_get_meta_data($connection)['timed_out']) {
                    throw new RuntimeException('Owned server response write timed out.');
                }

                return false;
            }
            $bytes = substr($bytes, $count);
        }

        if ($bytes !== '') {
            throw new RuntimeException('Owned server response write deadline exceeded.');
        }

        return true;
    };
    if (! $write("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\nContent-Type: application/octet-stream\r\nConnection: close\r\n\r\n")) {
        throw new RuntimeException('Cannot write owned server response headers.');
    }
    $chunk = str_repeat('S', 4096);
    while ($written < 262144 && microtime(true) < $deadline) {
        if (! $write(dechex(strlen($chunk))."\r\n".$chunk."\r\n")) {
            break;
        }
        $written += strlen($chunk);
        usleep(20000);
    }
    if ($written === 262144) {
        $write("0\r\n\r\n");
    } elseif (microtime(true) >= $deadline) {
        throw new RuntimeException('Owned server chunk deadline exceeded.');
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
} finally {
    if (is_resource($connection)) {
        fclose($connection);
    }
    if (is_resource($server)) {
        fclose($server);
    }
    $publish('status.json', ['intended_bytes' => 262144, 'written_bytes' => $written, 'write_failure' => $writeFailure, 'error' => $error]);
}
PHP;
    }
}
