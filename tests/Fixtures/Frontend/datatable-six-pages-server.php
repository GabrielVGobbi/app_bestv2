<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Process\Process;
use Tests\AuthorityHttpEnvironment;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$mysql = null;
$environment = null;
$app = null;
$server = null;
$manifest = null;
$stage = 'guard';

try {
    $ownerPath = $root.'/runtime/optional-runtime-owner.json';
    if (getenv('DATATABLE_SIX_BROWSER') !== '1' || basename($root) !== 'source'
        || preg_match('/\Aops004-[a-f0-9]{8}\z/', basename(dirname($root))) !== 1
        || ! is_file($ownerPath) || is_link($ownerPath) || file_exists($root.'/.env') || file_exists($root.'/.config')) {
        throw new RuntimeException('Six-page browser requires the marked disposable source copy.');
    }
    $owner = json_decode(file_get_contents($ownerPath), true, 8, JSON_THROW_ON_ERROR);
    if (($owner['token'] ?? null) !== getenv('OPS004_SOURCE_TOKEN') || realpath($owner['original_root'] ?? '') === realpath($root)) {
        throw new RuntimeException('Disposable source ownership mismatch.');
    }
    AuthorityHttpEnvironment::configureGlobals(true);
    $host = AuthorityHttpEnvironment::requiredHost();
    $mysql = new DisposableMysql;
    $stage = 'mysql';
    $mysql->start();
    foreach (['http-sessions', 'http-environments', 'http-public'] as $directory) {
        (new Filesystem)->ensureDirectoryExists($mysql->path.'/'.$directory);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
    if ($socket === false) {
        throw new RuntimeException('Cannot allocate the owned HTTP port.');
    }
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $manifest = ['settings' => $mysql->settings(), 'host' => $host, 'port' => $port, 'mode' => 'authority'];
    $manifestPath = $mysql->path.'/http.json';
    file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
    AuthorityHttpEnvironment::readManifest($manifestPath);
    $environment = new TestEnvironment;
    $stage = 'boot';
    $app = AuthorityHttpEnvironment::bootApplication($manifest, $environment);
    $app['config']->set('inertia.ssr.enabled', false);
    $app['config']->set('broadcasting.default', 'null');
    $stage = 'migrate';
    if ($app->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('Disposable migrations failed.');
    }
    $stage = 'seed';
    $roles = [];
    foreach (['developer', 'super-admin', 'admin', 'user'] as $slug) {
        $roles[$slug] = Role::query()->create(['name' => $slug, 'slug' => $slug]);
    }
    $actor = User::factory()->create(['name' => 'QA table developer', 'email' => 'qa-table@example.test', 'password' => 'BrowserFixture-123!', 'email_verified_at' => now(), 'must_change_password' => false, 'remember_token' => null]);
    $actor->roles()->sync([$roles['developer']->id, $roles['admin']->id]);
    $permissionIds = [];
    foreach (['view-users', 'view-clients', 'view-activity-logs'] as $slug) {
        $permissionIds[] = Permission::query()->create(['name' => $slug, 'slug' => $slug, 'group' => 'qa-tables'])->id;
    }
    $actor->permissions()->sync($permissionIds);
    for ($index = 1; $index <= 35; $index++) {
        $user = User::factory()->create(['name' => sprintf('QA Pessoa %02d', $index), 'email' => sprintf('qa-person-%02d@example.test', $index), 'email_verified_at' => now(), 'must_change_password' => false]);
        $user->roles()->sync([$roles['user']->id]);
        Client::factory()->create(['name' => sprintf('QA Cliente %02d', $index), 'email' => sprintf('qa-client-%02d@example.test', $index)]);
    }
    for ($index = 1; $index <= 12; $index++) {
        Role::query()->create(['name' => sprintf('QA Papel %02d', $index), 'slug' => sprintf('qa-role-%02d', $index), 'description' => 'Descrição QA longa para leitura de texto completo e coluna da tabela.']);
        Permission::query()->create(['name' => sprintf('QA Permissão %02d', $index), 'slug' => sprintf('qa-permission-%02d', $index), 'group' => 'qa-tables', 'description' => 'Permissão sintética para testar listagem.']);
    }
    Activity::query()->delete();
    $client = Client::query()->firstOrFail();
    for ($index = 1; $index <= 45; $index++) {
        Activity::query()->create(['log_name' => 'clients', 'event' => 'updated', 'description' => sprintf('QA table event %02d', $index), 'subject_type' => Client::class, 'subject_id' => $client->id, 'causer_type' => User::class, 'causer_id' => $actor->id, 'properties' => ['attributes' => ['name' => $client->name]]]);
    }
    $pdo = DisposableMysql::verifiedConnection($manifest['settings']);
    $counts = static function () use ($pdo): array {
        $result = [];
        foreach (['users', 'clients', 'roles', 'permissions', 'users_roles', 'users_permissions'] as $table) {
            $result[$table] = (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        }

        return $result;
    };
    $baseline = $counts();
    $app->make('db')->disconnect();
    $app->flush();
    $app = null;
    $environment->cleanUp();
    $environment = null;
    $stage = 'http';
    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $mysql->path.'/http-public', __DIR__.'/datatable-six-pages-router.php'], $root, [
        'AUTHORITY_HTTP_MANIFEST' => $manifestPath, 'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'DATATABLE_SIX_BROWSER' => '1', 'APP_RUNNING_IN_CONSOLE' => 'false',
    ]);
    $server->setTimeout(null);
    $server->disableOutput();
    $server->start();
    $deadline = microtime(true) + 20;
    do {
        $context = stream_context_create(['http' => ['timeout' => 1, 'header' => 'Host: '.$host.':'.$port."\r\n"]]);
        $ready = @file_get_contents('http://127.0.0.1:'.$port.'/__datatable_health', false, $context) === 'datatable-ready';
        if (! $ready) {
            usleep(100000);
        }
    } while (! $ready && $server->isRunning() && microtime(true) < $deadline);
    if (! $ready) {
        throw new RuntimeException('Owned HTTP readiness failed.');
    }
    echo json_encode(['url' => 'http://'.$host.':'.$port, 'actor' => ['id' => $actor->id, 'email' => $actor->email, 'password' => 'BrowserFixture-123!']], JSON_THROW_ON_ERROR)."\n";
    fflush(STDOUT);
    stream_set_blocking(STDIN, false);
    $deadline = microtime(true) + 600;
    while ($server->isRunning() && microtime(true) < $deadline && ! feof(STDIN)) {
        $line = fgets(STDIN);
        if ($line !== false && trim($line) === 'stop') {
            break;
        }
        usleep(100000);
    }
    if ($counts() !== $baseline) {
        throw new RuntimeException('Browser altered a business-record count.');
    }
    echo json_encode(['business_counts_unchanged' => true], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['stage' => $stage, 'class' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
    $failed = true;
} finally {
    if ($server?->isRunning()) {
        $server->stop(3);
    }
    $app?->make('db')->disconnect();
    $app?->flush();
    $environment?->cleanUp();
    if ($manifest !== null) {
        AuthorityHttpEnvironment::cleanUpEnvironments($manifest);
    }
    $mysql?->cleanUp();
}
exit(isset($failed) ? 1 : 0);
