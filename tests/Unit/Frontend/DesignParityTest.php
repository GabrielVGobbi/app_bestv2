<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class DesignParityTest extends TestCase
{
    public function test_real_application_build_renders_the_start_kit_visual_contract_over_http(): void
    {
        if (getenv('DESIGN_PARITY_BROWSER_TESTS') !== '1') {
            $this->markTestSkipped('Requires the application build, Chrome, and explicit DESIGN_PARITY_BROWSER_TESTS=1.');
        }

        $environment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'LOCALAPPDATA', 'APPDATA', 'USERPROFILE', 'PROGRAMFILES', 'PROGRAMFILES(X86)', 'PROGRAMW6432'];
        foreach ((getenv() ?: []) + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }
        foreach (['DESIGN_PARITY_NODE_BINARY', 'DESIGN_PARITY_PLAYWRIGHT_MODULE'] as $key) {
            $path = getenv($key);
            if (! is_string($path) || ! is_file($path) || ! preg_match('~\A(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
                throw new RuntimeException("{$key} must be an absolute installed path.");
            }
            $environment[$key] = $path;
        }

        $process = new Process([
            $environment['DESIGN_PARITY_NODE_BINARY'],
            'tests/Fixtures/Frontend/design-parity-browser.mjs',
        ], dirname(__DIR__, 3), $environment);
        $process->setTimeout(150);
        try {
            $process->run();
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $report['status']);
        $this->assertGreaterThanOrEqual(20, count($report['checks']));
        $this->assertSame([], $report['errors']);
        $this->assertSame([], $report['blocked']);
        $this->assertSame([], $report['mutations']);
        $this->assertSame(['/login'], $report['syntheticPosts']);
        $this->assertSame(0, $report['backendRequests']);
        $this->assertSame([], $report['darkAudit']['failures']);
        $this->assertTrue($report['darkAudit']['elevated']);
        $this->assertSame('dark', $report['darkAudit']['colorScheme']);
        $this->assertSame([], $report['componentAudit']['failures']);
        $this->assertGreaterThanOrEqual(3, $report['componentAudit']['fieldBorderContrast']);
        $this->assertGreaterThanOrEqual(4.5, $report['componentAudit']['placeholderContrast']);
        $this->assertSame([], $report['loginAudit']['failures']);
        $this->assertSame([], $report['lightAudit']['failures']);
        $this->assertSame([], $report['usersAudit']['failures']);
        $this->assertSame([], $report['createAudit']['failures']);
        $this->assertSame([], $report['profileAudit']['failures']);
        $this->assertSame([], $report['loginLightAudit']['failures']);
        $this->assertSame([], array_values(array_filter($report['served'], fn (string $url) => preg_match('~logo-icon|molecule~', $url) === 1)));
        $this->assertTrue($report['bootShell']['dark']);
        $this->assertTrue($report['longBrand']['fits']);
        $this->assertTrue($report['wideLogo']['fits'], 'Logo de arquivo largo estoura a sidebar');
        $this->assertSame([
            ['name' => 'email', 'message' => 'Estas credenciais não conferem.'],
            ['name' => 'password', 'message' => 'Informe a senha.'],
        ], $report['loginErrors']);
        $this->assertSame([], $report['loginErrorsAudit']['failures']);

        $audited = collect($report['hueAudits'])->map(fn (array $audit) => "{$audit['hue']} {$audit['theme']} {$audit['path']}")->sort()->values()->all();
        $expected = collect([30, 150, 280, 355])->crossJoin(['light', 'dark'], ['/dashboard', '/app/users', '/app/users/create'])->map(fn (array $c) => implode(' ', $c))->sort()->values()->all();
        $this->assertSame($expected, $audited);

        foreach ($report['hueAudits'] as $audit) {
            $this->assertTrue($audit['applied'], "--brand-hue {$audit['hue']} não aplicado em {$audit['path']}");
            $this->assertSame([], $audit['failures'], "Contraste com --brand-hue {$audit['hue']} ({$audit['theme']}) em {$audit['path']}");

            if ($audit['path'] === '/app/users/create') {
                $this->assertGreaterThanOrEqual(3, $audit['fieldBorderContrast'], "Borda de campo com --brand-hue {$audit['hue']} ({$audit['theme']})");
            }
        }
    }
}
