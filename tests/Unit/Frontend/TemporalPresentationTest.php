<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class TemporalPresentationTest extends TestCase
{
    #[DataProvider('timezones')]
    public function test_actual_date_helpers_use_one_local_calendar_for_group_keys_and_labels(string $timezone): void
    {
        if (getenv('REL005_NODE_TESTS') !== '1') {
            $this->markTestSkipped('Opt in with REL005_NODE_TESTS=1 and REL005_NODE_BINARY for isolated date-runtime checks.');
        }
        $binary = getenv('REL005_NODE_BINARY');
        if (! is_string($binary) || preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $binary) !== 1 || ! is_file($binary) || is_link($binary)) {
            throw new RuntimeException('REL005_NODE_BINARY must be an absolute installed Node executable.');
        }
        $root = dirname(__DIR__, 3);
        foreach (['tests/Fixtures/Frontend/typescript-loader.mjs', 'tests/Fixtures/Frontend/temporal-presentation.mjs'] as $file) {
            $this->assertFileExists($root.'/'.$file);
            $this->assertFalse(is_link($root.'/'.$file));
        }
        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'TMPDIR'];
        foreach ((getenv() ?: []) + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }
        $environment['TZ'] = $timezone;
        $process = new Process([
            $binary, '--import', './tests/Fixtures/Frontend/typescript-loader.mjs',
            'tests/Fixtures/Frontend/temporal-presentation.mjs', $timezone,
        ], $root, $environment);
        $process->setTimeout(20);
        try {
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame('', $process->getErrorOutput());
            $result = json_decode(trim($process->getOutput()), true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame('passed', $result['status']);
            $this->assertSame($timezone, $result['timezone']);
            $this->assertSame(7, $result['cases']);
            $this->assertSame(0, $result['network_calls']);
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }

    /** @return array<string, array{string}> */
    public static function timezones(): array
    {
        return [
            'UTC' => ['UTC'], 'São Paulo' => ['America/Sao_Paulo'],
            'New York' => ['America/New_York'], 'Tokyo' => ['Asia/Tokyo'],
        ];
    }
}
