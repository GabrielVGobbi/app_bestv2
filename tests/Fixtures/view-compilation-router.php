<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Tests\TestEnvironment;

if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
    || ! in_array($_SERVER['REQUEST_URI'] ?? '', ['/probe', '/exception'], true)
    || PHP_OS_FAMILY !== 'Windows') {
    http_response_code(404);

    return;
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}
TestEnvironment::assertSafeEnvironment();
$environment = new TestEnvironment;
$reader = null;
try {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $environment->prepare($app);
    $app->usePublicPath($environment->path.'/public');
    $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) use ($environment): void {
        $environment->configure($app);
        $app['config']->set('app.debug', true);
    });
    $app->afterBootstrapping(RegisterProviders::class, fn () => $environment->fakeIntegrations());
    $app->make(Kernel::class)->bootstrap();
    TestEnvironment::assertSafeDatabase($app['config']);

    if ($_SERVER['REQUEST_URI'] === '/exception') {
        $request = Request::capture();
        $app->instance('request', $request);
        $app->make(ExceptionHandler::class)->render($request, new RuntimeException('Isolated exception renderer validation'))->send();

        return;
    }

    $source = $environment->path.'/probe.blade.php';
    file_put_contents($source, '<h1>Old view</h1>');
    $compiler = $app->make('blade.compiler');
    $compiler->compile($source);
    $compiled = $compiler->getCompiledPath($source);
    $reader = new Process([PHP_BINARY, '-r', '$h = fopen($argv[1], "rb"); if ($h === false) { exit(1); } echo "LOCKED"; fflush(STDOUT); usleep(250000); fclose($h);', $compiled]);
    $reader->setTimeout(5);
    $reader->start();
    if (! $reader->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'LOCKED'))) {
        throw new RuntimeException('The native reader did not acquire the view.');
    }
    file_put_contents($source, '<h1>View atualizada com sucesso</h1><p>Bloqueio Windows real de 250 ms superado; arquivo publicado inteiro.</p>');
    $compiler->compile($source);
    if ($reader->wait() !== 0 || glob($app['config']['view.compiled'].'/*.tmp') !== []) {
        throw new RuntimeException('Reader or temporary file cleanup failed.');
    }
    response(view()->file($source)->render())->send();
} finally {
    $reader?->stop();
    $environment->cleanUp();
}
