<?php

declare(strict_types=1);

namespace Tests\Feature\CrudGenerator;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CrudGeneratorConcurrencyTest extends TestCase
{
    public function test_busy_generation_writes_nothing_and_retry_preserves_both_cruds_without_duplicate_registration(): void
    {
        $this->isolateProjectFiles();
        $project = base_path();
        $owner = bin2hex(random_bytes(16));
        $files = new Filesystem;
        $this->assertSame(realpath($this->testEnvironment->path.'/project'), realpath($project));
        $this->assertNotSame(realpath(dirname(__DIR__, 3)), realpath($project));
        $files->put($project.'/.crud-generator-owner', $owner);
        $this->prepareCentralFixtures($files, $project);
        $before = $this->projectSnapshot($project);
        $workers = [];

        try {
            $alpha = $this->worker($project, $owner, 'alpha', 'hold');
            $alpha->start();
            $workers[] = $alpha;
            $this->awaitFirstWrite($alpha, $project, $owner);
            $this->assertSame($before, $this->projectSnapshot($project), 'The barrier must precede the first generated write.');
            $held = $this->projectSnapshot($project, true);

            $beta = $this->worker($project, $owner, 'beta', 'apply');
            $beta->start();
            $workers[] = $beta;
            $beta->wait();
            $this->assertSame(73, $beta->getExitCode(), $beta->getErrorOutput().$beta->getOutput());
            $busy = json_decode(trim($beta->getOutput()), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('busy', $busy['status']);
            $this->assertContains('generation', $busy['error_fields']);
            $this->assertSame($held, $this->projectSnapshot($project, true), 'A busy worker may not change any generated or central file or create directories.');
            $this->assertTrue($alpha->isRunning(), 'The first worker must still own the generation lock.');

            $this->assertSame(strlen($owner), $files->put($project.'/.crud-generator-release', $owner));
            $alpha->wait();
            $this->assertSame(0, $alpha->getExitCode(), $alpha->getErrorOutput().$alpha->getOutput());
            $this->assertSame('applied', json_decode(trim($alpha->getOutput()), true, flags: JSON_THROW_ON_ERROR)['status']);
            $this->assertNoStagingResidue($project);

            $retry = $this->worker($project, $owner, 'beta', 'apply');
            $retry->start();
            $workers[] = $retry;
            $retry->wait();
            $this->assertSame(0, $retry->getExitCode(), $retry->getErrorOutput().$retry->getOutput());
            $this->assertSame('applied', json_decode(trim($retry->getOutput()), true, flags: JSON_THROW_ON_ERROR)['status']);
            $this->assertNoStagingResidue($project);
            $this->assertFileExists($project.'/app/Modules/ConcurrentAlphas/Models/ConcurrentAlpha.php');
            $this->assertFileExists($project.'/app/Modules/ConcurrentBetas/Models/ConcurrentBeta.php');
            $this->assertCentralRegistrations($files, $project);
            $completed = $this->projectSnapshot($project, true);

            $idempotent = $this->worker($project, $owner, 'beta', 'force');
            $idempotent->start();
            $workers[] = $idempotent;
            $idempotent->wait();
            $this->assertSame(0, $idempotent->getExitCode(), $idempotent->getErrorOutput().$idempotent->getOutput());
            $this->assertSame('applied', json_decode(trim($idempotent->getOutput()), true, flags: JSON_THROW_ON_ERROR)['status']);
            $this->assertNoStagingResidue($project);
            $this->assertSame($completed, $this->projectSnapshot($project, true), 'Reapplying owned output must neither duplicate registrations nor change file contents.');
            $this->assertCentralRegistrations($files, $project);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(2);
                }

                $this->assertFalse($worker->isRunning());
            }
        }
    }

    private function prepareCentralFixtures(Filesystem $files, string $project): void
    {
        $files->ensureDirectoryExists($project.'/config');
        $files->ensureDirectoryExists($project.'/resources/js/lib/api');
        $files->put($project.'/config/navigation.php', <<<'PHP'
<?php

return [
    'modules' => [
        [
            'route_prefixes' => [
                '/exportacoes',
            ],
            'items' => [
                [
                    'slug' => 'exportacoes',
                    'label' => 'Exports fixture',
                    'icon' => 'Download',
                    'href' => ['route' => 'exports.index'],
                ],
            ],
        ],
    ],
];

PHP);
        $files->put($project.'/resources/js/lib/api/api-routes.ts', <<<'TS'
export const apiRoutes = {
    exports: {
        store: '/api/v1/exports',
    },
};

TS);
    }

    private function worker(string $project, string $owner, string $worker, string $phase): Process
    {
        $process = new Process([
            PHP_BINARY, dirname(__DIR__, 2).'/crud-generator-worker.php', $project, $owner, $worker, $phase,
        ], $project, [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'APP_BASE_PATH' => $project, 'LARAVEL_CLOUD' => false,
        ]);
        $process->setTimeout(25);

        return $process;
    }

    private function awaitFirstWrite(Process $worker, string $project, string $owner): void
    {
        $deadline = microtime(true) + 10;

        while (! is_file($project.'/.crud-generator-ready')) {
            if (! $worker->isRunning()) {
                $this->fail('First worker exited before reaching the write barrier. '.$worker->getErrorOutput().$worker->getOutput());
            }

            if (microtime(true) >= $deadline) {
                $this->fail('First worker did not reach the generation write barrier. '.$worker->getErrorOutput().$worker->getOutput());
            }

            usleep(10000);
            clearstatcache(true, $project.'/.crud-generator-ready');
        }

        $this->assertFalse(is_link($project.'/.crud-generator-ready'));
        $this->assertSame($owner, file_get_contents($project.'/.crud-generator-ready'));
    }

    /** @return array<string, string> */
    private function projectSnapshot(string $project, bool $includeDirectories = false): array
    {
        $snapshot = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($project, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $entry) {
            $this->assertFalse($entry->isLink(), 'The concurrent fixture must not contain symlinks.');
            $path = str_replace('\\', '/', $entry->getPathname());
            $relative = substr($path, strlen(str_replace('\\', '/', $project)) + 1);

            if ($entry->isDir()) {
                if ($includeDirectories) {
                    $snapshot[$relative.'/'] = '<directory>';
                }

                continue;
            }

            if (! $entry->isFile() || in_array($relative, ['.start-kit-generator.lock', '.crud-generator-ready', '.crud-generator-release'], true)) {
                continue;
            }

            $snapshot[$relative] = hash_file('sha256', $entry->getPathname());
        }

        ksort($snapshot);

        return $snapshot;
    }

    private function assertNoStagingResidue(string $project): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($project, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $entry) {
            $this->assertFalse(str_starts_with($entry->getFilename(), '.start-kit-crud-'), 'A completed generation must not retain staging files or directories.');
        }
    }

    private function assertCentralRegistrations(Filesystem $files, string $project): void
    {
        $navigation = $files->get($project.'/config/navigation.php');
        $api = $files->get($project.'/resources/js/lib/api/api-routes.ts');

        foreach (['concurrent-alphas', 'concurrent-betas'] as $slug) {
            $this->assertSame(1, substr_count($navigation, "'/{$slug}',"));
            $this->assertSame(1, substr_count($navigation, "'href' => ['route' => '{$slug}.index']"));
            $this->assertStringContainsString("index: '/api/v1/{$slug}'", $api);
        }

        $this->assertSame(1, substr_count($navigation, "'slug' => 'concurrent_alphas'"));
        $this->assertSame(1, substr_count($navigation, "'slug' => 'concurrent_betas'"));
        $this->assertSame(1, substr_count($api, '    concurrentAlphas: {'));
        $this->assertSame(1, substr_count($api, '    concurrentBetas: {'));
        $this->assertStringContainsString("'label' => 'Exports fixture'", $navigation);
        $this->assertStringContainsString("store: '/api/v1/exports'", $api);
    }
}
