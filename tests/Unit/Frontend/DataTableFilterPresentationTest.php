<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class DataTableFilterPresentationTest extends TestCase
{
    public function test_filter_labels_preserve_context_zero_false_and_query_metadata(): void
    {
        if (getenv('DATATABLE_NODE_TESTS') !== '1') {
            $this->markTestSkipped('Opt in with DATATABLE_NODE_TESTS=1 and DATATABLE_NODE_BINARY.');
        }

        $binary = getenv('DATATABLE_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('DATATABLE_NODE_BINARY must be an absolute installed Node executable.');
        }

        $root = dirname(__DIR__, 3);
        foreach (['typescript-loader.mjs', 'datatable-filter-presentation.mjs'] as $fixture) {
            $this->assertFileExists($root.'/tests/Fixtures/Frontend/'.$fixture);
            $this->assertFalse(is_link($root.'/tests/Fixtures/Frontend/'.$fixture));
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
            './tests/Fixtures/Frontend/datatable-filter-presentation.mjs',
        ], $root, $environment, null, 20);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $result = json_decode(trim($process->getOutput()), true, 8, JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $result['status']);
        $this->assertSame(0, $result['network_calls']);
    }
}
