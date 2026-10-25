<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Tests\TestEnvironment;

return (static function (): Application {
    $context = require __DIR__.'/analysis-guard.php';
    TestEnvironment::assertSafeEnvironment();
    $environment = new TestEnvironment;
    register_shutdown_function(static function () use ($environment, $context): void {
        $failure = null;
        try {
            $normalize = static fn (string $path): string => strtolower(str_replace('\\', '/', $path));
            $runtime = $context['runtime'];
            $ownerPath = $runtime.'/owner.json';
            $stat = lstat($ownerPath);
            if (is_link($runtime) || $normalize((string) realpath($runtime)) !== $normalize($runtime) || $stat === false || ! is_file($ownerPath) || is_link($ownerPath) || $stat['nlink'] !== 1) {
                throw new RuntimeException('Analysis runtime ownership changed before cleanup.');
            }
            $owner = json_decode((string) file_get_contents($ownerPath), true, 512, JSON_THROW_ON_ERROR);
            if (($owner['token'] ?? null) !== $context['token'] || realpath(dirname($environment->path)) !== realpath($runtime.'/tmp') || ! preg_match('/\Astart-kit-tests-[a-f0-9]{32}\z/', basename($environment->path)) || is_link($environment->path)) {
                throw new RuntimeException('Refusing cleanup outside the owned PREP-001 runtime.');
            }
            $ownedRoot = $normalize((string) realpath($environment->path));
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($environment->path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
                if ($entry->isLink() || ! str_starts_with($normalize((string) realpath($entry->getPathname())), $ownedRoot.'/')) {
                    throw new RuntimeException('Refusing cleanup with a linked or escaping analysis descendant.');
                }
                $entryStat = lstat($entry->getPathname());
                if (! $entry->isDir() && (! $entry->isFile() || $entryStat === false || $entryStat['nlink'] !== 1)) {
                    throw new RuntimeException('Refusing cleanup with a nonregular analysis descendant.');
                }
            }
            $environment->cleanUp();
            if (file_exists($environment->path) || is_link($environment->path)) {
                throw new RuntimeException('The owned PREP-001 analysis runtime was not removed.');
            }
        } catch (Throwable $exception) {
            $failure = $exception->getMessage();
        }
        $stream = fopen($context['runtime'].'/cleanup.json', 'x');
        if ($stream === false) {
            fwrite(STDERR, "Cannot publish owned analysis cleanup receipt.\n");
            exit(97);
        }
        fwrite($stream, json_encode(['token' => $context['token'], 'test_runtime' => $environment->path, 'removed' => ! file_exists($environment->path), 'failure' => $failure], JSON_THROW_ON_ERROR));
        fclose($stream);
        if ($failure !== null) {
            fwrite(STDERR, 'Analysis runtime cleanup failed: '.$failure.PHP_EOL);
            exit(97);
        }
    });
    $app = require $context['project'].'/bootstrap/app.php';
    $environment->prepare($app);
    $app->afterBootstrapping(LoadConfiguration::class, static fn (Application $app) => $environment->configure($app));
    $app->afterBootstrapping(RegisterProviders::class, static fn () => $environment->fakeIntegrations());
    $app->afterBootstrapping(BootProviders::class, static function (Application $app) use ($context): void {
        TestEnvironment::assertSafeDatabase($app->make('config'));
        $stream = fopen($context['runtime'].'/bootstrap-passed.json', 'x');
        if ($stream === false) {
            throw new RuntimeException('Cannot publish owned analysis bootstrap receipt.');
        }
        fwrite($stream, json_encode(['token' => $context['token'], 'environment' => $app->environment(), 'database' => 'sqlite :memory:', 'environment_path' => $app->environmentPath(), 'storage_path' => $app->storagePath(), 'bootstrap_path' => $app->bootstrapPath()], JSON_THROW_ON_ERROR));
        fclose($stream);
    });

    return $app;
})();
