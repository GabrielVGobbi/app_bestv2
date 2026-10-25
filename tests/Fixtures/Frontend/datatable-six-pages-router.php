<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\AuthorityHttpEnvironment;
use Tests\TestEnvironment;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$environment = null;
$app = null;
$registry = null;
try {
    AuthorityHttpEnvironment::configureGlobals(false);
    if (getenv('DATATABLE_SIX_BROWSER') !== '1') {
        throw new RuntimeException('Six-page browser opt-in missing.');
    }
    $manifest = AuthorityHttpEnvironment::readManifest(getenv('AUTHORITY_HTTP_MANIFEST'));
    if (($_SERVER['HTTP_HOST'] ?? '') !== $manifest['host'].':'.$manifest['port'] || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
        http_response_code(404);

        return;
    }
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'");
    if ($method === 'GET' && $path === '/__datatable_health' && $_GET === []) {
        echo 'datatable-ready';

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
    $apis = ['/api/v1/users', '/api/v1/clients', '/api/v1/roles', '/api/v1/permissions', '/api/v1/activity-log', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'];
    $allowed = $method === 'GET' && (($_GET === [] && in_array($path, $pages, true)) || (in_array($path, $apis, true) && array_diff(array_keys($_GET), ['page', 'per_page', 'search', 'sort', 'direction', 'filter']) === []));
    $allowed = $allowed || ($method === 'POST' && $_GET === [] && in_array($path, ['/login', '/logout'], true));
    if (! $allowed || $_FILES !== [] || isset($_SERVER['HTTP_TRANSFER_ENCODING']) || isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
        http_response_code(404);

        return;
    }
    $environment = new TestEnvironment;
    $registry = AuthorityHttpEnvironment::registerEnvironment($manifest, $environment);
    $app = AuthorityHttpEnvironment::bootApplication($manifest, $environment);
    $app['config']->set('inertia.ssr.enabled', false);
    $app['config']->set('broadcasting.default', 'null');
    $kernel = $app->make(Kernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Isolated six-page request failed.';
} finally {
    $app?->make('db')->disconnect();
    $app?->flush();
    $environment?->cleanUp();
    if ($registry !== null && is_file($registry)) {
        unlink($registry);
    }
}
