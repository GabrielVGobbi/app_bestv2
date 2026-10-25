<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Notifications\EventHandler;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\TestCase;
use Tests\TestEnvironment;
use Throwable;
use ZipArchive;

require_once __DIR__.'/DatabaseDumpContentTest.php';

class BackupRestoreNativeTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private string $token;

    /** @var list<DisposableMysql|BkpDisposablePostgres> */
    private array $servers = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('OPS_RESTORE_NATIVE_TESTS') !== '1' || getenv('BKP_DUMP_NATIVE_TESTS') !== '1') {
            $this->markTestSkipped('Explicit OPS_RESTORE_NATIVE_TESTS=1 and BKP_DUMP_NATIVE_TESTS=1 are required.');
        }

        TestEnvironment::assertSafeEnvironment();
        TestEnvironment::assertSafeDatabase(config());
        $this->token = bin2hex(random_bytes(16));
        $this->root = sys_get_temp_dir().'/start-kit-restore-'.$this->token;

        if (file_exists($this->root) || is_link($this->root)) {
            throw new RuntimeException('Refusing a preexisting restore fixture directory.');
        }

        (new Filesystem)->ensureDirectoryExists($this->root);
        file_put_contents($this->root.'/owner', $this->token);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        $failures = [];

        try {
            foreach (array_reverse($this->servers) as $server) {
                try {
                    $server->cleanUp();
                } catch (Throwable $exception) {
                    $failures[] = $exception;
                }
            }

            if (isset($this->root) && $failures === []) {
                if (realpath(dirname($this->root)) !== realpath(sys_get_temp_dir()) || is_link($this->root)
                    || basename($this->root) !== 'start-kit-restore-'.$this->token
                    || file_get_contents($this->root.'/owner') !== $this->token) {
                    throw new RuntimeException('Refusing cleanup of an unowned restore fixture.');
                }

                (new Filesystem)->deleteDirectory($this->root);
                $this->assertDirectoryDoesNotExist($this->root);
            }

            if ($failures !== []) {
                throw new RuntimeException('Owned restore server cleanup failed; its diagnostic paths were preserved.', 0, $failures[0]);
            }
        } finally {
            EventHandler::enable();
            parent::tearDown();
        }
    }

    #[DataProvider('restoreProfiles')]
    public function test_real_backup_zip_restores_schema_data_constraints_and_private_files(string $driver, bool $encrypted): void
    {
        $policyFile = base_path('config/backup.php');
        $originalPolicy = require $policyFile;
        $this->assertIsArray($originalPolicy['backup']['source']['files']);
        $main = DB::connection()->getPdo();
        [$source, $target, $sourceConfig, $targetConfig, $binaryDirectory, $verify] = $this->databases($driver);
        $this->seedSource($source, $driver);
        $sourceBefore = $this->databaseSnapshot($source, $driver);
        $sourceHash = $driver === 'sqlite' ? hash_file('sha256', $sourceConfig['database']) : null;
        $verify();
        $this->assertSame(0, (int) $target->query('SELECT COUNT(*) FROM '.($driver === 'sqlite'
            ? "sqlite_master WHERE type='table' AND name IN ('restoreparent','restorechild')"
            : ($driver === 'pgsql' ? "information_schema.tables WHERE table_schema='public' AND table_name IN ('restoreparent','restorechild')"
                : "information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('restoreparent','restorechild')")))->fetchColumn());
        $project = $this->root.'/project';
        (new Filesystem)->ensureDirectoryExists($project);
        $project = realpath($project);
        $this->assertIsString($project);
        $this->isolateProjectFiles();
        $this->app->setBasePath($project);
        $this->app->useAppPath($project.'/app');
        $this->app->useConfigPath($project.'/config');
        $this->app->useBootstrapPath($project.'/bootstrap');
        $this->app->useStoragePath($project.'/storage');
        $this->app->useDatabasePath($project.'/database');
        $this->app->usePublicPath($project.'/public');
        $this->app->useEnvironmentPath($project);
        $positive = ['README.txt' => 'OPS003 recoverable project '.$this->token,
            'app/recovery-fixture.txt' => "OPS003 schema/data/files\n",
            'public/recovery.txt' => 'OPS003 public fixture',
            'storage/app/private/report.bin' => random_bytes(256)];
        $forbidden = ['.env', '.env.production', '.config', '.config.production', '.aws/credentials', '.ssh/id_ed25519',
            'auth.json', '.npmrc', 'bootstrap/cache/config.php', 'storage/logs/laravel.log', 'storage/debugbar/private.json',
            'database/existing.sqlite', 'database/existing.sqlite-wal', 'vendor/private.php', 'node_modules/private.js', '.git/config'];

        foreach ($positive as $path => $contents) {
            $this->writeFixture($project.'/'.$path, $contents);
        }

        foreach ($forbidden as $path) {
            $this->writeFixture($project.'/'.$path, 'OPS003_FORBIDDEN_'.$this->token.'_'.$path);
        }

        $fileHashes = [];

        foreach ([...array_keys($positive), ...$forbidden] as $path) {
            $fileHashes[$path] = hash_file('sha256', $project.'/'.$path);
        }

        $policy = require $policyFile;
        $this->assertSame($project, $policy['backup']['source']['files']['relative_path']);
        $password = $encrypted ? bin2hex(random_bytes(32)) : null;
        $policy['backup']['name'] = 'ops003-'.$this->token;
        $policy['backup']['source']['databases'] = ['ops_restore_source'];
        $policy['backup']['destination']['disks'] = ['ops_restore_private'];
        $policy['backup']['temporary_directory'] = $this->root.'/sdk-temp';
        $policy['backup']['password'] = $password;
        $policy['backup']['encryption'] = $encrypted ? 'aes256' : 'none';
        $policy['backup']['verify_backup'] = true;
        $policy['backup']['tries'] = 1;
        config(['database.connections.ops_restore_source' => $sourceConfig,
            'backup' => $policy, 'ops_restore_profile' => $policy,
            'filesystems.disks.ops_restore_private' => ['driver' => 'local', 'root' => $this->root.'/archive', 'visibility' => 'private', 'throw' => true]]);
        $this->app->instance(BackupConfig::class, BackupConfig::fromArray($policy));
        Storage::forgetDisk('ops_restore_private');

        try {
            $this->assertSame(0, Artisan::call('backup:run', ['--config' => 'ops_restore_profile', '--disable-notifications' => true,
                '--filename' => '2030-01-01-00-00-00.zip', '--no-interaction' => true]), 'The real SDK backup command must succeed.');
            $disk = Storage::disk('ops_restore_private');
            $archives = $disk->allFiles($policy['backup']['name']);
            $this->assertCount(1, $archives);
            $this->assertSame('private', $disk->getConfig()['visibility']);
            $archive = $disk->path($archives[0]);
            $this->assertFalse(str_starts_with(strtolower(realpath($archive)), strtolower(realpath($project.'/public')).DIRECTORY_SEPARATOR));
            $extracted = $this->extractChecked($archive, $password, $positive, $forbidden);
            $dumps = array_values(array_filter(array_keys($extracted), fn (string $name): bool => str_starts_with($name, 'db-dumps/') && str_ends_with($name, '.sql')));
            $this->assertCount(1, $dumps);
            $dump = $extracted[$dumps[0]];
            $sql = file_get_contents($dump);
            $this->assertIsString($sql);
            $this->assertStringContainsString('CREATE TABLE', $sql);
            $this->assertStringContainsString('OPS003_PARENT', $sql);
            $this->assertStringNotContainsString('isolation_owner', $sql, 'The target ownership marker must never be imported from the source.');
            $this->assertDoesNotMatchRegularExpression('/\bCREATE\s+DATABASE\b|\bUSE\s+[`"a-z]|\\\\connect\b|\bATTACH\s+DATABASE\b/i', $sql);
            $verify();
            $this->restore($driver, $targetConfig, $binaryDirectory, $dump);
            $verify();
            $this->assertSame($sourceBefore, $this->databaseSnapshot($target, $driver));
            $this->assertConstraints($target, $driver);
            $ciphertext = $target->query('SELECT secret FROM restoreparent WHERE id=1')->fetchColumn();
            $this->assertIsString($ciphertext);
            $this->assertSame('OPS003_PROTECTED_VALUE', Crypt::decryptString($ciphertext));

            try {
                (new Encrypter(random_bytes(32), 'AES-256-CBC'))->decryptString($ciphertext);
                $this->fail('Restored encrypted data requires the separately retained application key.');
            } catch (DecryptException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }

            foreach ($positive as $path => $contents) {
                $this->assertSame(hash('sha256', $contents), hash_file('sha256', $extracted[$path]));
            }

            Mail::assertNothingOutgoing();
            Http::assertNothingSent();
            $verify();
            $this->assertSame($sourceBefore, $this->databaseSnapshot($source, $driver));
            $proof = [
                'schema' => 'OPS003_NATIVE_RESTORE_PROOF_V1',
                'driver' => $driver, 'encrypted' => $encrypted, 'token' => $this->token,
                'source' => array_intersect_key($sourceConfig, array_flip(['driver', 'host', 'port', 'database'])),
                'target' => array_intersect_key($targetConfig, array_flip(['driver', 'host', 'port', 'database'])),
                'archive_sha256' => hash_file('sha256', $archive), 'archive_bytes' => filesize($archive),
                'dump_sha256' => hash_file('sha256', $dump), 'dump_bytes' => filesize($dump),
                'restored_files' => array_map(fn (string $path): array => ['sha256' => hash_file('sha256', $path), 'bytes' => filesize($path)], $extracted),
                'source_snapshot_sha256' => hash('sha256', json_encode($sourceBefore, JSON_THROW_ON_ERROR)),
                'target_snapshot_sha256' => hash('sha256', json_encode($this->databaseSnapshot($target, $driver), JSON_THROW_ON_ERROR)),
                'constraints_verified' => true, 'separate_app_key_verified' => true,
                'source_sqlite_sha256' => $sourceHash,
                'source_sqlite_unchanged' => $sourceHash === null || $sourceHash === hash_file('sha256', $sourceConfig['database']),
                'fixture_guard_excluded_from_dump_only' => $driver !== 'sqlite',
                'restore_client_exit' => 0,
            ];
            $this->assertNotFalse(file_put_contents(sys_get_temp_dir().'/ops003-restore-proof-'.$this->token.'.json', json_encode($proof, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)));
        } finally {
            $verify();
            $this->assertSame($sourceBefore, $this->databaseSnapshot($source, $driver));

            if ($sourceHash !== null) {
                $this->assertSame($sourceHash, hash_file('sha256', $sourceConfig['database']));
            }

            foreach ($fileHashes as $path => $hash) {
                $this->assertSame($hash, hash_file('sha256', $project.'/'.$path));
            }

            TestEnvironment::assertSafeDatabase(config());
            $this->assertSame($main, DB::connection()->getPdo());
            DB::purge('ops_restore_source');
            EventHandler::enable();
        }
    }

    /** @return array<string, array{string, bool}> */
    public static function restoreProfiles(): array
    {
        return ['SQLite plaintext' => ['sqlite', false], 'SQLite AES256' => ['sqlite', true],
            'MySQL AES256' => ['mysql', true], 'PostgreSQL AES256' => ['pgsql', true]];
    }

    /** @return array{PDO, PDO, array<string, mixed>, array<string, mixed>, string, callable(): void} */
    private function databases(string $driver): array
    {
        if ($driver === 'sqlite') {
            $directory = $this->approvedDirectory('BKP_SQLITE_BINARY_DIRECTORY', ['sqlite3.exe']);
            $sourcePath = $this->root.'/source.sqlite';
            $targetPath = $this->root.'/target.sqlite';
            $source = new PDO('sqlite:'.$sourcePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $target = new PDO('sqlite:'.$targetPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $source->exec('PRAGMA foreign_keys=ON');
            $target->exec('PRAGMA foreign_keys=ON');
            $sourceConfig = ['driver' => 'sqlite', 'database' => $sourcePath, 'dump' => ['dump_binary_path' => $directory, 'timeout' => 60]];
            $targetConfig = ['database' => $targetPath];
            $verify = function () use ($sourcePath, $targetPath): void {
                foreach ([$sourcePath, $targetPath] as $path) {
                    $this->assertSame(realpath($this->root), realpath(dirname($path)));
                    $this->assertFalse(is_link($path));
                    $this->assertSame(1, lstat($path)['nlink']);
                }

                $this->assertSame($this->token, file_get_contents($this->root.'/owner'));
            };
        } elseif ($driver === 'mysql') {
            $binary = getenv('AUTHORITY_MYSQLD_BINARY');

            if (! is_string($binary) || ! is_file($binary) || basename($binary) !== 'mysqld.exe') {
                throw new RuntimeException('The owned MySQL server binary must be explicitly approved.');
            }

            $directory = realpath(dirname($binary));
            $this->assertIsString($directory);
            $this->assertFileExists($directory.'/mysql.exe');
            $this->assertFileExists($directory.'/mysqldump.exe');
            foreach (['mysql.exe', 'mysqldump.exe'] as $executable) {
                $this->assertSame($directory, dirname((string) realpath($directory.'/'.$executable)));
            }
            $sourceServer = new DisposableMysql;
            $this->servers[] = $sourceServer;
            $sourceServer->start();
            $targetServer = new DisposableMysql;
            $this->servers[] = $targetServer;
            $targetServer->start();
            $sourceSettings = $sourceServer->settings();
            $targetSettings = $targetServer->settings();
            $source = DisposableMysql::verifiedConnection($sourceSettings);
            $target = DisposableMysql::verifiedConnection($targetSettings);
            $configuration = fn (array $settings): array => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $settings['port'],
                'database' => $settings['schema'], 'username' => $settings['username'], 'password' => $settings['password'],
                'url' => null, 'unix_socket' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'];
            $sourceConfig = $configuration($sourceSettings);
            $sourceConfig['dump'] = ['dump_binary_path' => $directory, 'exclude_tables' => ['isolation_owner'], 'timeout' => 60];
            $targetConfig = $configuration($targetSettings);
            $verify = function () use ($sourceSettings, $targetSettings): void {
                $this->assertNotSame($sourceSettings['path'], $targetSettings['path']);
                $this->assertNotSame($sourceSettings['port'], $targetSettings['port']);
                DisposableMysql::verifiedConnection($sourceSettings);
                DisposableMysql::verifiedConnection($targetSettings);
            };
        } else {
            $directory = $this->approvedDirectory('BKP_PG_BINARY_DIRECTORY', ['initdb.exe', 'postgres.exe', 'pg_ctl.exe', 'pg_dump.exe', 'psql.exe']);
            $sourceServer = new BkpDisposablePostgres;
            $this->servers[] = $sourceServer;
            $sourceServer->start();
            $targetServer = new BkpDisposablePostgres;
            $this->servers[] = $targetServer;
            $targetServer->start();
            $source = $sourceServer->verifiedConnection();
            $target = $targetServer->verifiedConnection();
            $sourceConfig = ['driver' => 'pgsql', ...$sourceServer->configuration(), 'dump' => ['dump_binary_path' => $directory,
                'exclude_tables' => ['isolation_owner'], 'add_extra_option' => '--no-owner --no-privileges', 'timeout' => 60]];
            $targetConfig = $targetServer->configuration();
            $verify = function () use ($sourceServer, $targetServer): void {
                $this->assertNotSame($sourceServer->configuration()['port'], $targetServer->configuration()['port']);
                $sourceServer->verifiedConnection();
                $targetServer->verifiedConnection();
            };
        }

        return [$source, $target, $sourceConfig, $targetConfig, $directory, $verify];
    }

    private function seedSource(PDO $pdo, string $driver): void
    {
        $suffix = $driver === 'mysql' ? ' ENGINE=InnoDB' : '';
        $pdo->exec('CREATE TABLE restoreparent (id INTEGER PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE, secret TEXT NOT NULL)'.$suffix);
        $pdo->exec('CREATE TABLE restorechild (id INTEGER PRIMARY KEY, parent_id INTEGER NOT NULL, payload VARCHAR(80) NOT NULL,
            FOREIGN KEY (parent_id) REFERENCES restoreparent(id))'.$suffix);
        $pdo->exec('CREATE INDEX restore_child_parent_idx ON restorechild(parent_id)');
        $this->assertSame('base64:'.base64_encode(str_repeat('t', 32)), config('app.key'));
        $insert = $pdo->prepare('INSERT INTO restoreparent (id,name,secret) VALUES (?,?,?)');
        $insert->execute([1, 'OPS003_PARENT', Crypt::encryptString('OPS003_PROTECTED_VALUE')]);
        $insert->execute([2, 'OPS003_SECOND', Crypt::encryptString('OPS003_SECOND_PROTECTED')]);
        $pdo->exec("INSERT INTO restorechild VALUES (10,1,'OPS003_CHILD'),(20,2,'OPS003_OTHER')");
    }

    /** @return array{parent: list<array<string, mixed>>, child: list<array<string, mixed>>, index: int} */
    private function databaseSnapshot(PDO $pdo, string $driver): array
    {
        $query = match ($driver) {
            'mysql' => "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='restorechild' AND index_name='restore_child_parent_idx'",
            'pgsql' => "SELECT COUNT(*) FROM pg_indexes WHERE schemaname='public' AND tablename='restorechild' AND indexname='restore_child_parent_idx'",
            default => "SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND tbl_name='restorechild' AND name='restore_child_parent_idx'",
        };
        $index = (int) $pdo->query($query)->fetchColumn();
        $this->assertSame(1, $index);

        return ['parent' => $pdo->query('SELECT id,name,secret FROM restoreparent ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
            'child' => $pdo->query('SELECT id,parent_id,payload FROM restorechild ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'index' => $index];
    }

    private function assertConstraints(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys=ON');
        }

        foreach (["INSERT INTO restoreparent (id,name,secret) VALUES (3,'OPS003_PARENT','OPS003_INVALID')", "INSERT INTO restorechild VALUES (30,999,'OPS003_INVALID')"] as $sql) {
            try {
                $pdo->exec($sql);
                $this->fail('A restored uniqueness/foreign-key constraint must reject invalid fixture data.');
            } catch (PDOException $exception) {
                $this->assertSame('23', substr((string) $exception->getCode(), 0, 2));
            }
        }

        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM restoreparent')->fetchColumn());
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM restorechild')->fetchColumn());
    }

    /** @param array<string, string> $positive @param list<string> $forbidden @return array<string, string> */
    private function extractChecked(string $archive, ?string $password, array $positive, array $forbidden): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive, ZipArchive::RDONLY));
        $entries = [];
        $seen = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                $this->assertIsString($name);
                $name = str_replace('\\', '/', $name);
                $this->assertNotSame('', $name);
                $this->assertFalse(str_contains($name, ':') || str_contains($name, "\0") || str_starts_with($name, '/'));
                $this->assertDoesNotMatchRegularExpression('~(?:\A|/)\.{1,2}(?:/|\z)~', $name);
                foreach (explode('/', rtrim($name, '/')) as $segment) {
                    $this->assertNotSame('', $segment);
                    $this->assertSame($segment, rtrim($segment, ' .'));
                    $this->assertDoesNotMatchRegularExpression('/[\x00-\x1f<>:"|?*]|^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $segment);
                }
                $folded = strtolower(rtrim($name, '/'));
                $this->assertArrayNotHasKey($folded, $seen, 'ZIP entries must not collide on the Windows target.');
                $seen[$folded] = true;
                $system = 0;
                $attributes = 0;
                $this->assertTrue($zip->getExternalAttributesIndex($index, $system, $attributes));
                $this->assertFalse($system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000);

                if (! str_ends_with($name, '/')) {
                    $this->assertTrue(isset($positive[$name]) || (str_starts_with($name, 'db-dumps/') && str_ends_with($name, '.sql')), 'Unexpected file in the actual SDK archive: '.$name);
                    $this->assertNotContains($name, $forbidden);
                    $this->assertSame($password === null ? ZipArchive::EM_NONE : ZipArchive::EM_AES_256, $zip->statIndex($index)['encryption_method']);
                    $entries[$name] = $index;
                }
            }

            if ($password !== null) {
                $first = array_values($entries)[0];
                $this->assertFalse($zip->getFromIndex($first));
                $this->assertTrue($zip->setPassword('wrong-'.$this->token));
                $this->assertFalse($zip->getFromIndex($first));
                $this->assertTrue($zip->setPassword($password));
            }

            $restore = $this->root.'/restored-files';
            $this->assertDirectoryDoesNotExist($restore);
            (new Filesystem)->ensureDirectoryExists($restore);
            $restored = [];

            foreach ($entries as $name => $index) {
                $contents = $zip->getFromIndex($index);
                $this->assertIsString($contents);
                $this->assertStringNotContainsString('OPS003_FORBIDDEN_'.$this->token, $contents);
                $target = $restore.'/'.$name;
                (new Filesystem)->ensureDirectoryExists(dirname($target));
                $this->assertTrue(str_starts_with(realpath(dirname($target)).DIRECTORY_SEPARATOR, realpath($restore).DIRECTORY_SEPARATOR));
                $this->assertFalse(file_exists($target) || is_link($target));
                $this->assertSame(strlen($contents), file_put_contents($target, $contents));
                $restored[$name] = $target;
            }

            foreach (array_keys($positive) as $name) {
                $this->assertArrayHasKey($name, $restored);
            }

            return $restored;
        } finally {
            $zip->close();
        }
    }

    /** @param array<string, mixed> $configuration */
    private function restore(string $driver, array $configuration, string $directory, string $dump): void
    {
        $credentials = $this->root.'/restore-credentials';
        $environment = ['PGSERVICE' => false, 'PGSERVICEFILE' => false, 'PGOPTIONS' => false, 'PGPASSWORD' => false, 'PGPASSFILE' => false,
            'MYSQL_PWD' => false, 'MYSQL_HOME' => false];

        if ($driver === 'mysql') {
            $clientHome = $this->root.'/mysql-client-home';
            $this->assertDirectoryDoesNotExist($clientHome);
            (new Filesystem)->ensureDirectoryExists($clientHome);
            foreach (['HOME', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA'] as $variable) {
                $environment[$variable] = $clientHome;
            }
            $environment['MYSQL_TEST_LOGIN_FILE'] = $clientHome.'/.mylogin.cnf';
            $this->assertFileDoesNotExist($environment['MYSQL_TEST_LOGIN_FILE']);
            $this->writeFixture($credentials, "[client]\nuser=".$configuration['username']."\npassword=".$configuration['password']."\n");
            $command = [$directory.'/mysql.exe', '--defaults-file='.$credentials, '--protocol=TCP', '--host=127.0.0.1',
                '--port='.$configuration['port'], '--database='.$configuration['database'], '--default-character-set=utf8mb4'];
        } elseif ($driver === 'pgsql') {
            $this->writeFixture($credentials, '127.0.0.1:'.$configuration['port'].':'.$configuration['database'].':'.$configuration['username'].':'.$configuration['password']."\n");
            chmod($credentials, 0600);
            $environment['PGPASSFILE'] = $credentials;
            $command = [$directory.'/psql.exe', '--no-psqlrc', '--host=127.0.0.1', '--port='.$configuration['port'],
                '--username='.$configuration['username'], '--dbname='.$configuration['database'], '--set=ON_ERROR_STOP=1', '--single-transaction'];
        } else {
            $command = [$directory.'/sqlite3.exe', '--bail', $configuration['database']];
        }

        $stream = fopen($dump, 'rb');
        $this->assertIsResource($stream);

        try {
            $process = new Process($command, $this->root, $environment);
            $process->setOptions(['create_new_console' => false, 'create_process_group' => true]);
            $process->setTimeout(60);
            $process->setInput($stream);
            $process->run();
            $this->assertTrue($process->isSuccessful(), 'Native restore failed: '.$process->getErrorOutput());
        } finally {
            fclose($stream);

            if (is_file($credentials)) {
                $this->assertTrue(unlink($credentials));
            }
        }
    }

    /** @param list<string> $executables */
    private function approvedDirectory(string $variable, array $executables): string
    {
        $configured = getenv($variable);
        $directory = is_string($configured) ? realpath($configured) : false;

        if ($directory === false) {
            throw new RuntimeException('An explicit approved restore binary directory is required.');
        }

        foreach ($executables as $executable) {
            $path = realpath($directory.'/'.$executable);

            if ($path === false || ! is_file($path) || dirname($path) !== $directory) {
                throw new RuntimeException('A restore executable escaped its approved directory.');
            }
        }

        return $directory;
    }

    private function writeFixture(string $path, string $contents): void
    {
        (new Filesystem)->ensureDirectoryExists(dirname($path));
        $this->assertSame(strlen($contents), file_put_contents($path, $contents));
    }
}
