<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class DataTableLayoutTest extends TestCase
{
    public function test_width_order_and_responsive_pinning_use_the_real_algorithms(): void
    {
        if (getenv('DATATABLE_NODE_TESTS') !== '1') {
            $this->markTestSkipped('Set DATATABLE_NODE_TESTS=1 and DATATABLE_NODE_BINARY for isolated layout contracts.');
        }

        $binary = getenv('DATATABLE_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('DATATABLE_NODE_BINARY must be an absolute installed Node executable.');
        }

        $environment = [];
        foreach ((getenv() ?: []) + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'TMPDIR'], true) && is_string($value) ? $value : false;
            }
        }
        $process = new Process([$binary, '--import', './tests/Fixtures/Frontend/typescript-loader.mjs', 'tests/Fixtures/Frontend/datatable-layout.mjs'], dirname(__DIR__, 3), $environment);
        $process->setTimeout(20);
        try {
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame('', $process->getErrorOutput());
            $result = json_decode(trim($process->getOutput()), true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame('passed', $result['status']);
            $this->assertSame(15, $result['checks']);
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }
}
