<?php

use App\Services\DevToolsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';

$environment = null;
$app = null;
$exitCode = 0;

try {
    TestEnvironment::assertSafeEnvironment();

    if (count($argv) !== 6 || ! preg_match('/^[a-f0-9]{32}$/', $argv[2]) || ! in_array($argv[3], ['0', '1'], true)) {
        throw new RuntimeException('Explicit owned environment worker invocation required.');
    }

    [$script, $project, $owner, $worker, $key, $value] = $argv;
    $root = realpath(dirname($project));

    if ($root === false || realpath(dirname($root)) !== realpath(sys_get_temp_dir())
        || ! preg_match('/^start-kit-tests-[a-f0-9]{32}$/', basename($root))
        || realpath($project) !== $root.DIRECTORY_SEPARATOR.'project' || is_link($project)
        || ! is_file($project.'/.devtools-owner') || file_get_contents($project.'/.devtools-owner') !== $owner
        || ! array_key_exists($key, DevToolsService::OPERATIONAL_CONFIG_KEYS)) {
        throw new RuntimeException('Refusing an unowned environment fixture.');
    }

    $environment = new TestEnvironment;
    file_put_contents($environment->path.'/.devtools-owner', $owner);
    file_put_contents($project.'/worker-environment-'.$worker, $environment->path);
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $environment->prepare($app);
    $app->afterBootstrapping(LoadConfiguration::class, fn ($app) => $environment->configure($app));
    $app->afterBootstrapping(RegisterProviders::class, fn () => $environment->fakeIntegrations());
    $app->make(Kernel::class)->bootstrap();
    TestEnvironment::assertSafeDatabase($app['config']);
    $app->useEnvironmentPath($project);
    DevToolsService::validateOperationalSetting($key, $value);
    $probe = fopen($project.'/.env.lock', 'r+b');

    if ($probe === false) {
        throw new RuntimeException('Parent lock is missing.');
    }

    $acquired = flock($probe, LOCK_EX | LOCK_NB);

    if ($acquired) {
        flock($probe, LOCK_UN);
        fclose($probe);
        throw new RuntimeException('Parent must retain the shared sidecar lock before release.');
    }

    fclose($probe);
    file_put_contents($project.'/worker-locked-'.$worker, 'locked');
    app(DevToolsService::class)->updateEnvVariable($key, $value);
    file_put_contents($project.'/worker-result-'.$worker, 'success');
} catch (Throwable $exception) {
    fwrite(STDERR, 'Environment worker failed: '.$exception::class."\n");
    $exitCode = 1;
} finally {
    try {
        $app?->make('db')->disconnect();
        $app?->flush();
    } finally {
        $environment?->cleanUp();
    }
}

exit($exitCode);
