<?php

namespace Tests;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Monolog\Handler\NullHandler;
use RuntimeException;

final class TestEnvironment
{
    public readonly string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir().'/start-kit-tests-'.bin2hex(random_bytes(16));

        foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache', 'storage/logs', 'environment', 'project/database'] as $directory) {
            (new Filesystem)->ensureDirectoryExists($this->path.'/'.$directory);
        }
    }

    public static function assertSafeEnvironment(): void
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''] as $key => $expected) {
            foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $value) {
                if ($value !== null && $value !== false && $value !== $expected) {
                    throw new RuntimeException("Unsafe test environment: {$key}. Tests require testing / sqlite / :memory: without DB_URL.");
                }
            }
        }
    }

    public static function assertSafeDatabase(Repository $config): void
    {
        $connection = $config->get('database.connections.sqlite', []);

        if ($config->get('app.env') !== 'testing'
            || $config->get('database.default') !== 'sqlite'
            || ($connection['driver'] ?? null) !== 'sqlite'
            || ($connection['database'] ?? null) !== ':memory:'
            || ! empty($connection['url'])
            || isset($connection['read'])
            || isset($connection['write'])) {
            throw new RuntimeException('Unsafe test database: only SQLite :memory: in testing is allowed.');
        }
    }

    public function prepare(Application $app): void
    {
        $app->useEnvironmentPath($this->path.'/environment');
        $app->useStoragePath($this->path.'/storage');
        $app->useBootstrapPath($this->path.'/bootstrap');

        foreach (['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE'] as $key) {
            $value = $this->path.'/bootstrap/cache/'.$key.'.php';
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        $app->addAbsoluteCachePathPrefix(substr($this->path, 0, 3));
    }

    public function configure(Application $app): void
    {
        $config = $app->make('config');
        self::assertSafeDatabase($config);

        $config->set([
            'database.connections' => ['sqlite' => $config->get('database.connections.sqlite')],
            'database.redis' => [],
            'activitylog.database_connection' => 'sqlite',
            'telescope.storage.database.connection' => 'sqlite',
            'pulse.storage.database.connection' => 'sqlite',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'app.url' => 'http://localhost',
            'app.debug' => false,
            'app.maintenance.driver' => 'file',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'session.files' => $this->path.'/storage/framework/sessions',
            'view.compiled' => $this->path.'/storage/framework/views',
            'logging.default' => 'null',
            'queue.default' => 'sync',
            'queue.connections' => ['sync' => ['driver' => 'sync']],
            'broadcasting.default' => 'null',
            'broadcasting.connections' => ['null' => ['driver' => 'null']],
            'filesystems.default' => 'local',
            'filesystems.links' => [],
            'mail.default' => 'array',
            'mail.from' => ['address' => 'tests@example.test', 'name' => 'Tests'],
            'services' => [],
            'email.to_override' => null,
            'slack.enabled' => false,
            'slack.webhook_url' => null,
            'telescope.enabled' => false,
            'pulse.enabled' => false,
            'debugbar.enabled' => false,
            'debugbar.storage.path' => $this->path.'/storage/debugbar',
            'inertia.ssr.enabled' => false,
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
            'exports.disk' => 'local',
            'database-exports.disk' => 'local',
        ]);

        foreach (array_keys($config->get('cache.stores', [])) as $name) {
            $config->set("cache.stores.{$name}", ['driver' => 'array', 'serialize' => false]);
        }

        foreach (array_keys($config->get('logging.channels', [])) as $name) {
            $config->set("logging.channels.{$name}", ['driver' => 'monolog', 'handler' => NullHandler::class]);
        }

        foreach (array_keys($config->get('mail.mailers', [])) as $name) {
            $config->set("mail.mailers.{$name}", ['transport' => 'array']);
        }

        foreach (array_keys($config->get('filesystems.disks', [])) as $name) {
            $config->set("filesystems.disks.{$name}", [
                'driver' => 'local', 'root' => $this->path.'/storage/disks/'.$name, 'throw' => true,
            ]);
        }
    }

    public function fakeIntegrations(): void
    {
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        Process::preventStrayProcesses();
    }

    public function isolateProjectFiles(Application $app): void
    {
        $source = dirname(__DIR__);
        $project = $this->path.'/project';
        $files = new Filesystem;

        foreach (['composer.json', 'bootstrap/providers.php', 'app/Modules/ExternalApi/config/external-apis.php'] as $fixture) {
            $files->ensureDirectoryExists(dirname($project.'/'.$fixture));
            $files->copy($source.'/'.$fixture, $project.'/'.$fixture);
        }

        $files->copyDirectory($source.'/app/Modules/ExternalApi/stubs', $project.'/app/Modules/ExternalApi/stubs');
        $files->put($project.'/.env.example', "APP_NAME=Fixture\nAPP_ENV=testing\nAPP_KEY=\n");

        $app->setBasePath($project);
        $app->useAppPath($project.'/app');
        $app->useConfigPath($project.'/config');
        $app->useDatabasePath($project.'/database');
        $app->usePublicPath($project.'/public');
        $app->useEnvironmentPath($project);
        $app->useStoragePath($this->path.'/storage');
        $app->useBootstrapPath($this->path.'/bootstrap');
    }

    public function cleanUp(): void
    {
        $parent = realpath(dirname($this->path));

        if ($parent !== realpath(sys_get_temp_dir()) || ! preg_match('/^start-kit-tests-[a-f0-9]{32}$/', basename($this->path)) || is_link($this->path)) {
            throw new RuntimeException('Refusing to remove an unowned test directory.');
        }

        (new Filesystem)->deleteDirectory($this->path);
    }
}
