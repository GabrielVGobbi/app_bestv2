<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\AuthorityHttpEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/closure-browser-bootstrap.php';

$runtime = null;
try {
    AuthorityHttpEnvironment::configureGlobals(false);
    $manifestPath = getenv('AUTHORITY_HTTP_MANIFEST');
    $fixturePath = getenv('ARC002_BROWSER_FIXTURE');
    if (! is_string($manifestPath) || ! is_string($fixturePath)) {
        throw new RuntimeException('Closure browser requires its owned manifests.');
    }
    $input = closureBrowserInput($manifestPath, $fixturePath);
    $manifest = $input['manifest'];
    $fixture = $input['fixture'];
    if (($_SERVER['HTTP_HOST'] ?? '') !== $manifest['host'].':'.$manifest['port']
        || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
        http_response_code(404);

        return;
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'");
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    $asset = AuthorityHttpEnvironment::assetPath($path, dirname(__DIR__).'/public');
    if ($method === 'GET' && $asset !== null && $_GET === []) {
        header('Content-Type: '.match (pathinfo($asset, PATHINFO_EXTENSION)) {
            'js' => 'application/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml', default => 'application/octet-stream',
        });
        readfile($asset);

        return;
    }
    $pages = ['/login', '/dashboard', '/roles', '/permissions', '/users', '/clients',
        '/roles/'.$fixture['role']['id'], '/roles/'.$fixture['role']['id'].'/edit',
        '/permissions/'.$fixture['permission']['id'], '/permissions/'.$fixture['permission']['id'].'/edit',
        '/users/'.$fixture['target_user']['uuid'], '/clients/'.$fixture['client']['uuid']];
    $reads = ['/api/v1/roles', '/api/v1/permissions', '/api/v1/users', '/api/v1/clients',
        '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'];
    $allowed = $method === 'GET' && (($_GET === [] && in_array($path, $pages, true))
        || (in_array($path, $reads, true) && array_diff(array_keys($_GET), ['page', 'per_page', 'search', 'sort', 'direction']) === []));
    $allowed = $allowed || ($method === 'POST' && $_GET === [] && in_array($path, ['/login', '/logout'], true));
    if ($method === 'PUT' && $_GET === [] && in_array($path, ['/api/v1/roles/'.$fixture['role']['id'], '/api/v1/permissions/'.$fixture['permission']['id']], true)) {
        $body = json_decode(file_get_contents('php://input'), true);
        $role = str_starts_with($path, '/api/v1/roles/');
        $allowedKeys = $role ? ['name', 'slug', 'description'] : ['name', 'slug', 'group', 'description'];
        $allowed = is_array($body) && array_diff(array_keys($body), $allowedKeys) === []
            && in_array($body['name'] ?? null, ['', $role ? 'ARC Role Updated' : 'ARC Permission Updated'], true)
            && ($body['slug'] ?? null) === $fixture[$role ? 'role' : 'permission']['slug'];
    }
    if ($method === 'DELETE' && $_GET === []) {
        $allowed = in_array($path, ['/users/'.$fixture['target_user']['uuid'], '/clients/'.$fixture['client']['uuid']], true)
            && ($_SERVER['HTTP_X_INERTIA'] ?? '') === 'true';
    }
    if (! $allowed || $_FILES !== [] || isset($_SERVER['HTTP_TRANSFER_ENCODING']) || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
        http_response_code(404);

        return;
    }
    $runtime = closureBrowserBoot($manifest);
    $app = $runtime['app'];
    $app->make(ExceptionHandler::class)->reportable(function (Throwable $exception) use ($manifest): void {
        file_put_contents($manifest['settings']['path'].'/closure-exceptions.jsonl', json_encode([
            'class' => $exception::class, 'message' => $exception->getMessage(),
        ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    });
    $kernel = $app->make(Kernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    file_put_contents($manifest['settings']['path'].'/closure-http-audit.jsonl', json_encode([
        'method' => $method, 'path' => $path, 'status' => $response->getStatusCode(),
        'inertia' => $request->header('X-Inertia'), 'content_type' => $response->headers->get('Content-Type'),
        'http_guard' => Http::preventingStrayRequests(), 'mail_fake' => Mail::isFake(), 'queue_fake' => Queue::isFake(),
        'ssr' => $app['config']->get('inertia.ssr.enabled'), 'broadcasting' => $app['config']->get('broadcasting.default'),
    ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable $exception) {
    if (isset($manifest)) {
        file_put_contents($manifest['settings']['path'].'/closure-exceptions.jsonl', json_encode([
            'class' => $exception::class, 'message' => $exception->getMessage(),
        ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    }
    http_response_code(500);
    echo 'Isolated closure browser request failed.';
} finally {
    if ($runtime !== null) {
        $runtime['app']->make('db')->disconnect();
        $runtime['app']->flush();
        $runtime['environment']->cleanUp();
        if (is_file($runtime['registry'])) {
            unlink($runtime['registry']);
        }
    }
}
