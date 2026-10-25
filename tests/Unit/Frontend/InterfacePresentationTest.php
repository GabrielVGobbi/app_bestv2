<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class InterfacePresentationTest extends TestCase
{
    public function test_shared_header_uses_optional_subtitle_and_accessible_navigation(): void
    {
        if (getenv('DATATABLE_NODE_TESTS') !== '1') {
            $this->markTestSkipped('Requires DATATABLE_NODE_TESTS=1 and DATATABLE_NODE_BINARY.');
        }

        $binary = getenv('DATATABLE_NODE_BINARY');
        if (! is_string($binary) || ! is_file($binary) || is_link($binary) || ! preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary)) {
            throw new RuntimeException('DATATABLE_NODE_BINARY must be an absolute installed Node executable.');
        }

        $environment = [];
        foreach ((getenv() ?: []) + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP'], true) && is_string($value) ? $value : false;
            }
        }

        $process = new Process([$binary, '--import', './tests/Fixtures/Frontend/typescript-loader.mjs', 'tests/Fixtures/Frontend/interface-presentation.mjs'], dirname(__DIR__, 3), $environment);
        $process->setTimeout(30);
        try {
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame('', $process->getErrorOutput());
            $this->assertSame('passed', json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR)['status']);
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }
}
