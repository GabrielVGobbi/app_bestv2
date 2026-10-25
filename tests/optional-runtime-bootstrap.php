<?php

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Tests\AuthorityHttpEnvironment;
use Tests\TestEnvironment;

function optionalRuntimeAssertCopy(): void
{
    $root = realpath(dirname(__DIR__));
    $marker = $root.'/runtime/optional-runtime-owner.json';
    if (! is_file($marker) || is_link($marker) || (lstat($marker)['nlink'] ?? 0) !== 1 || filesize($marker) > 4096) {
        throw new RuntimeException('Optional runtime builds require a marked disposable source copy.');
    }
    $owner = json_decode(file_get_contents($marker), true, 8, JSON_THROW_ON_ERROR);
    $token = getenv('OPS004_SOURCE_TOKEN');
    if (! is_array($owner) || ! is_string($token) || ! preg_match('/\A[a-f0-9]{32}\z/', $token)
        || ($owner['token'] ?? null) !== $token || ($owner['root'] ?? null) !== $root
        || ! isset($owner['original_root']) || realpath($owner['original_root']) === $root
        || basename($root) !== 'source' || ! preg_match('/\Aops004-[a-f0-9]{8}\z/', basename(dirname($root)))
        || file_exists($root.'/.env') || file_exists($root.'/.config') || file_exists($root.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Refusing an unowned, private or original source tree.');
    }
}

/** @return array<string, mixed> */
function optionalRuntimeState(string $manifestPath, string $statePath): array
{
    optionalRuntimeAssertCopy();
    if (getenv('OPS004_RUNTIME_TESTS') !== '1') {
        throw new RuntimeException('Optional runtimes require their explicit isolated opt-in.');
    }
    $manifest = AuthorityHttpEnvironment::readManifest($manifestPath);
    if ($manifest['mode'] !== 'authority' || realpath($statePath) !== realpath($manifest['settings']['path'].'/optional-runtime.json')
        || is_link($statePath) || filesize($statePath) > 8192 || (lstat($statePath)['nlink'] ?? 0) !== 1) {
        throw new RuntimeException('Optional runtime state must belong to the verified disposable database.');
    }
    $state = json_decode(file_get_contents($statePath), true, 16, JSON_THROW_ON_ERROR);
    if (! is_array($state) || ($state['token'] ?? null) !== $manifest['settings']['token']
        || ! is_bool($state['ssr'] ?? null) || ! is_bool($state['realtime'] ?? null)
        || ! is_array($state['actors'] ?? null) || count($state['actors']) !== 2) {
        throw new RuntimeException('Invalid optional runtime identity or modes.');
    }
    foreach (['ssr_port', 'reverb_port'] as $key) {
        if (! is_int($state[$key] ?? null) || $state[$key] < 1024 || $state[$key] > 65535
            || in_array($state[$key], [3306, 33060, 13714, $manifest['port'], $manifest['settings']['port']], true)) {
            throw new RuntimeException('Optional runtime requires exclusive nonstandard loopback ports.');
        }
    }
    if ($state['ssr_port'] === $state['reverb_port']) {
        throw new RuntimeException('Optional runtime ports must be distinct.');
    }
    foreach ($state['actors'] as $actor) {
        if (! is_array($actor) || ! is_int($actor['id'] ?? null) || $actor['id'] < 1) {
            throw new RuntimeException('Optional runtime actors must be positive fixture identities.');
        }
    }

    return ['manifest' => $manifest, 'state' => $state];
}

/** @return array{app: Application, environment: TestEnvironment, registry: string, manifest: array<string, mixed>, state: array<string, mixed>} */
function optionalRuntimeBoot(string $manifestPath, string $statePath): array
{
    $input = optionalRuntimeState($manifestPath, $statePath);
    $manifest = $input['manifest'];
    $state = $input['state'];
    $environment = new TestEnvironment;
    $registry = AuthorityHttpEnvironment::registerEnvironment($manifest, $environment);
    try {
        $app = AuthorityHttpEnvironment::bootApplication($manifest, $environment);
        $values = [
            'INERTIA_SSR_ENABLED' => $state['ssr'] ? 'true' : 'false',
            'INERTIA_SSR_URL' => 'http://127.0.0.1:'.$state['ssr_port'],
            'REVERB_APP_ID' => 'ops004-'.$manifest['settings']['token'],
            'REVERB_APP_KEY' => 'ops004-synthetic-key',
            'REVERB_APP_SECRET' => 'ops004-synthetic-secret',
            'REVERB_HOST' => '127.0.0.1', 'REVERB_PORT' => (string) $state['reverb_port'], 'REVERB_SCHEME' => 'http',
            'REVERB_SERVER_HOST' => '127.0.0.1', 'REVERB_SERVER_PORT' => (string) $state['reverb_port'],
        ];
        foreach ($values as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $inertia = require dirname(__DIR__).'/config/inertia.php';
        $broadcasting = require dirname(__DIR__).'/config/broadcasting.php';
        $reverb = require dirname(__DIR__).'/config/reverb.php';
        $reverb['apps']['apps'][0]['allowed_origins'] = [$manifest['host']];
        $broadcasting['connections']['reverb']['client_options'] = ['timeout' => 3, 'connect_timeout' => 3];
        $app['config']->set([
            'inertia.ssr' => [...$inertia['ssr'], 'bundle' => dirname(__DIR__).'/bootstrap/ssr/app.js', 'throw_on_error' => true],
            'broadcasting.default' => $state['realtime'] ? 'reverb' : 'null',
            'broadcasting.connections' => ['null' => ['driver' => 'null'], ...($state['realtime'] ? ['reverb' => $broadcasting['connections']['reverb']] : [])],
            'reverb' => $reverb,
        ]);
        $app->make(BroadcastManager::class)->purge();
        require dirname(__DIR__).'/routes/channels.php';
        if ($state['ssr']) {
            Http::allowStrayRequests(['http://127.0.0.1:'.$state['ssr_port'].'/render']);
        }

        return compact('app', 'environment', 'registry', 'manifest', 'state');
    } catch (Throwable $exception) {
        $environment->cleanUp();
        if (is_file($registry)) {
            unlink($registry);
        }
        throw $exception;
    }
}
