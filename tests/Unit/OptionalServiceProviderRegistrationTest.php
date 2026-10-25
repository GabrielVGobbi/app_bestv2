<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class OptionalServiceProviderRegistrationTest extends TestCase
{
    public function test_provider_list_without_vendor_omits_telescope_and_preserves_required_providers(): void
    {
        $root = dirname(__DIR__, 2);
        $providersPath = $root.'/bootstrap/providers.php';
        $childEnvironment = [];
        $allowed = ['SYSTEMROOT', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA'];

        foreach (getenv() + $_ENV + $_SERVER as $key => $value) {
            if (is_string($key)) {
                $childEnvironment[$key] = in_array(strtoupper($key), $allowed, true) && is_string($value) ? $value : false;
            }
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $childEnvironment['PROMPT'] = '$P$G';
        }

        $process = new Process([
            PHP_BINARY, '-n', '-r',
            'echo json_encode(["vendor_parent_available" => class_exists("Laravel\\\\Telescope\\\\TelescopeApplicationServiceProvider"), "providers" => require $argv[1], "autoloaders" => spl_autoload_functions(), "included_files" => get_included_files()], JSON_THROW_ON_ERROR);',
            $providersPath,
        ], $root, $childEnvironment);
        $process->setTimeout(10);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $observed = json_decode($process->getOutput(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertIsArray($observed);
        $this->assertFalse($observed['vendor_parent_available']);
        $this->assertSame([], $observed['autoloaders']);
        $this->assertSame([realpath($providersPath)], $observed['included_files']);
        $this->assertIsArray($observed['providers']);
        $this->assertTrue(array_is_list($observed['providers']));
        $this->assertNotContains(TelescopeServiceProvider::class, $observed['providers']);
        $this->assertContains(AppServiceProvider::class, $observed['providers']);
        $this->assertContains(FortifyServiceProvider::class, $observed['providers']);
        $installedProviders = require $providersPath;
        $this->assertSame(array_values(array_diff($installedProviders, [TelescopeServiceProvider::class])), $observed['providers']);
    }

    public function test_provider_list_with_real_telescope_keeps_its_application_provider_exactly_once(): void
    {
        $this->assertTrue(class_exists(TelescopeApplicationServiceProvider::class));
        $providers = require dirname(__DIR__, 2).'/bootstrap/providers.php';
        $this->assertIsArray($providers);
        $this->assertTrue(array_is_list($providers));
        $this->assertContains(AppServiceProvider::class, $providers);
        $this->assertContains(FortifyServiceProvider::class, $providers);
        $this->assertSame(1, count(array_filter($providers, fn (mixed $provider): bool => $provider === TelescopeServiceProvider::class)));
        $this->assertSame($providers, array_values(array_unique($providers)));
    }
}
