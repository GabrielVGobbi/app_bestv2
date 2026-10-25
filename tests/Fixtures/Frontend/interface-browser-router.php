<?php

declare(strict_types=1);

use App\Jobs\ProcessExport;
use App\Modules\Clients\Models\Client;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\AuthorityHttpEnvironment;
use Tests\DisposableMysql;
use Tests\TestEnvironment;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$environment = null;
$app = null;
$registry = null;
try {
    AuthorityHttpEnvironment::configureGlobals(false);
    if (getenv('INTERFACE_BROWSER_TESTS') !== '1') {
        throw new RuntimeException('Interface browser opt-in missing.');
    }
    $manifest = AuthorityHttpEnvironment::readManifest(getenv('AUTHORITY_HTTP_MANIFEST'));
    if (($_SERVER['HTTP_HOST'] ?? '') !== $manifest['host'].':'.$manifest['port'] || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
        http_response_code(404);

        return;
    }
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'");
    if ($method === 'GET' && $path === '/__interface_health' && $_GET === []) {
        echo 'interface-ready';

        return;
    }
    $asset = AuthorityHttpEnvironment::assetPath($path, $root.'/public');
    if ($method === 'GET' && $asset !== null && $_GET === []) {
        header('Content-Type: '.match (pathinfo($asset, PATHINFO_EXTENSION)) {
            'js' => 'text/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'woff2' => 'font/woff2', default => 'application/octet-stream',
        });
        readfile($asset);

        return;
    }
    if ($method === 'GET' && $_GET === [] && preg_match('~\A/src/(?:imagens/logo-icon-(?:white|dark)\.svg|assets/imagens/molecule/molecule[1-6]_[a-z_]+\.webp)\z~', $path) === 1 && is_file($root.'/public'.$path)) {
        header('Content-Type: '.(str_ends_with($path, '.svg') ? 'image/svg+xml' : 'image/webp'));
        readfile($root.'/public'.$path);

        return;
    }
    $pages = ['/', '/login', '/dashboard', '/users', '/clients', '/roles', '/permissions', '/activity-log', '/admin/dev/components/inline-cell-edit'];
    $apis = ['/api/v1/users', '/api/v1/clients', '/api/v1/roles', '/api/v1/permissions', '/api/v1/activity-log', '/api/v1/notifications', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count', '/api/v1/exports'];
    $allowed = $method === 'GET' && (($_GET === [] && in_array($path, $pages, true)) || (in_array($path, $apis, true) && array_diff(array_keys($_GET), ['page', 'per_page', 'search', 'sort', 'direction', 'filter']) === []));
    $fixture = json_decode(file_get_contents($manifest['settings']['path'].'/interface-fixture.json'), true, 8, JSON_THROW_ON_ERROR);
    if (($fixture['token'] ?? null) !== $manifest['settings']['token']) {
        throw new RuntimeException('Interface fixture ownership mismatch.');
    }
    $allowed = $allowed || ($method === 'GET' && (($_GET === ['parentable_type' => 'client'] && $path === '/api/v1/uploads/tags')
        || ($path === '/api/v1/uploads' && ($_GET['parentable_type'] ?? null) === 'client'
            && in_array($_GET['parentable_id'] ?? null, ['1', $fixture['client_uuid']], true)
            && ($_GET['page'] ?? null) === '1' && ($_GET['per_page'] ?? null) === '50' && count($_GET) === 4)));
    $allowed = $allowed || ($method === 'GET' && $_GET === [] && preg_match('~\A/(?:users/[a-f0-9-]{36}(?:/(?:edit|roles|permissions))?|clients/[a-f0-9-]{36}(?:/edit)?|roles/[0-9]+(?:/(?:edit|permissions))?|permissions/[0-9]+(?:/edit)?|users/create|clients/create|admin/dev(?:/components(?:/[a-z-]+)?)?|settings/(?:profile|security|appearance)|notifications|exportacoes|email-logs|external-api/diagnostics)\z~', $path) === 1);
    $allowed = $allowed || ($method === 'PUT' && $_GET === [] && $path === '/api/v1/roles/'.$fixture['role_id']);
    $allowed = $allowed || ($method === 'POST' && $_GET === [] && in_array($path, ['/login', '/logout', '/users', '/clients', '/api/v1/roles', '/api/v1/permissions', '/api/v1/exports', '/api/v1/notifications/mark-all-read', '/two-factor-challenge'], true));
    $allowed = $allowed || ($method === 'GET' && $_GET === [] && in_array($path, ['/two-factor-challenge', '/forgot-password', '/settings/appearance'], true));
    $allowed = $allowed || (in_array($method, ['GET', 'POST'], true) && $_GET === [] && $path === '/user/confirm-password');
    $allowed = $allowed || ($method === 'GET' && $_GET === [] && in_array($path, ['/admin/dev/seeder-generator', '/admin/dev/seeder-generator/tables', '/admin/dev/crud-generator'], true));
    $allowed = $allowed || ($method === 'POST' && $_GET === [] && $path === '/admin/dev/seeder-generator/preview');
    $allowed = $allowed || ($method === 'PUT' && $_GET === [] && in_array($path, ['/users/'.$fixture['user_uuid'], '/clients/'.$fixture['client_uuid'], '/api/v1/permissions/'.$fixture['permission_id']], true));
    $allowed = $allowed || ($method === 'PATCH' && $_GET === [] && ($path === '/settings/profile' || preg_match('~\A/api/v1/notifications/[a-f0-9-]{36}\z~', $path) === 1));
    $allowed = $allowed || ($method === 'GET' && (($path === '/api/v1/exports/columns' && array_keys($_GET) === ['model_class'] && $_GET['model_class'] === Client::class)
        || ($_GET === [] && preg_match('~\A/(?:api/v1/exports/[a-f0-9-]{36}|exports/[a-f0-9-]{36}/download)\z~', $path) === 1)));
    if (in_array($method, ['PUT', 'DELETE'], true) && $_GET === []) {
        $pdo = DisposableMysql::verifiedConnection($manifest['settings']);
        $permission = $pdo->query("SELECT id FROM permissions WHERE slug = 'access-painel'")->fetchColumn();
        $allowed = $allowed || ($permission !== false && $path === '/api/v1/permissions/'.$permission);
    }
    if ($method === 'DELETE' && $_GET === []) {
        $pdo = DisposableMysql::verifiedConnection($manifest['settings']);
        $client = $pdo->query("SELECT uuid FROM clients WHERE email = 'qa-interface-client@example.test'")->fetchColumn();
        $role = $pdo->query("SELECT id FROM roles WHERE slug = 'qa-interface-role'")->fetchColumn();
        $allowed = $allowed || ($client !== false && $path === '/clients/'.$client) || ($role !== false && $path === '/api/v1/roles/'.$role);
    }
    if (! $allowed || $_FILES !== [] || isset($_SERVER['HTTP_TRANSFER_ENCODING']) || isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
        http_response_code(404);

        return;
    }
    $environment = new TestEnvironment;
    $registry = AuthorityHttpEnvironment::registerEnvironment($manifest, $environment);
    $app = AuthorityHttpEnvironment::bootApplication($manifest, $environment);
    $app['config']->set('inertia.ssr.enabled', false);
    $app['config']->set('broadcasting.default', 'null');
    $app['config']->set('filesystems.disks.local.root', $manifest['settings']['path'].'/interface-files');
    $app->make('filesystem')->forgetDisk('local');
    // Only the real export job runs synchronously; all external delivery stays fake.
    Queue::fake()->except([ProcessExport::class]);
    $kernel = $app->make(Kernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Isolated interface request failed.';
} finally {
    $app?->make('db')->disconnect();
    $app?->flush();
    $environment?->cleanUp();
    if ($registry !== null && is_file($registry)) {
        unlink($registry);
    }
}
