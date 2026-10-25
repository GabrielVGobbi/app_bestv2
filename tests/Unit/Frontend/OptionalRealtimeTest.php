<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class OptionalRealtimeTest extends TestCase
{
    public function test_ssr_configuration_accepts_explicit_enabled_and_disabled_modes_and_owned_url(): void
    {
        foreach (['false' => false, 'true' => true] as $setting => $expected) {
            $environment = $this->environment();
            $environment['INERTIA_SSR_ENABLED'] = $setting;
            $environment['INERTIA_SSR_URL'] = 'http://127.0.0.1:19437';
            $process = new Process([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=', '-r',
                'require "vendor/autoload.php"; new Illuminate\Foundation\Application(getcwd()); echo json_encode((require "config/inertia.php")["ssr"], JSON_THROW_ON_ERROR);',
            ], dirname(__DIR__, 3), $environment);
            $process->setTimeout(20);
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame($expected, $result['enabled']);
            $this->assertSame('http://127.0.0.1:19437', $result['url']);
        }
    }

    public function test_real_echo_module_is_inert_in_ssr_with_realtime_both_disabled_and_enabled(): void
    {
        if (getenv('OPS004_NODE_TESTS') !== '1' && getenv('DEV_BUILD_NODE_TESTS') !== '1') {
            $this->markTestSkipped('The real Echo SSR import requires OPS004_NODE_TESTS=1.');
        }
        $binary = getenv('OPS004_NODE_BINARY') ?: getenv('DEV_BUILD_NODE_BINARY');
        if (! is_string($binary) || ! is_file($binary) || is_link($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1) {
            throw new RuntimeException('Provide the explicit installed Node executable.');
        }
        $process = new Process([$binary, 'tests/Fixtures/Frontend/optional-realtime-node.mjs'], dirname(__DIR__, 3), $this->environment());
        $process->setTimeout(20);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().' '.$process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $result = json_decode($process->getOutput(), true, 8, JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $result['status']);
        $this->assertSame([['enabled' => false, 'echo' => null], ['enabled' => true, 'echo' => null]], $result['observed']);
        $this->assertSame(0, $result['networkCalls']);
    }

    public function test_optional_runtime_refuses_unmarked_or_unowned_sources_before_launching_processes(): void
    {
        $process = new Process([PHP_BINARY, '-d', 'auto_prepend_file=', '-d', 'auto_append_file=', '-r',
            'require "vendor/autoload.php"; require "tests/optional-runtime-bootstrap.php"; try { optionalRuntimeAssertCopy(); echo "unsafe"; exit(1); } catch (RuntimeException $e) { echo "blocked"; }',
        ], dirname(__DIR__, 3), $this->environment());
        $process->setTimeout(20);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('blocked', $process->getOutput());
    }

    /** @return array<string, string|false> */
    private function environment(): array
    {
        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'TMPDIR', 'PHP_INI_SCAN_DIR'];
        foreach (getenv() + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }

        return $environment;
    }
}
