<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\AuthorityHttpEnvironment;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/optional-runtime-bootstrap.php';

$runtime = null;
try {
    AuthorityHttpEnvironment::configureGlobals(false);
    $manifestPath = getenv('AUTHORITY_HTTP_MANIFEST');
    $statePath = getenv('OPS004_RUNTIME_STATE');
    if (! is_string($manifestPath) || ! is_string($statePath)) {
        throw new RuntimeException('Owned manifests required.');
    }
    $input = optionalRuntimeState($manifestPath, $statePath);
    $manifest = $input['manifest'];
    $state = $input['state'];
    if (($_SERVER['HTTP_HOST'] ?? '') !== $manifest['host'].':'.$manifest['port']
        || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
        http_response_code(404);

        return;
    }
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    $asset = AuthorityHttpEnvironment::assetPath($path, dirname(__DIR__).'/public');
    if ($method === 'GET' && $asset !== null) {
        header('Content-Type: '.match (pathinfo($asset, PATHINFO_EXTENSION)) {
            'js' => 'application/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml', default => 'application/octet-stream',
        });
        readfile($asset);

        return;
    }
    $allowed = ($method === 'GET' && in_array($path, ['/', '/login', '/dashboard', '/notifications', '/api/v1/notifications', '/api/v1/notifications/unread', '/api/v1/notifications/unread-count'], true))
        || ($method === 'POST' && in_array($path, ['/login', '/logout', '/broadcasting/auth'], true));
    if (! $allowed || $_FILES !== [] || isset($_SERVER['HTTP_TRANSFER_ENCODING'])
        || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
        http_response_code(404);

        return;
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'".($state['realtime'] ? ' ws://127.0.0.1:'.$state['reverb_port'] : '')."; object-src 'none'; frame-ancestors 'none'");
    $runtime = optionalRuntimeBoot($manifestPath, $statePath);
    $app = $runtime['app'];
    $app->make(ExceptionHandler::class)->reportable(function (Throwable $exception) use ($manifest): void {
        file_put_contents($manifest['settings']['path'].'/optional-exceptions.jsonl', json_encode([
            'class' => $exception::class, 'message' => $exception->getMessage(),
        ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    });
    $kernel = $app->make(Kernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    file_put_contents($manifest['settings']['path'].'/optional-http-audit.jsonl', json_encode([
        'path' => $path, 'method' => $method, 'status' => $response->getStatusCode(),
        'ssr' => $app['config']->get('inertia.ssr.enabled'), 'realtime' => $state['realtime'],
        'http_guard' => Http::preventingStrayRequests(), 'mail_fake' => Mail::isFake(), 'queue_fake' => Queue::isFake(),
    ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable $exception) {
    if (isset($input, $manifest)) {
        file_put_contents($manifest['settings']['path'].'/optional-exceptions.jsonl', json_encode([
            'class' => $exception::class, 'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
    }
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Isolated optional runtime request failed.';
    error_log('Optional runtime failure: '.$exception::class.' '.$exception->getMessage());
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
