<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class PrivateStateIsolationTest extends TestCase
{
    public function test_private_scope_transport_and_ssr_are_isolated_using_real_frontend_modules(): void
    {
        if (getenv('SEC020_NODE_TESTS') !== '1') {
            $this->markTestSkipped('The frontend runtime contract requires SEC020_NODE_TESTS=1.');
        }
        $binary = getenv('SEC020_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('Set SEC020_NODE_BINARY to an explicit absolute installed Node executable.');
        }
        $root = dirname(__DIR__, 3);
        foreach (['tests/Fixtures/Frontend/typescript-loader.mjs', 'tests/Fixtures/Frontend/private-state-isolation.mjs'] as $relative) {
            $path = $root.'/'.$relative;
            $this->assertFileExists($path);
            $this->assertFalse(is_link($path));
            $metadata = stat($path);
            $this->assertNotFalse($metadata);
            $this->assertSame(1, $metadata['nlink']);
        }

        $process = new Process([
            $binary, '--import', './tests/Fixtures/Frontend/typescript-loader.mjs',
            'tests/Fixtures/Frontend/private-state-isolation.mjs',
        ], $root, $this->nodeEnvironment());
        $process->setTimeout(20);
        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('Frontend runtime did not finish. Completed phases: '.$process->getOutput().' Diagnostics: '.$process->getErrorOutput(), 0, $exception);
        }

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $lines = explode("\n", trim($process->getOutput()));
        $result = array_pop($lines);
        $this->assertNotSame('', $result, 'The runtime must emit a nonempty final JSON result.');
        $observed = json_decode($result, true, 32, JSON_THROW_ON_ERROR);
        $this->assertIsArray($observed);
        $this->assertSame('passed', $observed['status'], $observed['reason'] ?? 'The real module contracts must complete.');
        $phases = array_map(static fn (string $line): mixed => json_decode($line, true, 8, JSON_THROW_ON_ERROR), $lines);
        $this->assertSame(['initial-imports-ready', 'private-modules-ready', 'epoch-contract-passed', 'storage-escalation-passed', 'transport-contract-passed', 'ssr-first-rendered', 'ssr-contract-passed'], array_column($phases, 'phase'));
        $this->assertSame(4, $observed['adapterCalls']);
        $this->assertSame([101, 202, 101], $observed['ssrIdentities']);
        $this->assertSame([], $observed['unexpectedEnvironment']);
        foreach (['epochAndEligibility', 'partitionedStorageKeys', 'storagePurgeEscalation', 'combinedCancellation', 'delayedResultsDiscarded', 'activeIdentityRecovery', 'ssrPerRequestQueryClient', 'ssrPerRequestZipStore', 'ssrNoBrowserGlobalsOrHttp'] as $contract) {
            $this->assertTrue($observed['contracts'][$contract], $contract);
        }
    }

    /** @return array<string, string|false> */
    private function nodeEnvironment(): array
    {
        $inherited = getenv();
        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA'];
        foreach ($inherited + $_ENV + $_SERVER as $key => $value) {
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
