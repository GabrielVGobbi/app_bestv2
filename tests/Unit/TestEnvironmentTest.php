<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestEnvironment;

class TestEnvironmentTest extends TestCase
{
    #[DataProvider('unsafeDatabases')]
    public function test_unsafe_database_is_rejected_before_any_connection_is_opened(array $overrides): void
    {
        $config = new Repository(array_replace_recursive([
            'app' => ['env' => 'testing'],
            'database' => [
                'default' => 'sqlite',
                'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'url' => null]],
            ],
        ], $overrides));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe test database');

        TestEnvironment::assertSafeDatabase($config);
    }

    public static function unsafeDatabases(): array
    {
        return [
            'environment' => [['app' => ['env' => 'local']]],
            'connection' => [['database' => ['default' => 'mysql']]],
            'file' => [['database' => ['connections' => ['sqlite' => ['database' => 'database/database.sqlite']]]]],
            'url' => [['database' => ['connections' => ['sqlite' => ['url' => 'sqlite:///shared.sqlite']]]]],
            'driver' => [['database' => ['connections' => ['sqlite' => ['driver' => 'mysql']]]]],
            'read replica' => [['database' => ['connections' => ['sqlite' => ['read' => ['database' => 'shared']]]]]],
            'write replica' => [['database' => ['connections' => ['sqlite' => ['write' => ['database' => 'shared']]]]]],
        ];
    }

    #[DataProvider('unsafeInheritedEnvironment')]
    public function test_inherited_database_settings_stop_boot_before_refresh_database(array $environment): void
    {
        $probe = <<<'PHP'
require 'vendor/autoload.php';
class IsolationProbe extends Tests\TestCase
{
    use Illuminate\Foundation\Testing\RefreshDatabase;

    public function runProbe(): void { $this->setUp(); }
    public function refreshDatabase(): void { echo 'REFRESH_REACHED'; }
}
try {
    (new IsolationProbe('runProbe'))->runProbe();
    exit(1);
} catch (RuntimeException $exception) {
    echo $exception->getMessage();
    exit(23);
}
PHP;

        $process = new Process([PHP_BINARY, '-r', $probe], dirname(__DIR__, 2), array_merge([
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ], $environment));
        $process->run();

        $this->assertSame(23, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('Unsafe test environment', $process->getOutput());
        $this->assertStringNotContainsString('REFRESH_REACHED', $process->getOutput());
    }

    public static function unsafeInheritedEnvironment(): array
    {
        return [
            'local environment' => [['APP_ENV' => 'local']],
            'mysql' => [['DB_CONNECTION' => 'mysql']],
            'shared database' => [['DB_DATABASE' => 'laravel']],
            'database url' => [['DB_URL' => 'mysql://fixture:fixture@localhost/shared']],
        ];
    }

    public function test_changed_configuration_is_rejected_before_refresh_database(): void
    {
        $probe = <<<'PHP'
require 'vendor/autoload.php';
class IsolationProbe extends Tests\TestCase
{
    use Illuminate\Foundation\Testing\RefreshDatabase;

    public function createApplication(): Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.connections.sqlite.database', 'shared.sqlite');
        return $app;
    }
    public function runProbe(): void
    {
        try { $this->setUp(); } finally { $this->testEnvironment?->cleanUp(); }
    }
    public function refreshDatabase(): void { echo 'REFRESH_REACHED'; }
}
try {
    (new IsolationProbe('runProbe'))->runProbe();
    exit(1);
} catch (RuntimeException $exception) {
    echo $exception->getMessage();
    exit(23);
}
PHP;

        $process = new Process([PHP_BINARY, '-r', $probe], dirname(__DIR__, 2), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
        ]);
        $process->run();

        $this->assertSame(23, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
        $this->assertStringContainsString('Unsafe test database', $process->getOutput());
        $this->assertStringNotContainsString('REFRESH_REACHED', $process->getOutput());
    }
}
