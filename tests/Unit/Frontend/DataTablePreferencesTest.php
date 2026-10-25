<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class DataTablePreferencesTest extends TestCase
{
    public function test_real_preference_helpers_preserve_owned_layout_and_reject_obsolete_or_invalid_writes(): void
    {
        if (getenv('DATATABLE_NODE_TESTS') !== '1') {
            $this->markTestSkipped('Opt in with DATATABLE_NODE_TESTS=1 and DATATABLE_NODE_BINARY for isolated preference contracts.');
        }

        $binary = getenv('DATATABLE_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('DATATABLE_NODE_BINARY must be an absolute installed Node executable.');
        }

        $root = dirname(__DIR__, 3);
        foreach (['tests/Fixtures/Frontend/typescript-loader.mjs', 'tests/Fixtures/Frontend/datatable-preferences.mjs'] as $relative) {
            $this->assertFileExists($root.'/'.$relative);
            $this->assertFalse(is_link($root.'/'.$relative));
        }

        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'TMPDIR'];
        foreach ((getenv() ?: []) + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }

        $process = new Process([
            $binary, '--import', './tests/Fixtures/Frontend/typescript-loader.mjs',
            'tests/Fixtures/Frontend/datatable-preferences.mjs',
        ], $root, $environment);
        $process->setTimeout(20);

        try {
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame('', $process->getErrorOutput());
            $result = json_decode(trim($process->getOutput()), true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame('passed', $result['status']);
            $this->assertSame(9, $result['cases']);
            $this->assertSame(0, $result['network_calls']);
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }
}
