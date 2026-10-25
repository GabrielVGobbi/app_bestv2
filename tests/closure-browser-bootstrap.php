<?php

use Illuminate\Foundation\Application;
use Tests\AuthorityHttpEnvironment;
use Tests\TestEnvironment;

require_once __DIR__.'/optional-runtime-bootstrap.php';

/** @return array<string, mixed> */
function closureBrowserInput(string $manifestPath, string $fixturePath): array
{
    optionalRuntimeAssertCopy();
    if (getenv('ARC002_BROWSER_TESTS') !== '1') {
        throw new RuntimeException('Closure browser requires its explicit isolated opt-in.');
    }
    $manifest = AuthorityHttpEnvironment::readManifest($manifestPath);
    if ($manifest['mode'] !== 'authority'
        || realpath($fixturePath) !== realpath($manifest['settings']['path'].'/closure-browser.json')
        || is_link($fixturePath) || filesize($fixturePath) > 8192 || (lstat($fixturePath)['nlink'] ?? 0) !== 1) {
        throw new RuntimeException('Closure fixture must belong to the owned database.');
    }
    $fixture = json_decode(file_get_contents($fixturePath), true, 16, JSON_THROW_ON_ERROR);
    if (! is_array($fixture) || ($fixture['token'] ?? null) !== $manifest['settings']['token']) {
        throw new RuntimeException('Closure fixture identity mismatch.');
    }
    foreach (['actor', 'target_user', 'client', 'role', 'permission'] as $key) {
        if (! is_array($fixture[$key] ?? null) || ! is_int($fixture[$key]['id'] ?? null) || $fixture[$key]['id'] < 1) {
            throw new RuntimeException('Closure requires literal positive fixture identifiers.');
        }
    }
    foreach (['actor', 'target_user', 'client'] as $key) {
        if (! is_string($fixture[$key]['uuid'] ?? null)
            || preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/', $fixture[$key]['uuid']) !== 1) {
            throw new RuntimeException('Closure fixture requires its generated UUID.');
        }
    }
    if ($fixture['actor']['id'] === $fixture['target_user']['id']
        || ! is_string($fixture['actor_authority_hash'] ?? null)) {
        throw new RuntimeException('Closure actors and targets must remain distinct.');
    }

    return compact('manifest', 'fixture');
}

/** @return array{app: Application, environment: TestEnvironment, registry: string} */
function closureBrowserBoot(array $manifest): array
{
    $environment = new TestEnvironment;
    $registry = AuthorityHttpEnvironment::registerEnvironment($manifest, $environment);
    try {
        $app = AuthorityHttpEnvironment::bootApplication($manifest, $environment);
        $app['config']->set('inertia.ssr.enabled', false);
        $app['config']->set('broadcasting.default', 'null');

        return compact('app', 'environment', 'registry');
    } catch (Throwable $exception) {
        $environment->cleanUp();
        if (is_file($registry)) {
            unlink($registry);
        }
        throw $exception;
    }
}
