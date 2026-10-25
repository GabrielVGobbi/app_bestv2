<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Modules\Backup\Jobs\ProcessDatabaseExport;
use App\Modules\Backup\Models\DatabaseExport;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Symfony\Component\Process\Process;
use Tests\DisposableMysql;
use Tests\TestCase;
use Tests\TestEnvironment;
use Throwable;

class DatabaseDumpContentTest extends TestCase
{
    use RefreshDatabase;

    private static ?DisposableMysql $mysql = null;

    private static ?BkpDisposablePostgres $postgres = null;

    private string $fixturePath;

    private string $fixtureToken;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (getenv('BKP_DUMP_NATIVE_TESTS') !== '1') {
            return;
        }

        TestEnvironment::assertSafeEnvironment();

        if (! extension_loaded('pdo_pgsql') || ! extension_loaded('pdo_mysql') || ! extension_loaded('sqlite3')) {
            throw new RuntimeException('Native dump tests require the explicitly prepared PDO/SQLite extensions.');
        }

        try {
            self::$mysql = new DisposableMysql;
            self::$mysql->start();
            self::$postgres = new BkpDisposablePostgres;
            self::$postgres->start();
        } catch (Throwable $exception) {
            $cleanupFailures = [];
            foreach ([self::$postgres, self::$mysql] as $fixture) {
                try {
                    $fixture?->cleanUp();
                } catch (Throwable $cleanup) {
                    $cleanupFailures[] = get_debug_type($fixture).': '.$cleanup->getMessage();
                }
            }
            if ($cleanupFailures !== []) {
                throw new RuntimeException('Native setup failed: '.$exception->getMessage().'; secondary cleanup failures: '.implode('; ', $cleanupFailures), 0, $exception);
            }

            throw $exception;
        }
    }

    public static function tearDownAfterClass(): void
    {
        try {
            self::$postgres?->cleanUp();
        } finally {
            try {
                self::$mysql?->cleanUp();
            } finally {
                parent::tearDownAfterClass();
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('BKP_DUMP_NATIVE_TESTS') !== '1') {
            $this->markTestSkipped('Explicit BKP_DUMP_NATIVE_TESTS=1 opt-in is required.');
        }

        $this->assertMainDatabase();
        $this->fixtureToken = bin2hex(random_bytes(16));
        $this->fixturePath = sys_get_temp_dir().'/start-kit-bkp-content-'.$this->fixtureToken;

        if (file_exists($this->fixturePath) || is_link($this->fixturePath)) {
            throw new RuntimeException('Refusing a preexisting native dump directory.');
        }

        (new Filesystem)->ensureDirectoryExists($this->fixturePath);
        file_put_contents($this->fixturePath.'/owner', $this->fixtureToken);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->fixturePath)) {
                if (realpath(dirname($this->fixturePath)) !== realpath(sys_get_temp_dir())
                    || basename($this->fixturePath) !== 'start-kit-bkp-content-'.$this->fixtureToken
                    || is_link($this->fixturePath)
                    || file_get_contents($this->fixturePath.'/owner') !== $this->fixtureToken) {
                    throw new RuntimeException('Refusing cleanup of an unowned native dump directory.');
                }

                (new Filesystem)->deleteDirectory($this->fixturePath);
                $this->assertDirectoryDoesNotExist($this->fixturePath);
            }
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('dumpModes')]
    public function test_real_dump_content_and_literal_filters_preserve_the_source(string $driver, string $type): void
    {
        [$pdo, $configuration, $binaryDirectory] = $this->source($driver);
        $before = $this->sourceIdentity($pdo);
        $sqliteHash = $driver === 'sqlite' ? hash_file('sha256', $configuration['database']) : null;

        try {
            foreach (['all', 'include', 'exclude'] as $filter) {
                $export = new DatabaseExport(['export_type' => $type, 'tables' => $filter === 'include' ? ['bkpselected'] : null]);
                $settings = [...$configuration, 'dump' => ['exclude_tables' => $filter === 'exclude' ? ['bkpneighbor'] : []]];
                $target = $this->fixturePath.'/'.$driver.'-'.$type.'-'.$filter.'.sql';
                $this->assertFileDoesNotExist($target);
                $dumper = (new BkpNativeDumpProbe($export))->dumper($driver, $settings, $export);
                $dumper->setDumpBinaryPath($binaryDirectory)->dumpToFile($target);
                $this->assertFileExists($target);
                $sql = file_get_contents($target);
                $this->assertIsString($sql);
                $this->assertNotSame('', $sql);
                $this->assertStringContainsString('bkpselected', $sql);
                if ($filter === 'all') {
                    $this->assertStringContainsString('bkpneighbor', $sql);
                    if ($type === DatabaseExport::TYPE_SCHEMA) {
                        $this->assertStringNotContainsString('BKP_NEIGHBOR_SENTINEL', $sql);
                    } else {
                        $this->assertStringContainsString('BKP_NEIGHBOR_SENTINEL', $sql);
                    }
                } else {
                    $this->assertStringNotContainsString('bkpneighbor', $sql);
                    $this->assertStringNotContainsString('BKP_NEIGHBOR_SENTINEL', $sql);
                }

                if ($type === DatabaseExport::TYPE_SCHEMA) {
                    $this->assertStringContainsString('CREATE TABLE', $sql);
                    $this->assertStringNotContainsString('BKP_SELECTED_SENTINEL', $sql);
                } elseif ($type === DatabaseExport::TYPE_DATA) {
                    $this->assertStringNotContainsString('CREATE TABLE', $sql);
                    $this->assertStringContainsString('BKP_SELECTED_SENTINEL', $sql);
                } else {
                    $this->assertStringContainsString('CREATE TABLE', $sql);
                    $this->assertStringContainsString('BKP_SELECTED_SENTINEL', $sql);
                }

                $this->assertSame($before, $this->sourceIdentity($pdo));
                $this->verifyServer($driver);
                $this->assertMainDatabase();
            }
        } finally {
            $this->assertSame($before, $this->sourceIdentity($pdo));

            if ($sqliteHash !== null) {
                $this->assertSame($sqliteHash, hash_file('sha256', $configuration['database']));
            }

            $this->verifyServer($driver);
            $this->assertMainDatabase();
        }
    }

    public function test_sqlite_all_excluded_refuses_instead_of_producing_an_unfiltered_dump(): void
    {
        [$pdo, $configuration] = $this->source('sqlite');
        $before = $this->sourceIdentity($pdo);
        $hash = hash_file('sha256', $configuration['database']);
        $export = new DatabaseExport(['export_type' => 'full', 'tables' => null]);

        try {
            (new BkpNativeDumpProbe($export))->dumper('sqlite', [...$configuration,
                'dump' => ['exclude_tables' => ['bkpselected', 'bkpneighbor']]], $export);
            $this->fail('An empty effective SQLite selection must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('selection', $exception->getMessage());
        } finally {
            $this->assertSame($before, $this->sourceIdentity($pdo));
            $this->assertSame($hash, hash_file('sha256', $configuration['database']));
            $this->assertSame([], glob($this->fixturePath.'/*.sql'));
        }
    }

    public function test_nullable_history_migration_preserves_owned_mysql_rows_and_refuses_a_lossy_rollback(): void
    {
        $this->assertMainDatabase();
        $settings = $this->mysqlSettings();
        DisposableMysql::verifiedConnection($settings);
        $name = 'bkp_owned_'.substr($this->fixtureToken, 0, 16);
        config(['database.connections.'.$name => $this->mysqlConfiguration($settings)]);
        $connection = DB::connection($name);
        $native = $connection->getPdo();
        $this->assertSame($settings['token'], $native->query('SELECT token FROM isolation_owner')->fetchColumn());
        $this->assertSame($settings['port'], (int) $native->query('SELECT @@port')->fetchColumn());
        $main = DB::connection()->getPdo();
        $native->exec('CREATE TABLE backup_histories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE,
            disk VARCHAR(255) NOT NULL, path VARCHAR(255) NULL, file_name VARCHAR(255) NULL, file_size BIGINT UNSIGNED NULL,
            status VARCHAR(255) NOT NULL, error_message TEXT NULL, backed_up_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL) ENGINE=InnoDB');
        $native->exec('CREATE TABLE migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL) ENGINE=InnoDB');
        $native->exec("INSERT INTO migrations (migration,batch) VALUES ('bkp_owned_fixture',1)");
        $uuid = '11111111-1111-4111-8111-111111111111';
        $native->exec("INSERT INTO backup_histories (uuid,disk,status,backed_up_at) VALUES ('{$uuid}','local','completed','2030-01-01 00:00:00')");
        $before = $native->query('SELECT * FROM backup_histories')->fetchAll(PDO::FETCH_ASSOC);
        $options = ['--database' => $name, '--path' => [base_path('app/Modules/Backup/Database/Migrations/2026_10_18_223539_make_backup_history_disk_nullable.php')],
            '--realpath' => true, '--force' => true, '--no-interaction' => true];

        try {
            $this->assertSame(0, $this->migrationCommand('migrate', $options));
            $this->assertSame($before, $native->query('SELECT * FROM backup_histories')->fetchAll(PDO::FETCH_ASSOC));
            $this->assertSame('YES', $native->query("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='backup_histories' AND column_name='disk'")->fetchColumn());
            $native->exec("INSERT INTO backup_histories (uuid,disk,status,backed_up_at) VALUES ('22222222-2222-4222-8222-222222222222',NULL,'failed','2030-01-02 00:00:00')");
            $withNull = $native->query('SELECT * FROM backup_histories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

            try {
                $this->migrationCommand('migrate:rollback', [...$options, '--step' => 1]);
                $this->fail('Rollback must refuse unknown backup destinations before changing the schema.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('without a disk', $exception->getMessage());
            }

            $this->assertSame($withNull, $native->query('SELECT * FROM backup_histories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
            $this->assertSame('YES', $native->query("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='backup_histories' AND column_name='disk'")->fetchColumn());
            $this->assertSame(1, $native->exec("DELETE FROM backup_histories WHERE uuid='22222222-2222-4222-8222-222222222222' AND disk IS NULL"));
            $this->assertSame(0, $this->migrationCommand('migrate:rollback', [...$options, '--step' => 1]));
            $this->assertSame('NO', $native->query("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='backup_histories' AND column_name='disk'")->fetchColumn());
            $this->assertSame($before, $native->query('SELECT * FROM backup_histories')->fetchAll(PDO::FETCH_ASSOC));
            $this->assertSame($native, $connection->getPdo());
            DisposableMysql::verifiedConnection($settings);
        } finally {
            DB::purge($name);
            config(['database.connections.'.$name => null]);
            $this->assertMainDatabase();
            $this->assertSame($main, DB::connection()->getPdo());
        }
    }

    /** @return array<string, array{string, string}> */
    public static function dumpModes(): array
    {
        $cases = [];

        foreach (['mysql', 'mariadb', 'pgsql'] as $driver) {
            foreach (['schema', 'data', 'full'] as $type) {
                $cases[($driver === 'mariadb' ? 'mariadb alias on mysql' : $driver).' '.$type] = [$driver, $type];
            }
        }

        $cases['sqlite full'] = ['sqlite', 'full'];

        return $cases;
    }

    /** @return array{PDO, array<string, mixed>, string} */
    private function source(string $driver): array
    {
        if ($driver === 'sqlite') {
            $path = $this->fixturePath.'/source.sqlite';
            $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $configuration = ['database' => $path];
            $configured = getenv('BKP_SQLITE_BINARY_DIRECTORY');
            $binaryDirectory = is_string($configured) ? realpath($configured) : false;

            if ($binaryDirectory === false) {
                throw new RuntimeException('An explicitly approved SQLite binary directory is required.');
            }

            $executable = realpath($binaryDirectory.'/sqlite3.exe');

            if ($executable === false || ! is_file($executable) || dirname($executable) !== $binaryDirectory) {
                throw new RuntimeException('The approved SQLite binary must remain inside its explicit directory.');
            }
        } elseif ($driver === 'pgsql') {
            $fixture = self::$postgres ?? throw new RuntimeException('Owned PostgreSQL fixture is not running.');
            $pdo = $fixture->verifiedConnection();
            $configuration = $fixture->configuration();
            $binaryDirectory = $fixture->binaryDirectory();
        } else {
            $settings = $this->mysqlSettings();
            $pdo = DisposableMysql::verifiedConnection($settings);
            $configuration = $this->mysqlConfiguration($settings);
            $binary = getenv('AUTHORITY_MYSQLD_BINARY');

            if (! is_string($binary) || basename($binary) !== 'mysqld.exe' || ! is_file($binary)) {
                throw new RuntimeException('Native MySQL requires the runner-approved private binary.');
            }

            $binaryDirectory = dirname($binary);
            $this->assertFileExists($binaryDirectory.'/mysqldump.exe');
        }

        foreach (['bkpselected' => 'BKP_SELECTED_SENTINEL', 'bkpneighbor' => 'BKP_NEIGHBOR_SENTINEL'] as $table => $value) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS '.$table.' (id INTEGER PRIMARY KEY, value VARCHAR(80) NOT NULL)');
            $count = (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();

            if ($count === 0) {
                $statement = $pdo->prepare('INSERT INTO '.$table.' (id,value) VALUES (?,?)');
                $statement->execute([1, $value]);
            }
        }

        return [$pdo, $configuration, $binaryDirectory];
    }

    /** @return array{hash: string, counts: array<string, int>} */
    private function sourceIdentity(PDO $pdo): array
    {
        $rows = [];
        $counts = [];

        foreach (['bkpselected', 'bkpneighbor'] as $table) {
            $rows[$table] = $pdo->query('SELECT id,value FROM '.$table.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
            $this->assertSame(1, $counts[$table]);
        }

        $this->assertSame('BKP_SELECTED_SENTINEL', $rows['bkpselected'][0]['value']);
        $this->assertSame('BKP_NEIGHBOR_SENTINEL', $rows['bkpneighbor'][0]['value']);

        return ['hash' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)), 'counts' => $counts];
    }

    private function verifyServer(string $driver): void
    {
        if ($driver === 'pgsql') {
            self::$postgres?->verifiedConnection();
        } elseif ($driver !== 'sqlite') {
            DisposableMysql::verifiedConnection($this->mysqlSettings());
        }
    }

    /** @return array{path: string, token: string, port: int, schema: string, username: string, password: string} */
    private function mysqlSettings(): array
    {
        return (self::$mysql ?? throw new RuntimeException('Owned MySQL fixture is not running.'))->settings();
    }

    /**
     * @param  array{path: string, token: string, port: int, schema: string, username: string, password: string}  $settings
     * @return array<string, mixed>
     */
    private function mysqlConfiguration(array $settings): array
    {
        return ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $settings['port'], 'database' => $settings['schema'],
            'username' => $settings['username'], 'password' => $settings['password'], 'unix_socket' => '', 'url' => null,
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => 'InnoDB'];
    }

    private function assertMainDatabase(): void
    {
        TestEnvironment::assertSafeDatabase(config());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    /** @param array<string, mixed> $options */
    private function migrationCommand(string $command, array $options): int
    {
        $default = DB::getDefaultConnection();
        $main = DB::connection()->getPdo();
        Schema::clearResolvedInstance('db.schema');
        $this->app->forgetInstance('db.schema');

        try {
            return Artisan::call($command, $options);
        } finally {
            DB::setDefaultConnection($default);
            Schema::clearResolvedInstance('db.schema');
            $this->app->forgetInstance('db.schema');
            $this->assertMainDatabase();
            $this->assertSame($main, DB::connection()->getPdo());
        }
    }
}

class BkpNativeDumpProbe extends ProcessDatabaseExport
{
    /** @param array<string, mixed> $configuration */
    public function dumper(string $driver, array $configuration, DatabaseExport $export): MySql|PostgreSql|Sqlite
    {
        return parent::buildDumper($driver, $configuration, $export);
    }
}

final class BkpDisposablePostgres
{
    private readonly string $binaryDirectory;

    private readonly string $token;

    private readonly string $path;

    private readonly string $username;

    private readonly string $password;

    private readonly string $database;

    private readonly int $port;

    private ?Process $server = null;

    public function __construct()
    {
        if (getenv('BKP_DUMP_NATIVE_TESTS') !== '1') {
            throw new RuntimeException('Owned PostgreSQL requires the native test opt-in.');
        }

        $configured = getenv('BKP_PG_BINARY_DIRECTORY');
        $binaryDirectory = is_string($configured) ? realpath($configured) : false;

        if ($binaryDirectory === false) {
            throw new RuntimeException('An explicitly approved PostgreSQL binary directory is required.');
        }

        $this->binaryDirectory = $binaryDirectory;
        $this->binaryDirectory();
        $this->token = bin2hex(random_bytes(16));
        $this->path = sys_get_temp_dir().'/start-kit-bkp-pg-'.$this->token;
        $this->username = 'bkp'.substr($this->token, 0, 16);
        $this->database = 'bkp'.$this->token;
        $this->password = bin2hex(random_bytes(32));
        $this->port = $this->availablePort();
    }

    public function start(): void
    {
        if (file_exists($this->path) || is_link($this->path)) {
            throw new RuntimeException('Refusing a preexisting PostgreSQL fixture directory.');
        }

        (new Filesystem)->ensureDirectoryExists($this->path);
        file_put_contents($this->path.'/owner', $this->token);
        file_put_contents($this->path.'/password', $this->password."\n");
        $this->assertOwned();
        $initialize = $this->process([$this->binaryDirectory().'/initdb.exe', '-D', $this->path.'/data', '--no-locale', '--encoding=UTF8',
            '--auth=scram-sha-256', '--username='.$this->username, '--pwfile='.$this->path.'/password']);
        $initialize->setTimeout(90);
        $initialize->run();

        if (! $initialize->isSuccessful()) {
            throw new RuntimeException('Owned PostgreSQL initialization failed; credential-bearing output is withheld.');
        }

        $this->server = $this->process([$this->binaryDirectory().'/postgres.exe', '-D', $this->path.'/data', '-h', '127.0.0.1', '-p', (string) $this->port,
            '-c', 'unix_socket_directories=', '-c', 'logging_collector=off']);
        $this->server->setTimeout(null);
        $this->server->start();
        $deadline = microtime(true) + 45;

        do {
            if (! $this->server->isRunning()) {
                throw new RuntimeException('Owned PostgreSQL exited before readiness.');
            }

            try {
                $admin = $this->connect('postgres');
                $this->assertServerIdentity($admin);
                $admin->exec('CREATE DATABASE '.$this->database);
                $pdo = $this->connect($this->database);
                $pdo->exec('CREATE TABLE isolation_owner (token CHAR(32) PRIMARY KEY)');
                $statement = $pdo->prepare('INSERT INTO isolation_owner VALUES (?)');
                $statement->execute([$this->token]);
                $this->verifiedConnection();
                unlink($this->path.'/password');

                return;
            } catch (PDOException) {
                usleep(100000);
            }
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Owned PostgreSQL readiness timed out.');
    }

    /** @return array{host: string, port: int, database: string, username: string, password: string} */
    public function configuration(): array
    {
        $this->verifiedConnection();

        return ['host' => '127.0.0.1', 'port' => $this->port, 'database' => $this->database,
            'username' => $this->username, 'password' => $this->password];
    }

    public function verifiedConnection(): PDO
    {
        $this->assertOwned();
        $pdo = $this->connect($this->database);
        $this->assertServerIdentity($pdo);

        if ($pdo->query('SELECT token FROM isolation_owner')->fetchColumn() !== $this->token
            || $pdo->query('SELECT current_database()')->fetchColumn() !== $this->database) {
            throw new RuntimeException('Owned PostgreSQL database marker mismatch.');
        }

        return $pdo;
    }

    public function cleanUp(): void
    {
        try {
            if (! file_exists($this->path)) {
                return;
            }

            $this->assertOwned();

            if ($this->server?->isRunning()) {
                $this->assertServerIdentity($this->connect('postgres'));
                $stop = $this->process([$this->binaryDirectory().'/pg_ctl.exe', '-D', $this->path.'/data', 'stop', '-m', 'fast', '-w', '-t', '15']);
                $stop->setTimeout(20);
                $stop->run();

                if (! $stop->isSuccessful()) {
                    throw new RuntimeException('Owned PostgreSQL graceful shutdown failed.');
                }
            }
        } finally {
            if ($this->server?->isRunning()) {
                $this->server->stop(3);
            }
        }

        if ($this->server?->isRunning() || is_file($this->path.'/data/postmaster.pid')) {
            throw new RuntimeException('Refusing deletion of an active PostgreSQL data directory.');
        }

        $this->assertOwned();
        (new Filesystem)->deleteDirectory($this->path);

        if (file_exists($this->path)) {
            throw new RuntimeException('Owned PostgreSQL fixture was not fully removed.');
        }
    }

    private function connect(string $database): PDO
    {
        $this->assertOwned();

        return new PDO('pgsql:host=127.0.0.1;port='.$this->port.';dbname='.$database.';connect_timeout=2',
            $this->username, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function binaryDirectory(): string
    {
        foreach (['initdb.exe', 'postgres.exe', 'pg_ctl.exe', 'pg_dump.exe'] as $binary) {
            $resolved = realpath($this->binaryDirectory.'/'.$binary);

            if ($resolved === false || ! is_file($resolved) || dirname($resolved) !== $this->binaryDirectory) {
                throw new RuntimeException('The approved PostgreSQL binaries must remain inside their explicit directory.');
            }
        }

        return $this->binaryDirectory;
    }

    private function assertServerIdentity(PDO $pdo): void
    {
        $this->assertOwned();
        $data = $pdo->query('SHOW data_directory')->fetchColumn();
        $port = $pdo->query('SHOW port')->fetchColumn();
        $user = $pdo->query('SELECT current_user')->fetchColumn();
        $pidFile = $this->path.'/data/postmaster.pid';
        $pid = is_file($pidFile) ? file($pidFile, FILE_IGNORE_NEW_LINES) : false;

        if (! is_string($data) || realpath($data) !== realpath($this->path.'/data') || (int) $port !== $this->port
            || $user !== $this->username || $pid === false || ! $this->ownsPostmaster((int) ($pid[0] ?? 0))
            || realpath($pid[1] ?? '') !== realpath($this->path.'/data') || (int) ($pid[3] ?? 0) !== $this->port) {
            throw new RuntimeException('PostgreSQL process, data directory or endpoint identity mismatch.');
        }
    }

    private function ownsPostmaster(int $pid): bool
    {
        $parent = $this->server?->getPid();
        if ($pid <= 0 || $parent === null) {
            return false;
        }

        $shell = getenv('SYSTEMROOT').'/System32/WindowsPowerShell/v1.0/powershell.exe';
        $query = $this->process([$shell, '-NoProfile', '-NonInteractive', '-Command',
            '$p=Get-CimInstance Win32_Process -Filter "ProcessId='.$pid.'"; if($null -eq $p){exit 1}; $p | Select-Object ProcessId,ParentProcessId,ExecutablePath | ConvertTo-Json -Compress']);
        $query->setTimeout(10);
        $query->run();
        $identity = json_decode($query->getOutput(), true);

        return $query->isSuccessful() && is_array($identity)
            && $this->server->getPid() === $parent
            && (int) ($identity['ProcessId'] ?? 0) === $pid
            && ($pid === $parent || (int) ($identity['ParentProcessId'] ?? 0) === $parent)
            && is_string($identity['ExecutablePath'] ?? null)
            && realpath($identity['ExecutablePath']) === realpath($this->binaryDirectory().'/postgres.exe');
    }

    private function assertOwned(): void
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $this->token)
            || basename($this->path) !== 'start-kit-bkp-pg-'.$this->token
            || realpath(dirname($this->path)) !== realpath(sys_get_temp_dir())
            || is_link($this->path) || is_link($this->path.'/data')
            || ! is_file($this->path.'/owner') || file_get_contents($this->path.'/owner') !== $this->token
            || $this->port < 1024 || in_array($this->port, [5432, 3306, 33060], true)
            || ! preg_match('/^[a-f0-9]{64}$/', $this->password)) {
            throw new RuntimeException('Refusing an unowned PostgreSQL fixture.');
        }
    }

    /** @param list<string> $arguments */
    private function process(array $arguments): Process
    {
        $process = new Process($arguments, $this->path, [
            'PGDATA' => false, 'PGHOST' => false, 'PGPORT' => false, 'PGUSER' => false, 'PGDATABASE' => false,
            'PGPASSWORD' => false, 'PGPASSFILE' => false, 'PGSERVICE' => false, 'PGSERVICEFILE' => false, 'PGOPTIONS' => false,
        ]);
        $process->setOptions(['create_new_console' => false, 'create_process_group' => true]);

        return $process;
    }

    private function availablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new RuntimeException('Cannot reserve an owned PostgreSQL loopback port.');
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr(strrchr((string) $address, ':'), 1);

        return in_array($port, [5432, 3306, 33060], true) ? $this->availablePort() : $port;
    }
}
