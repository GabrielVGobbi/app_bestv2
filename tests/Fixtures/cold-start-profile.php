<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\Routing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestEnvironment;

$started = hrtime(true);
if (getenv('COLD_START_PROFILE') !== '1' || ! in_array(PHP_SAPI, ['cli', 'cgi-fcgi'], true)) {
    exit(1);
}

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$stages = ['autoload_ms' => round((hrtime(true) - $started) / 1e6, 2)];
TestEnvironment::assertSafeEnvironment();
$environment = new TestEnvironment;
$app = require $root.'/bootstrap/app.php';
$environment->prepare($app);
foreach ([LoadEnvironmentVariables::class, LoadConfiguration::class, RegisterFacades::class, RegisterProviders::class, BootProviders::class] as $bootstrapper) {
    $app->beforeBootstrapping($bootstrapper, function () use (&$stages, $started, $bootstrapper): void {
        $stages['before_'.class_basename($bootstrapper)] = round((hrtime(true) - $started) / 1e6, 2);
    });
    $app->afterBootstrapping($bootstrapper, function () use (&$stages, $started, $bootstrapper): void {
        $stages['after_'.class_basename($bootstrapper)] = round((hrtime(true) - $started) / 1e6, 2);
    });
}
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($environment): void {
    $environment->configure($app);
    $app['config']->set([
        'app.debug' => true,
        'debugbar.enabled' => true,
        'telescope.enabled' => getenv('COLD_START_TELESCOPE') === '1',
    ]);
});
$app->afterBootstrapping(RegisterProviders::class, function () use ($environment, $app, &$stages, $started): void {
    $environment->fakeIntegrations();
    if (config('telescope.enabled')) {
        TestEnvironment::assertSafeDatabase($app['config']);
        (require $app->basePath('vendor/laravel/telescope/database/migrations/2018_08_08_100000_create_telescope_entries_table.php'))->up();
    }
    $app['events']->listen(Routing::class, function () use (&$stages, $started): void {
        $stages['routing_ms'] = round((hrtime(true) - $started) / 1e6, 2);
    });
});
$app->afterBootstrapping(BootProviders::class, function () use (&$stages, $started): void {
    $stages['booted_ms'] = round((hrtime(true) - $started) / 1e6, 2);
});

$exitCode = 0;
try {
    $kernel = $app->make(Kernel::class);
    $request = Request::create('/login');
    $response = $kernel->handle($request);
    $stages['response_ms'] = round((hrtime(true) - $started) / 1e6, 2);
    $kernel->terminate($request, $response);
    preg_match('~<script data-page="app" type="application/json">(.*?)</script>~s', $response->getContent(), $matches);
    $page = json_decode($matches[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
    if ($response->getStatusCode() !== 200 || ($page['component'] ?? null) !== 'auth/login') {
        throw new RuntimeException('The isolated cold-start page did not render.');
    }
    Http::assertNothingSent();
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
    echo json_encode([
        'sapi' => PHP_SAPI,
        'status' => $response->getStatusCode(),
        'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
        'stages' => $stages,
        'total_ms' => round((hrtime(true) - $started) / 1e6, 2),
    ], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    $exitCode = 1;
    echo json_encode(['error' => $exception->getMessage(), 'type' => $exception::class], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $environment->cleanUp();
}

exit($exitCode);
