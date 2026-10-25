<?php

declare(strict_types=1);

use App\Modules\SeederGenerator\DTOs\SeederBlueprint;
use App\Modules\SeederGenerator\Services\SeederGeneratorService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\DisposableMysql;
use Tests\Fixtures\SeederGenerator\AllowedRecord;
use Tests\Fixtures\SeederGenerator\AllowedRecordFactory;
use Tests\TestEnvironment;

$environment = null;
$app = null;
$exitCode = 1;

try {
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'SEEDER_GENERATOR_MYSQL_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1'] as $key => $expected) {
        if (getenv($key) !== $expected) {
            throw new RuntimeException('Explicit isolated native seeder worker environment required.');
        }

        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
            if ($value !== null && $value !== $expected) {
                throw new RuntimeException('Divergent native seeder worker environment.');
            }
        }
    }

    foreach ([getenv('LARAVEL_CLOUD'), $_ENV['LARAVEL_CLOUD'] ?? null, $_SERVER['LARAVEL_CLOUD'] ?? null] as $value) {
        if (! in_array($value, [null, false, '', '0', 'false'], true)) {
            throw new RuntimeException('Native seeder worker must not activate Cloud.');
        }
    }

    if (count($argv) !== 4 || ! in_array([$argv[2], $argv[3]], [['0', 'hold'], ['0', 'race'], ['1', 'apply'], ['1', 'replay'], ['0', 'run'], ['1', 'run'], ['0', 'cli-revoked']], true)
        || ! is_file($argv[1]) || is_link($argv[1]) || filesize($argv[1]) > 16384) {
        throw new RuntimeException('Unapproved native seeder worker invocation.');
    }

    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $settings = $manifest['settings'];
    $token = $settings['token'];
    $mysqlRoot = realpath($settings['path']);

    if (! is_string($token) || preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1
        || $mysqlRoot === false || realpath(dirname($mysqlRoot)) !== realpath(sys_get_temp_dir())
        || basename($mysqlRoot) !== 'start-kit-mysql-'.$token || is_link($settings['path'])
        || is_link($mysqlRoot.'/owner') || ! is_file($mysqlRoot.'/owner') || file_get_contents($mysqlRoot.'/owner') !== $token
        || is_link($mysqlRoot.'/barrier') || ! is_dir($mysqlRoot.'/barrier')
        || realpath($argv[1]) !== realpath($mysqlRoot.'/seeder-workers.json')) {
        throw new RuntimeException('Unowned native seeder manifest.');
    }

    $project = realpath($manifest['project']);
    $projectRoot = $project === false ? false : realpath(dirname($project));

    if ($project === false || $projectRoot === false || realpath(dirname($projectRoot)) !== realpath(sys_get_temp_dir())
        || preg_match('/\Astart-kit-tests-[a-f0-9]{32}\z/', basename($projectRoot)) !== 1
        || $project !== $projectRoot.DIRECTORY_SEPARATOR.'project' || is_link($manifest['project']) || is_link($projectRoot)
        || realpath(getcwd()) !== $project || is_link($project.'/.seeder-generator-owner')
        || ! is_file($project.'/.seeder-generator-owner') || file_get_contents($project.'/.seeder-generator-owner') !== $token) {
        throw new RuntimeException('Unowned native seeder project or CWD.');
    }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        if ($entry->isLink()) {
            throw new RuntimeException('Native seeder worker project must not contain links.');
        }
    }

    require dirname(__DIR__).'/vendor/autoload.php';
    TestEnvironment::assertSafeEnvironment();
    DisposableMysql::verifiedConnection($settings);
    $environment = new TestEnvironment;
    file_put_contents($environment->path.'/authority-owner', $token);
    file_put_contents($mysqlRoot.'/barrier/environment-'.$argv[2], $environment->path);
    $app = DisposableMysql::bootApplication($settings, $environment);
    $environment->isolateProjectFiles($app);
    $app->setBasePath($project);
    $app->useAppPath($project.'/app');
    $app->useConfigPath($project.'/config');
    $app->useDatabasePath($project.'/database');
    $app->usePublicPath($project.'/public');
    $app->useEnvironmentPath($project);
    $app['config']->set('seeder-generator.allowed_tables', ['allowed_records' => [
        'columns' => ['label', 'unique_key'], 'unique_columns' => ['unique_key'],
        'modes' => ['data', 'factory'], 'operations' => ['preview', 'generate', 'execute', 'run'],
        'model' => AllowedRecord::class, 'factory' => AllowedRecordFactory::class,
        'factory_trusted' => true, 'truncate' => true,
    ]]);
    $connection = $app['db']->connection();

    if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== $settings['schema']
        || (int) $connection->getConfig('port') !== $settings['port']
        || $connection->getConfig('host') !== '127.0.0.1' || $app->environment() !== 'testing'
        || $app['config']->get('app.env') !== 'testing' || realpath($app->basePath()) !== $project) {
        throw new RuntimeException('Native seeder worker configuration escaped its sandbox.');
    }

    $paused = false;
    if ($argv[3] === 'race') {
        $connection->statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
    $connection->beforeExecuting(function (string $sql) use (&$paused, $argv, $mysqlRoot, $token, $settings, $project): void {
        if (! in_array($argv[3], ['hold', 'race'], true) || $paused || preg_match('/\A\s*delete\b/i', $sql) !== 1) {
            return;
        }

        DisposableMysql::verifiedConnection($settings);

        if (is_link($project.'/.seeder-generator-owner') || file_get_contents($project.'/.seeder-generator-owner') !== $token) {
            throw new RuntimeException('Native seeder project ownership changed.');
        }

        $paused = true;

        if (is_link($mysqlRoot.'/barrier')) {
            throw new RuntimeException('Native seeder barrier directory ownership changed.');
        }

        $temporary = $mysqlRoot.'/barrier/.seeder-ready-'.$token.'-'.bin2hex(random_bytes(8));

        try {
            if (file_exists($temporary) || is_link($temporary) || file_put_contents($temporary, $token) !== 32
                || ! rename($temporary, $mysqlRoot.'/barrier/seeder-ready')) {
                throw new RuntimeException('Native seeder readiness publication failed.');
            }
        } finally {
            if (is_file($temporary) && ! is_link($temporary)) {
                unlink($temporary);
            }
        }
        $deadline = microtime(true) + 25;

        while (! is_file($mysqlRoot.'/barrier/seeder-release')) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Native seeder DELETE barrier timed out.');
            }

            usleep(10000);
            clearstatcache(true, $mysqlRoot.'/barrier/seeder-release');
        }

        if (is_link($mysqlRoot.'/barrier/seeder-release') || file_get_contents($mysqlRoot.'/barrier/seeder-release') !== $token) {
            throw new RuntimeException('Native seeder release ownership mismatch.');
        }
    });

    try {
        if (in_array($argv[3], ['run', 'cli-revoked'], true)) {
            if (class_exists('Database\\Seeders\\GeneratedNativeReplaySeeder', false)) {
                throw new RuntimeException('Artifact worker must start with a fresh class table.');
            }

            $result = $app->make(SeederGeneratorService::class)->runSeeder('GeneratedNativeReplaySeeder');

            if ($result['exit_code'] !== 0) {
                throw new RuntimeException('Owned native artifact execution failed.');
            }

            $status = 'run';

            if ($argv[3] === 'cli-revoked') {
                $app['config']->set('seeder-generator.allowed_tables', []);
                $denied = false;

                try {
                    $app->make(Kernel::class)->call('db:seed', [
                        '--class' => 'Database\\Seeders\\GeneratedNativeReplaySeeder', '--no-interaction' => true,
                    ]);
                } catch (ValidationException $exception) {
                    $denied = array_key_exists('table', $exception->errors());
                }

                if (! $denied) {
                    throw new RuntimeException('CLI artifact ignored revoked live policy.');
                }

                $status = 'policy-refused';
            }

            echo json_encode(['status' => $status], JSON_THROW_ON_ERROR)."\n";
            $exitCode = 0;
        } else {
            $blueprint = SeederBlueprint::fromArray([
                'table' => 'allowed_records', 'mode' => 'data', 'limit' => 2,
                'truncate' => in_array($argv[3], ['hold', 'race'], true), 'idempotent' => true, 'unique_column' => 'unique_key', 'seeder_name' => 'native_worker',
            ]);
            $result = $app->make(SeederGeneratorService::class)->executeDirectly($blueprint);

            if ($argv[3] === 'race') {
                if ($result['success'] || $result['exit_code'] !== 1 || $result['records'] !== 0 || ! $paused) {
                    throw new RuntimeException('Concurrent destination growth must refuse replacement.');
                }
                echo json_encode([
                    'status' => 'refused', 'records' => $result['records'], 'transaction_level' => $connection->transactionLevel(),
                    'foreign_keys' => (int) $connection->scalar('SELECT @@foreign_key_checks'),
                    'isolation' => $connection->scalar('SELECT @@SESSION.transaction_isolation'),
                ], JSON_THROW_ON_ERROR)."\n";
            } else {
                if (! $result['success'] || $result['exit_code'] !== 0) {
                    throw new RuntimeException('Owned native seeder execution failed.');
                }

                echo json_encode(['status' => 'applied', 'records' => $result['records']], JSON_THROW_ON_ERROR)."\n";
            }
            $exitCode = 0;
        }
    } catch (ValidationException $exception) {
        if (! array_key_exists('generation', $exception->errors())) {
            throw $exception;
        }

        echo json_encode(['status' => 'busy', 'error_fields' => array_keys($exception->errors())], JSON_THROW_ON_ERROR)."\n";
        $exitCode = 73;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Native seeder worker refused or failed: '.$exception::class."\n");
} finally {
    try {
        $app?->make('db')->disconnect();
        $app?->flush();
    } finally {
        $environment?->cleanUp();
    }
}

exit($exitCode);
