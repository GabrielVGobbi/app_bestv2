<?php

declare(strict_types=1);

namespace Tests\Feature\SeederGenerator;

use App\Modules\SeederGenerator\DTOs\SeederBlueprint;
use App\Modules\SeederGenerator\Services\SeederGeneratorService;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\Fixtures\SeederGenerator\AllowedRecord;
use Tests\Fixtures\SeederGenerator\AllowedRecordFactory;
use Tests\TestEnvironment;

class SeederGeneratorMysqlTest extends TestCase
{
    protected function tearDown(): void
    {
        try {
            HandleExceptions::flushState($this);
            RegisterProviders::flushState();
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
        } finally {
            parent::tearDown();
        }
    }

    public function test_native_innodb_replay_and_failures_preserve_transactional_data_and_foreign_keys(): void
    {
        $this->withSandbox(function (Application $app, Connection $connection, DisposableMysql $mysql): void {
            $service = $app->make(SeederGeneratorService::class);
            $blueprint = $this->blueprint();
            $code = $service->generateSeederCode($blueprint);
            $path = $service->saveSeeder($blueprint, $code);
            $this->assertSame(realpath($app->databasePath('seeders')), realpath(dirname($path)));
            $this->assertFalse(is_link($path));
            $this->assertSame($code, file_get_contents($path));
            $this->assertStringNotContainsString('SEC007_NATIVE_SECRET', $code);
            $connection->table('allowed_records')->delete();

            $this->assertFalse(class_exists('Database\\Seeders\\GeneratedNativeReplaySeeder', false));
            $this->runArtifactWorker($app, $mysql, '0', 'run', 'run');
            $this->assertSame(['native-first', 'native-second'], $connection->table('allowed_records')->orderBy('unique_key')->pluck('unique_key')->all());
            $this->assertSame(0, $connection->table('allowed_records')->whereNotNull('secret')->count());
            $replayed = $this->rows($connection);
            $this->runArtifactWorker($app, $mysql, '1', 'run', 'run');
            $this->assertSame($replayed, $this->rows($connection));
            $this->runArtifactWorker($app, $mysql, '0', 'cli-revoked', 'policy-refused');
            $this->assertSame($replayed, $this->rows($connection));
            $this->assertSame(0, $service->executeDirectly($blueprint)['records']);

            $maximumId = (int) $connection->table('allowed_records')->max('id');
            $replacement = $this->blueprint(['truncate' => true]);
            $result = $service->executeDirectly($replacement);
            $this->assertTrue($result['success']);
            $this->assertSame(2, $result['records']);
            $this->assertGreaterThan($maximumId, (int) $connection->table('allowed_records')->min('id'), 'Transactional DELETE must not reset AUTO_INCREMENT.');
            $this->assertSame(1, (int) $connection->scalar('SELECT @@foreign_key_checks'));

            $queries = [];
            $connection->beforeExecuting(function (string $sql) use (&$queries): void {
                $queries[] = $sql;
            });
            $connection->getSchemaBuilder()->create('seeder_references', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('allowed_id')->constrained('allowed_records')->restrictOnDelete();
            });
            $connection->table('seeder_references')->insert(['allowed_id' => $connection->table('allowed_records')->min('id')]);
            $before = $this->rows($connection);
            $queries = [];
            $refused = $service->executeDirectly($replacement);
            $this->assertFalse($refused['success']);
            $this->assertSame(0, $refused['records']);
            $this->assertSame($before, $this->rows($connection));
            $this->assertSame(1, $connection->table('seeder_references')->count());
            $this->assertContainsDeleteWithoutUnsafeSql($queries);
            $this->assertSame(1, (int) $connection->scalar('SELECT @@foreign_key_checks'));
            $connection->getSchemaBuilder()->drop('seeder_references');

            $connection->unprepared("CREATE TRIGGER seeder_fail_insert BEFORE INSERT ON allowed_records FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SEC007_SQL_SENTINEL'");
            $queries = [];
            $failed = $service->executeDirectly($replacement);
            $this->assertFalse($failed['success']);
            $this->assertSame(0, $failed['records']);
            $this->assertSame($before, $this->rows($connection), 'A failed INSERT after DELETE must restore every original row.');
            $this->assertContainsDeleteWithoutUnsafeSql($queries);
            $this->assertStringNotContainsString('SEC007_SQL_SENTINEL', $failed['message']);
            $this->assertStringNotContainsString('insert into', strtolower($failed['message']));
            $connection->unprepared('DROP TRIGGER seeder_fail_insert');

            $connection->unprepared("CREATE TRIGGER seeder_fail_second_factory BEFORE INSERT ON allowed_records FOR EACH ROW BEGIN IF (SELECT COUNT(*) FROM allowed_records) >= 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SEC007_FACTORY_SENTINEL'; END IF; END");
            $factory = $service->executeDirectly($this->blueprint(['mode' => 'factory', 'truncate' => false]));
            $this->assertFalse($factory['success']);
            $this->assertSame(0, $factory['records']);
            $this->assertSame($before, $this->rows($connection), 'The first neutral factory insert must roll back when the second fails.');
            $this->assertStringNotContainsString('SEC007_FACTORY_SENTINEL', $factory['message']);
            $connection->unprepared('DROP TRIGGER seeder_fail_second_factory');

            $this->assertSame(0, $connection->transactionLevel());
            $connection->table('allowed_records')->update(['secret' => 'SEC007_REQUIRED_SECRET']);
            $connection->statement('ALTER TABLE allowed_records MODIFY secret VARCHAR(255) NOT NULL');
            $requiredBefore = $this->rows($connection);
            $queries = [];
            $missingRequired = $service->executeDirectly($replacement);
            $this->assertFalse($missingRequired['success'], 'Omitting a required excluded column must abort instead of coercing or ignoring the row.');
            $this->assertSame(0, $missingRequired['records']);
            $this->assertSame($requiredBefore, $this->rows($connection));
            $this->assertContainsDeleteWithoutUnsafeSql($queries);
            $this->assertStringNotContainsString('SEC007_REQUIRED_SECRET', $missingRequired['message']);
            $this->assertStringNotContainsString('insert into', strtolower($missingRequired['message']));
            $connection->statement('ALTER TABLE allowed_records MODIFY secret VARCHAR(255) NULL');

            $connection->getSchemaBuilder()->create('seeder_parents', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('label');
            });
            $oldParent = $connection->table('seeder_parents')->insertGetId(['label' => 'Old parent']);
            $currentParent = $connection->table('seeder_parents')->insertGetId(['label' => 'Current parent']);
            $connection->getSchemaBuilder()->table('allowed_records', function (Blueprint $table): void {
                $table->foreignId('parent_id')->nullable()->constrained('seeder_parents')->restrictOnDelete();
            });
            $connection->table('allowed_records')->update(['parent_id' => $oldParent]);
            $descriptor = [
                'version' => 1,
                'blueprint' => [
                    'table' => 'allowed_records', 'mode' => 'data', 'limit' => 2,
                    'truncate' => true, 'idempotent' => true, 'unique_column' => 'unique_key', 'seeder_name' => 'native_fk_snapshot',
                ],
                'columns' => ['label', 'unique_key', 'parent_id'],
                'rows' => [
                    ['label' => 'Obsolete snapshot', 'unique_key' => 'obsolete-first', 'parent_id' => $oldParent],
                    ['label' => 'Obsolete second', 'unique_key' => 'obsolete-second', 'parent_id' => $oldParent],
                ],
            ];
            $connection->table('allowed_records')->update(['parent_id' => $currentParent]);
            $connection->table('seeder_parents')->where('id', $oldParent)->delete();
            $app['config']->set('seeder-generator.allowed_tables.allowed_records.columns', ['label', 'unique_key', 'parent_id']);
            $foreignBefore = $this->rows($connection);
            $failure = null;
            $queries = [];

            try {
                $service->executeGenerated($descriptor);
            } catch (\RuntimeException $exception) {
                $failure = $exception->getMessage();
            }

            $this->assertNotNull($failure, 'An obsolete snapshot FK must abort instead of committing DELETE with an ignored INSERT.');
            $this->assertSame($foreignBefore, $this->rows($connection));
            $this->assertSame(1, $connection->table('seeder_parents')->count());
            $this->assertContainsDeleteWithoutUnsafeSql($queries);
            $this->assertStringNotContainsString('insert into', strtolower($failure));
            $this->assertStringNotContainsString('SEC007_REQUIRED_SECRET', $failure);
            $this->assertSame(1, (int) $connection->scalar('SELECT @@foreign_key_checks'));

            $connection->getSchemaBuilder()->create('nontransactional_records', function (Blueprint $table): void {
                $table->engine = 'MyISAM';
                $table->id();
                $table->string('label');
                $table->string('unique_key', 191)->unique();
            });
            $connection->table('nontransactional_records')->insert(['label' => 'Engine fixture', 'unique_key' => 'engine-first']);
            $policy = $this->policy();
            $policy['modes'] = ['data'];
            unset($policy['model'], $policy['factory'], $policy['factory_trusted']);
            $app['config']->set('seeder-generator.allowed_tables.nontransactional_records', $policy);
            $queries = [];
            $denied = false;

            try {
                $denied = ! $service->executeDirectly($this->blueprint(['table' => 'nontransactional_records', 'truncate' => true]))['success'];
            } catch (ValidationException $exception) {
                $denied = $exception->errors() !== [];
            }

            $this->assertTrue($denied, 'A nontransactional engine must be refused before mutation.');
            $this->assertSame(1, $connection->table('nontransactional_records')->count());
            $this->assertSame('Engine fixture', $connection->table('nontransactional_records')->value('label'));
            $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => preg_match('/\A\s*(?:delete|truncate|insert|update)\b/i', $sql) === 1)));
            $this->assertSame(1, (int) $connection->scalar('SELECT @@foreign_key_checks'));
        });
    }

    public function test_native_non_strict_mysql_session_is_refused_before_any_data_mutation(): void
    {
        $this->withSandbox(function (Application $app, Connection $connection): void {
            $connection->statement('ALTER TABLE allowed_records MODIFY secret VARCHAR(255) NOT NULL');
            $originalMode = $connection->scalar('SELECT @@SESSION.sql_mode');
            $this->assertIsString($originalMode);
            $before = $this->rows($connection);
            $queries = [];
            $connection->beforeExecuting(function (string $sql) use (&$queries): void {
                $queries[] = $sql;
            });

            try {
                $connection->statement("SET SESSION sql_mode = ''");
                $this->assertSame('', $connection->scalar('SELECT @@SESSION.sql_mode'));
                $queries = [];
                $refused = false;

                try {
                    $result = $app->make(SeederGeneratorService::class)->executeDirectly($this->blueprint(['truncate' => true]));
                    $refused = $result['success'] === false;
                } catch (ValidationException $exception) {
                    $refused = $exception->errors() !== [];
                }

                $this->assertTrue($refused, 'A non-strict MySQL session must be refused before DELETE can discard original records.');
                $this->assertSame($before, $this->rows($connection));
                $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => preg_match('/\A\s*(?:delete|truncate|insert|update|replace)\b/i', $sql) === 1)));
                $this->assertSame(1, (int) $connection->scalar('SELECT @@foreign_key_checks'));
            } finally {
                $connection->statement('SET SESSION sql_mode = ?', [$originalMode]);
                $this->assertSame($originalMode, $connection->scalar('SELECT @@SESSION.sql_mode'));
            }
        });
    }

    public function test_native_busy_worker_changes_nothing_and_release_retry_is_idempotent(): void
    {
        $this->withSandbox(function (Application $app, Connection $connection, DisposableMysql $mysql): void {
            $project = $app->basePath();
            $settings = $mysql->settings();
            $files = new Filesystem;
            $files->put($project.'/.seeder-generator-owner', $settings['token']);
            $manifest = $mysql->path.'/seeder-workers.json';
            $files->put($manifest, json_encode(['settings' => $settings, 'project' => $project], JSON_THROW_ON_ERROR));
            chmod($manifest, 0600);
            $workers = [];

            try {
                $alpha = $this->worker($manifest, $project, '0', 'hold');
                $alpha->start();
                $workers[] = $alpha;
                $this->waitUntil(fn (): bool => is_file($mysql->path.'/barrier/seeder-ready'), $alpha);
                $this->assertSame($settings['token'], file_get_contents($mysql->path.'/barrier/seeder-ready'));
                $before = $this->rows($connection);
                $held = $this->projectSnapshot($project);
                $beta = $this->worker($manifest, $project, '1', 'apply');
                $beta->start();
                $workers[] = $beta;
                $beta->wait();
                $this->assertSame(73, $beta->getExitCode(), $beta->getErrorOutput().$beta->getOutput());
                $busy = json_decode(trim($beta->getOutput()), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('busy', $busy['status']);
                $this->assertContains('generation', $busy['error_fields']);
                $this->assertSame($before, $this->rows($connection));
                $this->assertSame($held, $this->projectSnapshot($project));
                $this->assertTrue($alpha->isRunning());
                $this->publishRelease($mysql);
                $alpha->wait();
                $this->assertSame(0, $alpha->getExitCode(), $alpha->getErrorOutput().$alpha->getOutput());
                $this->assertSame(2, json_decode(trim($alpha->getOutput()), true, flags: JSON_THROW_ON_ERROR)['records']);
                $this->assertSame('', $files->get($project.'/.start-kit-generator.lock'));

                foreach (['apply', 'replay'] as $phase) {
                    $retry = $this->worker($manifest, $project, '1', $phase);
                    $retry->start();
                    $workers[] = $retry;
                    $retry->wait();
                    $this->assertSame(0, $retry->getExitCode(), $retry->getErrorOutput().$retry->getOutput());
                    $this->assertSame(0, json_decode(trim($retry->getOutput()), true, flags: JSON_THROW_ON_ERROR)['records']);
                }

                $this->assertSame(2, $connection->table('allowed_records')->count());
                $this->assertSame(['native-first', 'native-second'], $connection->table('allowed_records')->orderBy('unique_key')->pluck('unique_key')->all());
                $this->assertSame(1, (int) $connection->scalar('SELECT @@foreign_key_checks'));
                $this->assertSame($held, $this->projectSnapshot($project));
                $this->assertSame('', $files->get($project.'/.start-kit-generator.lock'));
            } finally {
                foreach ($workers as $worker) {
                    if ($worker->isRunning()) {
                        $worker->stop(2);
                    }

                    $this->assertFalse($worker->isRunning());
                }
            }
        });
    }

    public function test_native_read_committed_concurrent_growth_rolls_back_the_entire_delete(): void
    {
        $this->withSandbox(function (Application $app, Connection $connection, DisposableMysql $mysql): void {
            $service = $app->make(SeederGeneratorService::class);
            $code = $service->generateSeederCode($this->blueprint(['truncate' => true]));
            $this->assertSame(1, preg_match('/@start-kit-seeder:([A-Za-z0-9+\/=]+)/', $code, $match));
            $descriptor = json_decode(base64_decode($match[1], true), true, flags: JSON_THROW_ON_ERROR);
            unset($descriptor['sha256']);
            $worker = $this->worker($mysql->path.'/seeder-workers.json', $app->basePath(), '0', 'race');
            try {
                $worker->start();
                $this->waitUntil(fn (): bool => is_file($mysql->path.'/barrier/seeder-ready'), $worker);
                $connection->table('allowed_records')->insert([
                    'label' => 'External insert after snapshot', 'unique_key' => 'native-third', 'secret' => 'PRESERVE_CONCURRENT_ROW',
                ]);
                $before = $this->rows($connection);
                $this->assertCount(3, $before);
                $this->publishRelease($mysql);
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());
                $result = json_decode(trim($worker->getOutput()), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('refused', $result['status']);
                $this->assertSame('READ-COMMITTED', $result['isolation']);
                $this->assertSame(0, $result['records']);
                $this->assertSame(0, $result['transaction_level']);
                $this->assertSame(1, $result['foreign_keys']);
                $this->assertSame($before, $this->rows($connection));
                $this->assertSame(1, (int) $connection->scalar('SELECT @@foreign_key_checks'));

                $refused = $service->executeDirectly($this->blueprint(['truncate' => true]));
                $this->assertFalse($refused['success']);
                $this->assertSame(0, $refused['records']);
                $this->assertSame($before, $this->rows($connection));
                $runtimeRefused = false;
                try {
                    $service->executeGenerated($descriptor);
                } catch (\RuntimeException) {
                    $runtimeRefused = true;
                }
                $this->assertTrue($runtimeRefused);
                $this->assertSame($before, $this->rows($connection));
                $captureRefused = false;
                try {
                    $service->generateSeederCode($this->blueprint(['truncate' => true]));
                } catch (ValidationException $exception) {
                    $captureRefused = array_key_exists('truncate', $exception->errors());
                }
                $this->assertTrue($captureRefused);
                $this->assertSame($before, $this->rows($connection));
                $this->assertSame(0, $connection->transactionLevel());
                $this->assertSame(1, (int) $connection->scalar('SELECT @@foreign_key_checks'));
            } finally {
                if ($worker->isRunning()) {
                    $worker->stop(2);
                }
                $this->assertFalse($worker->isRunning());
            }
        });
    }

    private function withSandbox(callable $test): void
    {
        if (getenv('SEEDER_GENERATOR_MYSQL_TESTS') !== '1' || getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Explicit SEEDER_GENERATOR_MYSQL_TESTS=1 and AUTHORITY_MYSQL_TESTS=1 required; run only after hardened-code review.');
        }

        TestEnvironment::assertSafeEnvironment();
        $mysql = new DisposableMysql;
        $environment = null;
        $app = null;
        $connection = null;

        try {
            $mysql->start();
            $environment = new TestEnvironment;
            $app = DisposableMysql::bootApplication($mysql->settings(), $environment);
            $environment->isolateProjectFiles($app);
            $this->assertSame(realpath($environment->path.'/project'), realpath($app->basePath()));
            $connection = $app['db']->connection();
            DisposableMysql::verifiedConnection($mysql->settings());
            $this->assertSame('mysql', $connection->getDriverName());
            $this->assertSame($mysql->settings()['schema'], $connection->getDatabaseName());
            (new Filesystem)->ensureDirectoryExists($app->databasePath('seeders'));
            (new Filesystem)->put($app->basePath('.seeder-generator-owner'), $mysql->settings()['token']);
            (new Filesystem)->put($mysql->path.'/seeder-workers.json', json_encode(['settings' => $mysql->settings(), 'project' => $app->basePath()], JSON_THROW_ON_ERROR));
            chmod($mysql->path.'/seeder-workers.json', 0600);
            $connection->getSchemaBuilder()->create('allowed_records', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('label');
                $table->string('unique_key')->unique();
                $table->string('secret')->nullable();
            });
            $connection->table('allowed_records')->insert([
                ['label' => 'Native first', 'unique_key' => 'native-first', 'secret' => 'SEC007_NATIVE_SECRET'],
                ['label' => 'Native second', 'unique_key' => 'native-second', 'secret' => 'SEC007_NATIVE_SECRET'],
            ]);
            $app['config']->set('seeder-generator.allowed_tables', ['allowed_records' => $this->policy()]);
            $test($app, $connection, $mysql);
        } finally {
            try {
                while ($connection !== null && $connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }

                $app?->make('db')->disconnect();
                $app?->flush();
            } finally {
                try {
                    $environment?->cleanUp();
                } finally {
                    $mysql->cleanUp();
                }
            }
        }

        $this->assertDirectoryDoesNotExist($environment->path);
        $this->assertDirectoryDoesNotExist($mysql->path);
    }

    /** @return array<string, mixed> */
    private function policy(): array
    {
        return [
            'columns' => ['label', 'unique_key'], 'unique_columns' => ['unique_key'],
            'modes' => ['data', 'factory'], 'operations' => ['preview', 'generate', 'execute', 'run'],
            'model' => AllowedRecord::class, 'factory' => AllowedRecordFactory::class,
            'factory_trusted' => true, 'truncate' => true,
        ];
    }

    /** @param array<string, mixed> $changes */
    private function blueprint(array $changes = []): SeederBlueprint
    {
        return SeederBlueprint::fromArray(array_replace([
            'table' => 'allowed_records', 'mode' => 'data', 'limit' => 2, 'truncate' => false,
            'idempotent' => true, 'unique_column' => 'unique_key', 'seeder_name' => 'native_replay',
        ], $changes));
    }

    /** @return list<array<string, mixed>> */
    private function rows(Connection $connection): array
    {
        return $connection->table('allowed_records')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
    }

    /** @param list<string> $queries */
    private function assertContainsDeleteWithoutUnsafeSql(array $queries): void
    {
        $this->assertNotEmpty(array_filter($queries, fn (string $sql): bool => preg_match('/\A\s*delete\b/i', $sql) === 1));
        $this->assertDoesNotMatchRegularExpression('/^\s*(?:truncate\b|set\b[^;]*\bforeign_key_checks\b)/im', implode("\n", $queries));
    }

    private function worker(string $manifest, string $project, string $worker, string $phase): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/seeder-generator-worker.php', $manifest, $worker, $phase], $project, [
            'SEEDER_GENERATOR_MYSQL_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1',
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'LARAVEL_CLOUD' => false, 'APP_BASE_PATH' => $project,
            'TELESCOPE_ENABLED' => 'false', 'PULSE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false',
        ]);
        $process->setTimeout(40);

        return $process;
    }

    private function runArtifactWorker(Application $app, DisposableMysql $mysql, string $identity, string $phase, string $status): void
    {
        $worker = $this->worker($mysql->path.'/seeder-workers.json', $app->basePath(), $identity, $phase);

        try {
            $worker->start();
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());
            $this->assertSame($status, json_decode(trim($worker->getOutput()), true, flags: JSON_THROW_ON_ERROR)['status']);
        } finally {
            if ($worker->isRunning()) {
                $worker->stop(2);
            }

            $this->assertFalse($worker->isRunning());
        }
    }

    private function publishRelease(DisposableMysql $mysql): void
    {
        $settings = $mysql->settings();
        DisposableMysql::verifiedConnection($settings);
        $directory = $mysql->path.'/barrier';
        $this->assertFalse(is_link($directory));
        $temporary = $directory.'/.seeder-release-'.$settings['token'].'-'.bin2hex(random_bytes(8));

        try {
            $this->assertFileDoesNotExist($temporary);
            $this->assertSame(32, file_put_contents($temporary, $settings['token']));
            $this->assertTrue(rename($temporary, $directory.'/seeder-release'));
        } finally {
            if (is_file($temporary) && ! is_link($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function waitUntil(callable $condition, Process $worker): void
    {
        $deadline = microtime(true) + 20;

        while (! $condition()) {
            if (! $worker->isRunning() || microtime(true) >= $deadline) {
                $this->fail('Owned seeder worker did not reach its DELETE barrier. '.$worker->getErrorOutput().$worker->getOutput());
            }

            usleep(10000);
            clearstatcache();
        }
    }

    /** @return array<string, string> */
    private function projectSnapshot(string $project): array
    {
        $result = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($project, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            $this->assertFalse($entry->isLink());
            $path = $entry->getPathname();

            if (str_replace('\\', '/', $path) === str_replace('\\', '/', $project).'/.start-kit-generator.lock') {
                clearstatcache(true, $path);
                $stat = lstat($path);
                $this->assertIsArray($stat);
                $this->assertSame(0100000, $stat['mode'] & 0170000);
                $this->assertSame(1, $stat['nlink']);
                $this->assertSame(0, $stat['size']);
                $result[$path] = 'lock:'.json_encode([
                    'dev' => $stat['dev'], 'ino' => $stat['ino'], 'nlink' => $stat['nlink'], 'size' => $stat['size'],
                ], JSON_THROW_ON_ERROR);

                continue;
            }

            if ($entry->isDir()) {
                $result[$path] = 'directory';

                continue;
            }

            $hash = hash_file('sha256', $path);
            $this->assertIsString($hash);
            $result[$path] = $hash;
        }

        ksort($result);

        return $result;
    }
}
