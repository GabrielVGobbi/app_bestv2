<?php

namespace Tests\Feature\Operations;

use ErrorException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ViewCompilationTest extends TestCase
{
    public function test_compilation_waits_for_a_windows_reader_and_publishes_the_complete_view(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Requires native Windows file sharing semantics.');
        }

        $source = $this->testEnvironment->path.'/probe.blade.php';
        file_put_contents($source, 'old view');
        $compiler = $this->app->make('blade.compiler');
        $compiler->compile($source);
        $compiled = $compiler->getCompiledPath($source);
        $original = file_get_contents($compiled);
        file_put_contents($source, 'new complete view');

        $reader = new Process([PHP_BINARY, '-r', '$h = fopen($argv[1], "rb"); if ($h === false) { exit(1); } echo "LOCKED"; fflush(STDOUT); usleep(250000); fclose($h);', $compiled]);
        $reader->setTimeout(5);
        try {
            $reader->start();
            $this->assertTrue($reader->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'LOCKED')));
            $this->assertSame($original, file_get_contents($compiled));
            $compiler->compile($source);
            $this->assertStringContainsString('new complete view', file_get_contents($compiled));
            $this->assertSame(0, $reader->wait());
            $this->assertSame([], glob(config('view.compiled').'/*.tmp'));
        } finally {
            $reader->stop();
        }
    }

    public function test_a_persistent_windows_lock_preserves_the_view_and_reports_the_failure_without_temporary_files(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Requires native Windows file sharing semantics.');
        }

        $path = config('view.compiled').'/locked.php';
        file_put_contents($path, 'original view');
        $handle = fopen($path, 'rb');
        try {
            try {
                $this->app['files']->replace($path, 'replacement');
                $this->fail('A persistent lock must not be reported as success.');
            } catch (ErrorException $exception) {
                $this->assertStringContainsString('(code: 5)', $exception->getMessage());
            }
            $this->assertSame('original view', file_get_contents($path));
            $this->assertSame([], glob(config('view.compiled').'/*.tmp'));
        } finally {
            fclose($handle);
        }
    }

    public function test_views_compile_and_render_before_and_after_a_source_change(): void
    {
        $source = $this->testEnvironment->path.'/probe.blade.php';
        file_put_contents($source, 'Hello {{ $name }}');
        $this->assertSame('Hello &lt;reader&gt;', view()->file($source, ['name' => '<reader>'])->render());
        file_put_contents($source, 'Updated {{ $name }}');
        $this->app->make('blade.compiler')->compile($source);
        $this->assertSame('Updated reader', view()->file($source, ['name' => 'reader'])->render());
        $this->assertSame([], glob(config('view.compiled').'/*.tmp'));
    }

    public function test_a_read_only_view_is_not_overwritten_or_reported_as_success(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Requires native Windows read-only semantics.');
        }

        $path = config('view.compiled').'/read-only.php';
        file_put_contents($path, 'original view');
        chmod($path, 0444);
        try {
            try {
                $this->app['files']->replace($path, 'replacement');
                $this->fail('A read-only view must not be overwritten.');
            } catch (ErrorException $exception) {
                $this->assertStringContainsString('(code: 5)', $exception->getMessage());
            }
            $this->assertSame('original view', file_get_contents($path));
            $this->assertSame([], glob(config('view.compiled').'/*.tmp'));
        } finally {
            chmod($path, 0666);
        }
    }

    public function test_files_outside_the_view_cache_keep_the_standard_filesystem_behavior(): void
    {
        $path = $this->testEnvironment->path.'/ordinary.txt';
        $this->app['files']->replace($path, 'initial');
        $this->app['files']->replace($path, 'updated');
        $this->assertSame('updated', file_get_contents($path));
    }

    public function test_the_debug_exception_renderer_works_with_cold_and_warm_view_caches(): void
    {
        config(['app.debug' => true]);
        $this->app->make(Vite::class)->useHotFile($this->testEnvironment->path.'/vite.hot');
        (new class($this->app) extends FoundationServiceProvider
        {
            public function register(): void
            {
                $this->registerExceptionRenderer();
            }
        })->register();
        $handler = $this->app->make(ExceptionHandler::class);
        for ($index = 0; $index < 3; $index++) {
            $response = $handler->render(Request::create('/__view_probe'), new RuntimeException('Isolated view probe'));
            $this->assertSame(500, $response->getStatusCode());
            $this->assertStringContainsString('Isolated view probe', $response->getContent());
            $this->assertMatchesRegularExpression('~<title>\s*Laravel\s*</title>~', $response->getContent());
        }
        $this->assertSame([], glob(config('view.compiled').'/*.tmp'));
    }
}
