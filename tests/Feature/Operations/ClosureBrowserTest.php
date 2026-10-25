<?php

namespace Tests\Feature\Operations;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\AuthorityHttpEnvironment;
use Tests\DisposableMysql;

class ClosureBrowserTest extends TestCase
{
    public function test_real_forms_and_web_deletes_preserve_json_and_inertia_contracts(): void
    {
        if (getenv('ARC002_BROWSER_TESTS') !== '1') {
            $this->markTestSkipped('ARC browser requires its explicit disposable profile.');
        }
        $root = dirname(__DIR__, 3);
        require_once $root.'/tests/closure-browser-bootstrap.php';
        \optionalRuntimeAssertCopy();
        $node = $this->binary('OPS004_NODE_BINARY');
        $this->binary('OPS004_BROWSER_BINARY');
        $this->binary('OPS004_PLAYWRIGHT_MODULE');
        $reports = getenv('ARC002_BROWSER_REPORT_DIRECTORY') ?: getenv('OPS004_REPORT_DIRECTORY');
        if (! is_string($reports) || ! is_dir($reports) || is_link($reports)
            || ! str_starts_with(realpath($reports), realpath($root).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Closure reports must stay in an existing owned source-copy directory.');
        }
        if (! is_file($root.'/public/build/manifest.json')) {
            throw new RuntimeException('The real application build is required before the closure browser profile.');
        }
        $host = AuthorityHttpEnvironment::requiredHost();
        $mysql = new DisposableMysql;
        $manifest = null;
        $server = null;
        try {
            $mysql->start();
            foreach (['http-sessions', 'http-environments', 'http-public'] as $directory) {
                (new Filesystem)->ensureDirectoryExists($mysql->path.'/'.$directory);
            }
            $manifest = ['settings' => $mysql->settings(), 'host' => $host, 'port' => $this->port(), 'mode' => 'authority'];
            $manifestPath = $mysql->path.'/http.json';
            $fixturePath = $mysql->path.'/closure-browser.json';
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
            AuthorityHttpEnvironment::readManifest($manifestPath);
            $flags = ['AUTHORITY_HTTP_MANIFEST' => $manifestPath, 'ARC002_BROWSER_FIXTURE' => $fixturePath];
            $seed = $this->process([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=',
                'tests/closure-browser-worker.php', 'seed', $manifestPath, $fixturePath], $flags);
            $seed->setTimeout(90);
            $seed->run();
            $this->assertSame(0, $seed->getExitCode(), $seed->getOutput().$seed->getErrorOutput());
            $this->assertTrue(json_decode(trim($seed->getOutput()), true, 8, JSON_THROW_ON_ERROR)['seeded']);
            \closureBrowserInput($manifestPath, $fixturePath);
            $server = $this->process([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=',
                '-S', '127.0.0.1:'.$manifest['port'], '-t', $mysql->path.'/http-public', $root.'/tests/closure-browser-router.php'], $flags);
            $server->start();
            $this->ready($server, $manifest['port']);
            $browser = $this->process([$node, 'tests/Fixtures/Frontend/closure-browser.mjs',
                $manifestPath, $fixturePath, $reports.'/browser.json'], $flags);
            $browser->setTimeout(150);
            $browser->run();
            file_put_contents($reports.'/browser.out', $browser->getOutput());
            file_put_contents($reports.'/browser.err', $browser->getErrorOutput());
            $this->assertSame(0, $browser->getExitCode(), $browser->getOutput().$browser->getErrorOutput());
            $result = json_decode(file_get_contents($reports.'/browser.json'), true, 64, JSON_THROW_ON_ERROR);
            $this->assertSame('passed', $result['status']);
            $this->assertCount(12, $result['checks']);
            $this->assertSame(0, $result['allowedExternalRequests']);
            $this->assertSame([], $result['websockets']);
            $this->assertSame([], $result['cleanupErrors']);
            $verification = $this->process([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=',
                'tests/closure-browser-worker.php', 'verify', $manifestPath, $fixturePath], $flags);
            $verification->setTimeout(30);
            $verification->run();
            file_put_contents($reports.'/ledger.out', $verification->getOutput());
            file_put_contents($reports.'/ledger.err', $verification->getErrorOutput());
            $this->assertSame(0, $verification->getExitCode(), $verification->getOutput().$verification->getErrorOutput());
            $ledger = json_decode(trim($verification->getOutput()), true, 16, JSON_THROW_ON_ERROR);
            $this->assertCount(5, $ledger);
            foreach ($ledger as $name => $value) {
                $this->assertTrue($value, $name);
            }
            $audit = array_map(fn (string $row): array => json_decode($row, true, 16, JSON_THROW_ON_ERROR),
                file($mysql->path.'/closure-http-audit.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            $this->assertNotEmpty($audit);
            foreach ($audit as $row) {
                $this->assertTrue($row['http_guard'] && $row['mail_fake'] && $row['queue_fake']);
                $this->assertFalse($row['ssr']);
                $this->assertSame('null', $row['broadcasting']);
            }
        } finally {
            if ($server !== null && $server->isRunning()) {
                $server->stop(3);
            }
            if ($server !== null) {
                file_put_contents($reports.'/http.out', $server->getOutput());
                file_put_contents($reports.'/http.err', $server->getErrorOutput());
                $this->assertFalse($server->isRunning());
            }
            if ($manifest !== null) {
                foreach (['closure-http-audit.jsonl', 'closure-exceptions.jsonl'] as $name) {
                    if (is_file($mysql->path.'/'.$name)) {
                        copy($mysql->path.'/'.$name, $reports.'/'.$name);
                    }
                }
                AuthorityHttpEnvironment::cleanUpEnvironments($manifest);
            }
            $mysql->cleanUp();
        }
    }

    private function binary(string $name): string
    {
        $path = getenv($name);
        if (! is_string($path) || ! is_file($path) || is_link($path)
            || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $path) !== 1) {
            throw new RuntimeException('An explicit installed file is required: '.$name);
        }

        return $path;
    }

    private function port(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        if ($socket === false) {
            throw new RuntimeException('Cannot allocate the owned HTTP port.');
        }
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    /** @param list<string> $command @param array<string, string> $extra */
    private function process(array $command, array $extra = []): Process
    {
        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'TMPDIR', 'PHP_INI_SCAN_DIR',
            'OPS004_SOURCE_TOKEN', 'OPS004_NODE_BINARY', 'OPS004_BROWSER_BINARY', 'OPS004_PLAYWRIGHT_MODULE', 'AUTHORITY_MYSQLD_BINARY', 'AUTHORITY_HTTP_HOST'];
        foreach (getenv() + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }
        $environment = array_replace($environment, ['ARC002_BROWSER_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_HTTP_TESTS' => '1',
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)), 'APP_DEBUG' => 'false', 'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'INERTIA_SSR_ENABLED' => 'false', 'SLACK_ENABLED' => 'false', 'EXTERNAL_APIS_FAKE' => 'true',
            'TELESCOPE_ENABLED' => 'false', 'PULSE_ENABLED' => 'false', 'DEBUGBAR_ENABLED' => 'false'], $extra);
        $process = new Process($command, dirname(__DIR__, 3), $environment);
        $process->setTimeout(null);

        return $process;
    }

    private function ready(Process $process, int $port): void
    {
        $deadline = microtime(true) + 30;
        do {
            if (! $process->isRunning()) {
                throw new RuntimeException('Owned HTTP server exited: '.$process->getErrorOutput());
            }
            $socket = @stream_socket_client('tcp://127.0.0.1:'.$port, $code, $message, 0.2);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Owned HTTP readiness timed out.');
    }
}
