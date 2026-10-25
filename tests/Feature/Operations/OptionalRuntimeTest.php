<?php

namespace Tests\Feature\Operations;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\AuthorityHttpEnvironment;
use Tests\DisposableMysql;

class OptionalRuntimeTest extends TestCase
{
    public function test_four_optional_modes_render_hydrate_and_deliver_only_to_owned_private_channels(): void
    {
        if (getenv('OPS004_RUNTIME_TESTS') !== '1') {
            $this->markTestSkipped('Optional runtime E2E requires its explicit disposable native profile.');
        }
        $root = dirname(__DIR__, 3);
        require_once $root.'/tests/optional-runtime-bootstrap.php';
        \optionalRuntimeAssertCopy();
        foreach (['home', 'appdata', 'localappdata', 'npm-cache'] as $directory) {
            (new Filesystem)->ensureDirectoryExists($root.'/runtime/'.$directory);
        }
        file_put_contents($root.'/runtime/empty.npmrc', '');
        $node = $this->binary('OPS004_NODE_BINARY');
        $this->binary('OPS004_BROWSER_BINARY');
        $module = $this->binary('OPS004_PLAYWRIGHT_MODULE');
        $npm = $this->binary('OPS004_NPM_CLI');
        $reports = getenv('OPS004_REPORT_DIRECTORY');
        if (! is_string($reports) || ! is_dir($reports) || is_link($reports)
            || ! str_starts_with(realpath($reports), realpath($root).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Reports must be an existing owned source-copy directory.');
        }
        $host = AuthorityHttpEnvironment::requiredHost();
        $mysql = new DisposableMysql;
        $http = $reverb = $ssr = null;
        $manifest = null;
        $results = [];
        try {
            $mysql->start();
            foreach (['http-sessions', 'http-environments', 'http-public'] as $directory) {
                (new Filesystem)->ensureDirectoryExists($mysql->path.'/'.$directory);
            }
            $manifest = ['settings' => $mysql->settings(), 'host' => $host, 'port' => $this->port(), 'mode' => 'authority'];
            $manifestPath = $mysql->path.'/http.json';
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
            AuthorityHttpEnvironment::readManifest($manifestPath);
            $seed = $this->process([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=',
                'tests/optional-runtime-worker.php', 'seed', $manifestPath, $mysql->path.'/optional-runtime.json'],
                ['OPS004_RUNTIME_TESTS' => '1']);
            $seed->setTimeout(90);
            $seed->run();
            $this->assertSame(0, $seed->getExitCode(), $seed->getErrorOutput());
            $actors = json_decode($seed->getOutput(), true, 16, JSON_THROW_ON_ERROR);
            $this->assertCount(2, $actors);
            $state = ['token' => $manifest['settings']['token'], 'actors' => $actors, 'ssr' => false, 'realtime' => false,
                'ssr_port' => $this->port(), 'reverb_port' => $this->port()];
            $statePath = $mysql->path.'/optional-runtime.json';
            $this->state($statePath, $state);
            $flags = ['OPS004_RUNTIME_TESTS' => '1', 'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1',
                'AUTHORITY_HTTP_HOST' => $host, 'AUTHORITY_HTTP_MANIFEST' => $manifestPath, 'OPS004_RUNTIME_STATE' => $statePath];
            $http = $this->process([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=', '-S', '127.0.0.1:'.$manifest['port'],
                '-t', $mysql->path.'/http-public', $root.'/tests/optional-runtime-router.php'], $flags);
            $http->start();
            $origin = 'http://'.$host.':'.$manifest['port'];
            $this->ready($http, '127.0.0.1', $manifest['port']);
            foreach ([false, true] as $realtime) {
                $state['realtime'] = $realtime;
                $this->state($statePath, $state);
                $build = $this->process([$node, $npm, 'run', 'build:ssr'], [
                    'OPS004_ARTISAN_SAFE' => '1', 'VITE_REVERB_ENABLED' => $realtime ? 'true' : 'false',
                    'VITE_REVERB_APP_KEY' => 'ops004-synthetic-key', 'VITE_REVERB_HOST' => '127.0.0.1',
                    'VITE_REVERB_PORT' => (string) $state['reverb_port'], 'VITE_REVERB_SCHEME' => 'http',
                ]);
                $build->setTimeout(180);
                $build->run();
                file_put_contents($reports.'/build-'.(int) $realtime.'.out', $build->getOutput());
                file_put_contents($reports.'/build-'.(int) $realtime.'.err', $build->getErrorOutput());
                $this->assertSame(0, $build->getExitCode(), $build->getErrorOutput());
                if ($realtime) {
                    $reverb = $this->process([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=',
                        'tests/optional-runtime-worker.php', 'reverb', $manifestPath, $statePath], $flags);
                    $reverb->start();
                    $this->ready($reverb, '127.0.0.1', $state['reverb_port']);
                }
                foreach ([false, true] as $withSsr) {
                    $state['ssr'] = $withSsr;
                    $this->state($statePath, $state);
                    $mode = 'ssr'.(int) $withSsr.'-realtime'.(int) $realtime;
                    file_put_contents($reports.'/progress.json', json_encode(['mode' => $mode, 'stage' => 'starting'], JSON_THROW_ON_ERROR));
                    if ($withSsr) {
                        $ssr = $this->process([$node, '--import', './tests/Fixtures/Frontend/optional-ssr-loopback.mjs', 'bootstrap/ssr/app.js'],
                            ['OPS004_SSR_PORT' => (string) $state['ssr_port']]);
                        $ssr->start();
                        $this->ready($ssr, '127.0.0.1', $state['ssr_port']);
                    }
                    $browser = $this->process([$node, 'tests/Fixtures/Frontend/optional-runtime-browser.mjs',
                        $manifestPath, $statePath, $reports.'/'.$mode.'.json'], $flags + ['OPS004_PLAYWRIGHT_MODULE' => $module]);
                    $browser->setTimeout(150);
                    $browser->run();
                    file_put_contents($reports.'/'.$mode.'.out', $browser->getOutput());
                    file_put_contents($reports.'/'.$mode.'.err', $browser->getErrorOutput());
                    if ($ssr !== null) {
                        $ssr->stop(3);
                        file_put_contents($reports.'/'.$mode.'-ssr.out', $ssr->getOutput());
                        file_put_contents($reports.'/'.$mode.'-ssr.err', $ssr->getErrorOutput());
                        $this->assertFalse($ssr->isRunning());
                        $ssr = null;
                    }
                    $this->assertSame(0, $browser->getExitCode(), $browser->getOutput().' '.$browser->getErrorOutput());
                    $result = json_decode(file_get_contents($reports.'/'.$mode.'.json'), true, 32, JSON_THROW_ON_ERROR);
                    $this->assertSame('passed', $result['status']);
                    $results[] = $result;
                }
                if ($reverb !== null) {
                    $reverb->stop(3);
                    file_put_contents($reports.'/reverb.out', $reverb->getOutput());
                    file_put_contents($reports.'/reverb.err', $reverb->getErrorOutput());
                    $this->assertFalse($reverb->isRunning());
                    $reverb = null;
                }
            }
            $this->assertCount(4, $results);
            file_put_contents($reports.'/http-audit.jsonl', file_get_contents($mysql->path.'/optional-http-audit.jsonl'));
            file_put_contents($reports.'/matrix.json', json_encode($results, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } finally {
            foreach ([$ssr, $reverb, $http] as $process) {
                if ($process !== null && $process->isRunning()) {
                    $process->stop(3);
                }
            }
            if ($manifest !== null && is_file($manifest['settings']['path'].'/optional-exceptions.jsonl')) {
                copy($manifest['settings']['path'].'/optional-exceptions.jsonl', $reports.'/exceptions.jsonl');
            }
            if ($http !== null) {
                file_put_contents($reports.'/http.out', $http->getOutput());
                file_put_contents($reports.'/http.err', $http->getErrorOutput());
            }
            if ($manifest !== null) {
                AuthorityHttpEnvironment::cleanUpEnvironments($manifest);
            }
            $mysql->cleanUp();
        }
    }

    private function binary(string $name): string
    {
        $path = getenv($name);
        if (! is_string($path) || ! is_file($path) || is_link($path) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $path) !== 1) {
            throw new RuntimeException('An explicit installed file is required: '.$name);
        }

        return $path;
    }

    private function port(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        if ($socket === false) {
            throw new RuntimeException('Cannot allocate an owned loopback port.');
        }
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    /** @param array<string, mixed> $state */
    private function state(string $path, array $state): void
    {
        file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));
    }

    /** @param list<string> $command @param array<string, string> $extra */
    private function process(array $command, array $extra = []): Process
    {
        $environment = [];
        foreach (getenv() + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = false;
            }
        }
        foreach (['SystemRoot', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'TMPDIR', 'PHP_INI_SCAN_DIR',
            'OPS004_SOURCE_TOKEN', 'OPS004_NODE_BINARY', 'OPS004_BROWSER_BINARY', 'OPS004_PLAYWRIGHT_MODULE', 'AUTHORITY_MYSQLD_BINARY', 'AUTHORITY_HTTP_HOST'] as $key) {
            $value = getenv($key);
            if (is_string($value)) {
                $environment[$key] = $value;
            }
        }
        $runtime = dirname(__DIR__, 3).'/runtime';
        $environment = array_replace($environment, ['HOME' => $runtime.'/home', 'USERPROFILE' => $runtime.'/home',
            'APPDATA' => $runtime.'/appdata', 'LOCALAPPDATA' => $runtime.'/localappdata',
            'NPM_CONFIG_USERCONFIG' => $runtime.'/empty.npmrc', 'NPM_CONFIG_CACHE' => $runtime.'/npm-cache',
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)), 'APP_DEBUG' => 'false', 'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'INERTIA_SSR_ENABLED' => 'false', 'EXTERNAL_APIS_FAKE' => 'true',
            'SLACK_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'PULSE_ENABLED' => 'false', 'DEBUGBAR_ENABLED' => 'false',
            'AUTHORITY_MYSQL_TESTS' => '1', 'AUTHORITY_HTTP_TESTS' => '1', 'OPS004_PHP_BINARY' => PHP_BINARY], $extra);
        $process = new Process($command, dirname(__DIR__, 3), $environment);
        $process->setTimeout(null);

        return $process;
    }

    private function ready(Process $process, string $host, int $port): void
    {
        $deadline = microtime(true) + 30;
        do {
            if (! $process->isRunning()) {
                throw new RuntimeException('Owned server exited before readiness: '.$process->getOutput().' '.$process->getErrorOutput());
            }
            $socket = @stream_socket_client('tcp://'.$host.':'.$port, $code, $message, 0.2);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Owned server readiness timed out.');
    }
}
