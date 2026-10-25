<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class HttpContractTest extends TestCase
{
    public function test_http_mutations_and_pagination_preserve_their_transport_contracts(): void
    {
        if (getenv('ARC002_NODE_TESTS') !== '1' && getenv('DEV_BUILD_NODE_TESTS') !== '1') {
            $this->markTestSkipped('HTTP contract regression requires ARC002_NODE_TESTS=1.');
        }

        $binary = getenv('ARC002_NODE_BINARY') ?: getenv('DEV_BUILD_NODE_BINARY');
        if (! is_string($binary) || ! is_file($binary) || is_link($binary)
            || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1) {
            throw new RuntimeException('Provide the explicit installed Node executable in ARC002_NODE_BINARY.');
        }

        $root = dirname(__DIR__, 3);
        foreach (['typescript-loader.mjs', 'http-contract.mjs'] as $fixture) {
            $path = $root.'/tests/Fixtures/Frontend/'.$fixture;
            $this->assertFileExists($path);
            $this->assertFalse(is_link($path));
            $this->assertSame(1, stat($path)['nlink']);
        }

        $process = new Process([
            $binary, '--import', './tests/Fixtures/Frontend/typescript-loader.mjs',
            './tests/Fixtures/Frontend/http-contract.mjs',
        ], $root, $this->environment(), null, 45);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $observed = json_decode(trim($process->getOutput()), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $observed['status']);
        $this->assertSame(0, $observed['realNetworkRequests']);
        $this->assertCount(13, $observed['checks']);
        $expected = ['pagination-public-aliases-have-the-canonical-contract'];
        foreach (['roles', 'permissions'] as $resource) {
            foreach (['success', 'validation', 'inactive', 'identity-ended-during-request'] as $scenario) {
                $expected[] = $resource.'-edit:'.$scenario;
            }
        }
        foreach (['success-redirect', 'success-callback', 'validation', 'cancel'] as $scenario) {
            $expected[] = 'web-delete:'.$scenario;
        }
        $this->assertSame($expected, array_column($observed['checks'], 'name'));
        foreach ($observed['checks'] as $check) {
            $this->assertSame('passed', $check['status'], $check['name']);
        }
    }

    /** @return array<string, string|false> */
    private function environment(): array
    {
        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'TMPDIR'];
        foreach (getenv() + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }

        return $environment;
    }
}
