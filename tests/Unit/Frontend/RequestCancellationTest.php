<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class RequestCancellationTest extends TestCase
{
    public function test_queries_abort_transport_and_preserve_current_identity_and_filters(): void
    {
        if (getenv('UI005_NODE_TESTS') !== '1') {
            $this->markTestSkipped('The query cancellation contract requires UI005_NODE_TESTS=1.');
        }

        $binary = getenv('UI005_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('Set UI005_NODE_BINARY to an explicit absolute installed Node executable.');
        }

        $root = dirname(__DIR__, 3);
        foreach (['typescript-loader.mjs', 'ui-cancellation-query-contracts.mjs'] as $fixture) {
            $path = $root.'/tests/Fixtures/Frontend/'.$fixture;
            $this->assertFileExists($path);
            $this->assertFalse(is_link($path));
            $metadata = stat($path);
            $this->assertNotFalse($metadata);
            $this->assertSame(1, $metadata['nlink']);
        }

        $process = new Process([
            $binary, '--import', './tests/Fixtures/Frontend/typescript-loader.mjs',
            './tests/Fixtures/Frontend/ui-cancellation-query-contracts.mjs',
        ], $root, $this->nodeEnvironment(), null, 20);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $lines = explode("\n", trim($process->getOutput()));
        $observed = json_decode(array_pop($lines), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $observed['status']);
        $this->assertSame(0, $observed['realNetworkRequests']);
        $this->assertCount(15, $observed['checks']);
        $this->assertCount(15, $lines);
        $expected = [];
        foreach (['uploads-list', 'upload-tags', 'timeline'] as $factory) {
            foreach ([
                'explicit-cancel-aborts-http-and-restores-cache-without-business-error',
                'last-observer-unmount-aborts-and-does-not-warm-stale-cache',
                'new-filters-win-when-old-transport-finishes-last',
                'ended-session-late-success-cannot-enter-new-identity-client',
                'ended-session-late-failure-cannot-enter-new-identity-client',
            ] as $contract) {
                $expected[] = $factory.':'.$contract;
            }
        }

        $this->assertSame($expected, array_column($observed['checks'], 'name'));
        foreach ($observed['checks'] as $index => $check) {
            $this->assertSame('passed', $check['status'], $check['name']);
            $this->assertSame($check, json_decode($lines[$index], true, 16, JSON_THROW_ON_ERROR));
        }
    }

    /** @return array<string, string|false> */
    private function nodeEnvironment(): array
    {
        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA'];
        foreach (getenv() + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $environment['PROMPT'] = '$P$G';
        }

        return $environment;
    }
}
