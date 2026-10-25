<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use PDO;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

final class DisposableMysql
{
    public readonly string $path;

    private ?Process $server = null;

    private string $rootPassword;

    /** @var array{path: string, token: string, port: int, schema: string, username: string, password: string} */
    private array $settings;

    public function __construct()
    {
        if (getenv('AUTHORITY_MYSQL_TESTS') !== '1') {
            throw new RuntimeException('Disposable MySQL requires explicit AUTHORITY_MYSQL_TESTS=1.');
        }

        $token = bin2hex(random_bytes(16));
        $this->path = sys_get_temp_dir().'/start-kit-mysql-'.$token;
        $this->rootPassword = bin2hex(random_bytes(32));
        $this->settings = [
            'path' => $this->path,
            'token' => $token,
            'port' => self::availablePort(),
            'schema' => 'authority'.$token,
            'username' => 'authority_'.substr($token, 0, 16),
            'password' => bin2hex(random_bytes(32)),
        ];

        foreach (['data', 'tmp', 'barrier'] as $directory) {
            (new Filesystem)->ensureDirectoryExists($this->path.'/'.$directory);
        }

        file_put_contents($this->path.'/owner', $token);
    }

    public function start(): void
    {
        self::assertOwnedPath($this->settings);
        $binary = getenv('AUTHORITY_MYSQLD_BINARY') ?: (new ExecutableFinder)->find('mysqld');

        if (! is_string($binary) || ! is_file($binary)) {
            throw new RuntimeException('Set AUTHORITY_MYSQLD_BINARY to an installed mysqld executable.');
        }

        $arguments = [
            $binary, '--no-defaults', '--basedir='.str_replace('\\', '/', dirname($binary, 2)),
            '--datadir='.$this->path.'/data', '--tmpdir='.$this->path.'/tmp',
            '--log-error='.$this->path.'/mysql.log', '--pid-file='.$this->path.'/mysql.pid',
            '--mysqlx=OFF', '--named-pipe=OFF', '--shared-memory=OFF',
            '--persisted-globals-load=OFF', '--local-infile=OFF', '--secure-file-priv=NULL',
            '--skip-log-bin', '--innodb-buffer-pool-size=32M', '--performance-schema=ON',
        ];

        $initialize = new Process([...$arguments, '--initialize'], $this->path);
        $initialize->setTimeout(120);
        $initialize->run();

        if (! $initialize->isSuccessful()) {
            throw new RuntimeException('Disposable MySQL initialization failed; server output is withheld because it may contain credentials.');
        }

        $schema = $this->settings['schema'];
        $username = $this->settings['username'];
        $password = $this->settings['password'];
        $token = $this->settings['token'];
        $sql = "ALTER USER 'root'@'localhost' IDENTIFIED BY '{$this->rootPassword}';\n"
            ."CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
            ."CREATE USER '{$username}'@'127.0.0.1' IDENTIFIED BY '{$password}';\n"
            ."GRANT ALL PRIVILEGES ON `{$schema}`.* TO '{$username}'@'127.0.0.1';\n"
            ."CREATE TABLE `{$schema}`.isolation_owner (token CHAR(32) PRIMARY KEY) ENGINE=InnoDB;\n"
            ."INSERT INTO `{$schema}`.isolation_owner VALUES ('{$token}');\n";
        file_put_contents($this->path.'/initialize.sql', $sql);

        $this->server = new Process([
            ...$arguments, '--bind-address=127.0.0.1', '--port='.$this->settings['port'],
            '--init-file='.$this->path.'/initialize.sql',
        ], $this->path);
        $this->server->setTimeout(null);
        $this->server->start();
        $deadline = microtime(true) + 45;

        do {
            if (! $this->server->isRunning()) {
                throw new RuntimeException('Disposable MySQL exited before readiness; credential-bearing logs are withheld.');
            }

            try {
                self::verifiedConnection($this->settings);
                unlink($this->path.'/initialize.sql');

                return;
            } catch (\PDOException) {
                usleep(100000);
            }
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Disposable MySQL readiness timed out.');
    }

    /** @return array{path: string, token: string, port: int, schema: string, username: string, password: string} */
    public function settings(): array
    {
        return $this->settings;
    }

    /** @param array{path: string, token: string, port: int, schema: string, username: string, password: string} $settings */
    public static function verifiedConnection(array $settings): PDO
    {
        self::assertOwnedPath($settings);

        if ($settings['port'] < 1024 || in_array($settings['port'], [3306, 33060], true)
            || $settings['schema'] !== 'authority'.$settings['token']
            || $settings['username'] !== 'authority_'.substr($settings['token'], 0, 16)
            || ! preg_match('/^[a-f0-9]{64}$/', $settings['password'])) {
            throw new RuntimeException('Refusing an unowned MySQL connection.');
        }

        $pdo = self::connect($settings, $settings['username'], $settings['password']);
        self::assertServerIdentity($pdo, $settings);
        $owner = $pdo->query('SELECT token FROM isolation_owner')->fetchColumn();
        $user = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();

        if ($owner !== $settings['token'] || $user !== $settings['username'].'@127.0.0.1') {
            throw new RuntimeException('Disposable MySQL account or ownership marker mismatch.');
        }

        return $pdo;
    }

    /** @param array{path: string, token: string, port: int, schema: string, username: string, password: string} $settings */
    public static function bootApplication(array $settings, TestEnvironment $environment): Application
    {
        TestEnvironment::assertSafeEnvironment();
        self::verifiedConnection($settings);
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $environment->prepare($app);
        $app->afterBootstrapping(LoadConfiguration::class, fn (Application $app) => $environment->configure($app));
        $app->afterBootstrapping(RegisterProviders::class, fn () => $environment->fakeIntegrations());
        $app->make(Kernel::class)->bootstrap();
        TestEnvironment::assertSafeDatabase($app->make('config'));
        $app['config']->set('database.connections.authority_sandbox', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $settings['port'],
            'database' => $settings['schema'], 'username' => $settings['username'],
            'password' => $settings['password'], 'unix_socket' => '', 'url' => null,
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
            'strict' => true, 'engine' => 'InnoDB',
        ]);
        $app['db']->setDefaultConnection('authority_sandbox');
        $app['config']->set('activitylog.database_connection', 'authority_sandbox');
        $app['config']->set('telescope.storage.database.connection', 'authority_sandbox');
        $app['config']->set('pulse.storage.database.connection', 'authority_sandbox');
        self::assertServerIdentity($app['db']->connection()->getPdo(), $settings);

        return $app;
    }

    /** @param list<int> $connectionIds */
    public function waitingConnections(array $connectionIds): int
    {
        $pdo = $this->rootConnection();
        $placeholders = implode(',', array_fill(0, count($connectionIds), '?'));
        $query = $pdo->prepare('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID = w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN ('.$placeholders.')');
        $query->execute($connectionIds);

        return (int) $query->fetchColumn();
    }

    public function cleanUp(): void
    {
        if ($this->server?->isRunning()) {
            try {
                $this->rootConnection()->exec('SHUTDOWN');
                $deadline = microtime(true) + 10;

                while ($this->server->isRunning() && microtime(true) < $deadline) {
                    usleep(100000);
                }
            } catch (Throwable) {
                // The process handle belongs only to the server created by this instance.
            } finally {
                if ($this->server->isRunning()) {
                    $this->server->stop(3);
                }
            }
        }

        if ($this->server?->isRunning()) {
            throw new RuntimeException('Refusing cleanup while the owned MySQL server is still running.');
        }

        self::assertOwnedPath($this->settings);

        foreach ([0, 1] as $worker) {
            $pathFile = $this->path.'/barrier/environment-'.$worker;

            if (! is_file($pathFile)) {
                continue;
            }

            $workerPath = file_get_contents($pathFile);

            if (! file_exists($workerPath)) {
                continue;
            }

            if (realpath(dirname($workerPath)) !== realpath(sys_get_temp_dir())
                || ! preg_match('/^start-kit-tests-[a-f0-9]{32}$/', basename($workerPath))
                || is_link($workerPath) || ! is_file($workerPath.'/authority-owner')
                || file_get_contents($workerPath.'/authority-owner') !== $this->settings['token']) {
                throw new RuntimeException('Refusing cleanup of an unowned worker environment.');
            }

            (new Filesystem)->deleteDirectory($workerPath);

            if (file_exists($workerPath)) {
                throw new RuntimeException('Worker environment was not fully removed.');
            }
        }

        (new Filesystem)->deleteDirectory($this->path);

        if (file_exists($this->path)) {
            throw new RuntimeException('Disposable MySQL directory was not fully removed.');
        }
    }

    private function rootConnection(): PDO
    {
        $pdo = self::connect($this->settings, 'root', $this->rootPassword);
        self::assertServerIdentity($pdo, $this->settings);

        return $pdo;
    }

    /** @param array{path: string, token: string, port: int, schema: string, username: string, password: string} $settings */
    private static function connect(array $settings, string $username, string $password): PDO
    {
        return new PDO('mysql:host=127.0.0.1;port='.$settings['port'].';dbname='.$settings['schema'].';charset=utf8mb4', $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
    }

    /** @param array{path: string, token: string, port: int, schema: string, username: string, password: string} $settings */
    private static function assertServerIdentity(PDO $pdo, array $settings): void
    {
        $identity = $pdo->query('SELECT @@datadir AS datadir, @@port AS port, @@pid_file AS pid_file, DATABASE() AS db')->fetch(PDO::FETCH_ASSOC);
        $normalize = fn (string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/'));

        if ($normalize($identity['datadir']) !== $normalize($settings['path'].'/data')
            || $normalize($identity['pid_file']) !== $normalize($settings['path'].'/mysql.pid')
            || (int) $identity['port'] !== $settings['port'] || $identity['db'] !== $settings['schema']
            || ! is_file($settings['path'].'/mysql.pid')) {
            throw new RuntimeException('Refusing MySQL server outside the owned data directory.');
        }
    }

    /** @param array{path: string, token: string, port: int, schema: string, username: string, password: string} $settings */
    private static function assertOwnedPath(array $settings): void
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $settings['token'])
            || basename($settings['path']) !== 'start-kit-mysql-'.$settings['token']
            || realpath(dirname($settings['path'])) !== realpath(sys_get_temp_dir())
            || is_link($settings['path']) || is_link($settings['path'].'/data')
            || ! is_file($settings['path'].'/owner')
            || file_get_contents($settings['path'].'/owner') !== $settings['token']) {
            throw new RuntimeException('Refusing an unowned MySQL sandbox path.');
        }
    }

    private static function availablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new RuntimeException('Cannot reserve a loopback port for disposable MySQL.');
        }

        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        if (in_array($port, [3306, 33060], true)) {
            return self::availablePort();
        }

        return $port;
    }
}
