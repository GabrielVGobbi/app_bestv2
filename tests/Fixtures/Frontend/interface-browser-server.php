<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Notifications\Models\AppNotification;
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
    if (getenv('INTERFACE_BROWSER_TESTS') !== '1') {
        throw new RuntimeException('Interface browser requires explicit opt-in.');
    }
    TestEnvironment::assertSafeEnvironment();
    AuthorityHttpEnvironment::configureGlobals(true);
    $host = AuthorityHttpEnvironment::requiredHost();
    $mysql = new DisposableMysql;
    $stage = 'mysql';
    $mysql->start();
    foreach (['http-sessions', 'http-environments', 'http-public', 'interface-files'] as $directory) {
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
    foreach (['view-users', 'create-users', 'update-users', 'delete-users', 'view-clients', 'create-clients', 'update-clients', 'delete-clients', 'view-activity-logs'] as $slug) {
        $permissionIds[] = Permission::query()->create(['name' => $slug, 'slug' => $slug, 'group' => 'qa-tables'])->id;
    }
    $actor->permissions()->sync($permissionIds);
    $mfa = User::factory()->create(['name' => 'QA MFA', 'email' => 'qa-mfa@example.test', 'password' => 'BrowserFixture-123!', 'email_verified_at' => now(), 'must_change_password' => false]);
    $mfa->forceFill([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcde12345-fghij67890', 'klmno12345-pqrst67890'], JSON_THROW_ON_ERROR)),
        'two_factor_confirmed_at' => now(),
    ])->save();
    AppNotification::create(['recipient_id' => $actor->id, 'type' => 'system', 'category' => 'general', 'title' => 'QA Interface Notification', 'body' => 'Notificação descartável para validar leitura.']);
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
    $authorityBefore = $pdo->query('SELECT * FROM users_permissions ORDER BY user_id, permission_id')->fetchAll(PDO::FETCH_ASSOC);
    $fixture = ['token' => $manifest['settings']['token'], 'role_id' => Role::query()->where('slug', 'qa-role-01')->sole()->id,
        'permission_id' => Permission::query()->where('slug', 'view-users')->sole()->id,
        'user_uuid' => User::query()->where('email', 'qa-person-01@example.test')->sole()->uuid, 'client_uuid' => $client->uuid];
    file_put_contents($mysql->path.'/interface-fixture.json', json_encode($fixture, JSON_THROW_ON_ERROR));
    $app->make('db')->disconnect();
    $app->flush();
    $app = null;
    $environment->cleanUp();
    $environment = null;
    $stage = 'http';
    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $mysql->path.'/http-public', __DIR__.'/interface-browser-router.php'], $root, [
        'AUTHORITY_HTTP_MANIFEST' => $manifestPath, 'AUTHORITY_HTTP_TESTS' => '1', 'AUTHORITY_MYSQL_TESTS' => '1', 'INTERFACE_BROWSER_TESTS' => '1', 'APP_RUNNING_IN_CONSOLE' => 'false',
    ]);
    $server->setTimeout(null);
    $server->disableOutput();
    $server->start();
    $deadline = microtime(true) + 20;
    do {
        $context = stream_context_create(['http' => ['timeout' => 1, 'header' => 'Host: '.$host.':'.$port."\r\n"]]);
        $ready = @file_get_contents('http://127.0.0.1:'.$port.'/__interface_health', false, $context) === 'interface-ready';
        if (! $ready) {
            usleep(100000);
        }
    } while (! $ready && $server->isRunning() && microtime(true) < $deadline);
    if (! $ready) {
        throw new RuntimeException('Owned HTTP readiness failed.');
    }
    echo json_encode(['url' => 'http://'.$host.':'.$port, 'actor' => ['id' => $actor->id, 'email' => $actor->email, 'password' => 'BrowserFixture-123!'], 'fixture' => $fixture], JSON_THROW_ON_ERROR)."\n";
    fflush(STDOUT);
    stream_set_blocking(STDIN, false);
    $deadline = microtime(true) + 1800;
    while ($server->isRunning() && microtime(true) < $deadline && ! feof(STDIN)) {
        $line = fgets(STDIN);
        if ($line !== false && trim($line) === 'stop') {
            break;
        }
        usleep(100000);
    }
    $after = $counts();
    $delta = $after['users'] - $baseline['users'];
    $roleDelta = $after['users_roles'] - $baseline['users_roles'];
    unset($after['users'], $baseline['users'], $after['users_roles'], $baseline['users_roles']);
    if ($after !== $baseline || ! in_array($delta, [0, 1], true) || $roleDelta !== $delta) {
        throw new RuntimeException('Unexpected synthetic resource changes.');
    }
    if ($authorityBefore !== $pdo->query('SELECT * FROM users_permissions ORDER BY user_id, permission_id')->fetchAll(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('The interface flow changed direct authority.');
    }
    $exports = $pdo->query('SELECT user_id, model_class, status, format, file_path FROM exports')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($exports as $export) {
        if ((int) $export['user_id'] !== $actor->id || $export['model_class'] !== Client::class || $export['status'] !== 'completed'
            || ! in_array($export['format'], ['csv', 'xlsx', 'tsv'], true) || str_contains($export['file_path'], '..')
            || ! is_file($mysql->path.'/interface-files/'.$export['file_path'])) {
            throw new RuntimeException('Export did not complete inside the disposable storage.');
        }
    }
    if ($delta === 1) {
        $created = $pdo->query("SELECT id, name, email_verified_at, must_change_password FROM users WHERE email = 'qa-interface-user@example.test'")->fetch(PDO::FETCH_ASSOC);
        if (! is_array($created) || $created['name'] !== 'QA Interface User' || $created['email_verified_at'] !== null || (int) $created['must_change_password'] !== 1) {
            throw new RuntimeException('Unexpected created-user identity or first-access state.');
        }
        $links = $pdo->prepare('SELECT roles.slug FROM users_roles JOIN roles ON roles.id = users_roles.role_id WHERE users_roles.user_id = ?');
        $links->execute([$created['id']]);
        if ($links->fetchAll(PDO::FETCH_COLUMN) !== ['user']) {
            throw new RuntimeException('The created user must receive only the existing default role.');
        }
    }
    echo json_encode(['synthetic_users_created' => $delta, 'automatic_default_roles' => $roleDelta, 'other_resource_counts_unchanged' => true, 'direct_authority_unchanged' => true, 'completed_private_exports' => count($exports)], JSON_THROW_ON_ERROR)."\n";
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
