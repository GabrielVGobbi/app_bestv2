<?php

declare(strict_types=1);

use App\Modules\CrudGenerator\DTOs\CrudBlueprint;
use App\Modules\CrudGenerator\DTOs\CrudField;
use App\Modules\CrudGenerator\Services\CrudGeneratorService;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Tests\TestEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';

$app = null;
$exitCode = 1;

try {
    TestEnvironment::assertSafeEnvironment();

    if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
        || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL') !== ''
        || count($argv) !== 5 || ! preg_match('/\A[a-f0-9]{32}\z/', $argv[2])
        || ! in_array([$argv[3], $argv[4]], [['alpha', 'hold'], ['beta', 'apply'], ['beta', 'force']], true)) {
        throw new RuntimeException('Explicit isolated generator worker invocation required.');
    }

    [$script, $project, $owner, $worker, $phase] = $argv;
    $root = realpath(dirname($project));
    $projectReal = realpath($project);

    if ($root === false || $projectReal === false || realpath(dirname($root)) !== realpath(sys_get_temp_dir())
        || ! preg_match('/\Astart-kit-tests-[a-f0-9]{32}\z/', basename($root))
        || $projectReal !== $root.DIRECTORY_SEPARATOR.'project' || is_link($root) || is_link($project)
        || ! is_file($project.'/.crud-generator-owner') || is_link($project.'/.crud-generator-owner')
        || file_get_contents($project.'/.crud-generator-owner') !== $owner) {
        throw new RuntimeException('Refusing an unowned generator fixture.');
    }

    $project = $projectReal;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        if ($entry->isLink()) {
            throw new RuntimeException('Generator worker fixture must not contain symlinks.');
        }
    }

    $app = new class($project) extends Application
    {
        protected function registerBaseServiceProviders(): void {}

        protected function registerLaravelCloudServices(): void {}
    };
    $app->instance('env', 'testing');
    $app->instance('config', new Repository([
        'app' => ['env' => 'testing', 'locale' => 'en', 'fallback_locale' => 'en'],
        'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'url' => null]]],
    ]));
    TestEnvironment::assertSafeDatabase($app['config']);
    $translator = new Translator(new ArrayLoader, 'en');
    $app->instance('translator', $translator);
    $app->instance('validator', new Factory($translator, $app));
    Facade::setFacadeApplication($app);
    Date::setTestNow('2026-10-16 12:00:00');

    $files = new class($project, $owner, $phase) extends Filesystem
    {
        private bool $paused = false;

        public function __construct(private readonly string $project, private readonly string $owner, private readonly string $phase) {}

        public function replace($path, $content, $mode = null): void
        {
            $this->beforeWrite($path);
            parent::replace($path, $content, $mode);
        }

        public function put($path, $contents, $lock = false)
        {
            $this->beforeWrite($path);

            return parent::put($path, $contents, $lock);
        }

        public function move($path, $target)
        {
            $this->assertOwnedPath($path);
            $this->assertOwnedPath($target);

            return parent::move($path, $target);
        }

        public function makeDirectory($path, $mode = 0755, $recursive = false, $force = false)
        {
            $this->assertOwnedPath($path);

            return parent::makeDirectory($path, $mode, $recursive, $force);
        }

        public function delete($paths)
        {
            foreach (is_array($paths) ? $paths : func_get_args() as $path) {
                $this->assertOwnedPath($path);
            }

            return parent::delete(...func_get_args());
        }

        public function deleteDirectory($directory, $preserve = false)
        {
            $this->assertOwnedPath($directory);

            return parent::deleteDirectory($directory, $preserve);
        }

        private function assertOwnedPath(string $path): void
        {
            $normalized = str_replace('\\', '/', $path);
            $project = str_replace('\\', '/', $this->project);

            if (($normalized !== $project && ! str_starts_with($normalized, $project.'/'))
                || preg_match('~(?:\A|/)(?:\.|\.\.)(?:/|\z)~', $normalized)
                || is_link($this->project.'/.crud-generator-owner')
                || file_get_contents($this->project.'/.crud-generator-owner') !== $this->owner) {
                throw new RuntimeException('Worker write must remain inside its owned project.');
            }

            for ($candidate = $normalized; $candidate !== $project; $candidate = str_replace('\\', '/', dirname($candidate))) {
                if (is_link($candidate)) {
                    throw new RuntimeException('Worker refuses a symlink write destination.');
                }

                if (file_exists($candidate)) {
                    $real = str_replace('\\', '/', realpath($candidate));

                    if ($real !== $project && ! str_starts_with($real, $project.'/')) {
                        throw new RuntimeException('Worker destination escapes the owned fixture.');
                    }
                }
            }
        }

        private function beforeWrite(string $path): void
        {
            $this->assertOwnedPath($path);

            if ($this->phase !== 'hold' || $this->paused) {
                return;
            }

            $this->paused = true;
            $ready = $this->project.'/.crud-generator-ready';
            $signal = $ready.'.tmp';
            $this->assertOwnedPath($ready);
            $this->assertOwnedPath($signal);
            $handle = fopen($signal, 'x+b');

            if ($handle === false) {
                throw new RuntimeException('Unable to signal the generation write barrier.');
            }

            try {
                if (fwrite($handle, $this->owner) !== strlen($this->owner) || ! fflush($handle)) {
                    throw new RuntimeException('Incomplete generation write barrier signal.');
                }
            } finally {
                fclose($handle);
            }

            $this->assertOwnedPath($ready);

            if (file_exists($ready) || is_link($ready) || ! rename($signal, $ready)) {
                throw new RuntimeException('Unable to publish the complete generation write barrier.');
            }

            $release = $this->project.'/.crud-generator-release';
            $deadline = microtime(true) + 15;

            while (true) {
                clearstatcache(true, $release);
                $this->assertOwnedPath($release);

                if (is_file($release) && file_get_contents($release) === $this->owner) {
                    return;
                }

                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Parent did not release the generation write barrier.');
                }

                usleep(10000);
            }
        }
    };
    $blueprint = new CrudBlueprint(
        model: $worker === 'alpha' ? 'ConcurrentAlpha' : 'ConcurrentBeta',
        module: $worker === 'alpha' ? 'ConcurrentAlphas' : 'ConcurrentBetas',
        fields: [new CrudField(name: 'name')],
        withExport: false,
        withObserver: false,
        withPolicy: false,
        withFactory: false,
        withSeeder: false,
        withTests: false,
        registerProvider: false,
    );
    (new CrudGeneratorService($files))->apply($blueprint, $phase === 'force');
    fwrite(STDOUT, json_encode(['status' => 'applied'], JSON_THROW_ON_ERROR).PHP_EOL);
    $exitCode = 0;
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode(['status' => 'busy', 'error_fields' => array_keys($exception->errors())], JSON_THROW_ON_ERROR).PHP_EOL);
    $exitCode = 73;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Generator worker failed: '.$exception::class.': '.$exception->getMessage().PHP_EOL);
} finally {
    if ($app !== null) {
        Date::setTestNow();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        $app->flush();
        Application::setInstance(null);
    }
}

exit($exitCode);
