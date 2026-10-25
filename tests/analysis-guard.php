<?php

declare(strict_types=1);
use Illuminate\Foundation\Application;

return (static function (): array {
    $refuse = static function (string $message): never {
        throw new RuntimeException('Isolated analysis refused: '.$message);
    };
    $normalize = static fn (string $path): string => strtolower(str_replace('\\', '/', $path));
    $regular = static function (string $path) use ($refuse): void {
        $stat = lstat($path);
        if ($stat === false || ! is_file($path) || is_link($path) || $stat['nlink'] !== 1) {
            $refuse('single regular input required: '.$path);
        }
        for ($parent = dirname($path); dirname($parent) !== $parent; $parent = dirname($parent)) {
            if (is_link($parent)) {
                $refuse('linked parent directory: '.$path);
            }
        }
    };
    if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Windows') {
        $refuse('Windows CLI is required.');
    }
    $runtime = getenv('START_KIT_ANALYSIS_ROOT');
    $token = getenv('START_KIT_ANALYSIS_TOKEN');
    $project = realpath(dirname(__DIR__));
    if (! is_string($runtime) || ! is_string($token) || ! preg_match('/\A[a-f0-9]{32}\z/', $token) || $project === false) {
        $refuse('use php tests/analyse.php; an owned runtime/token is required before Laravel can bootstrap.');
    }
    if (basename($runtime) !== 'skanalysis-'.substr($token, 0, 16) || is_link($runtime) || realpath($runtime) === false || $normalize(realpath($runtime)) !== $normalize($runtime) || $normalize((string) getcwd()) !== $normalize($runtime)) {
        $refuse('runtime identity or working directory differs.');
    }
    $regular($runtime.'/owner.json');
    $owner = json_decode((string) file_get_contents($runtime.'/owner.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($owner['schema'] ?? null) !== 'START_KIT_ANALYSIS_V1' || ($owner['token'] ?? null) !== $token || $normalize($owner['runtime'] ?? '') !== $normalize($runtime) || $normalize($owner['project'] ?? '') !== $normalize($project) || $normalize((string) realpath(dirname($runtime))) !== $normalize($owner['temporary_parent'] ?? '')) {
        $refuse('ownership manifest/project mismatch.');
    }
    foreach (['bootstrap', 'bootstrap/cache', 'cache', 'tmp', 'home', 'php-scan'] as $directory) {
        $path = $runtime.'/'.$directory;
        if (! is_dir($path) || is_link($path) || $normalize((string) realpath($path)) !== $normalize($path)) {
            $refuse('runtime directory identity differs: '.$directory);
        }
    }
    $runtimeFiles = $owner['runtime_files'] ?? [];
    if (array_keys($runtimeFiles) !== ['bootstrap/app.php', 'phpstan.neon', 'php-scan/99-analysis.ini']) {
        $refuse('runtime input inventory differs.');
    }
    foreach ($runtimeFiles as $name => $hash) {
        $regular($runtime.'/'.$name);
        if (! is_string($hash) || hash_file('sha256', $runtime.'/'.$name) !== $hash) {
            $refuse('owned wrapper/config/INI changed: '.$name);
        }
    }
    $requiredSources = ['tests/analyse.php', 'tests/analysis-guard.php', 'tests/analysis-bootstrap.php', 'tests/analysis-migrations.php', 'tests/TestEnvironment.php', 'bootstrap/app.php', 'phpstan-safety.neon', 'phpstan-base.neon', 'phpstan.neon', 'tests/stubs/sanctum.stub', 'composer.lock'];
    $sources = $owner['source_files'] ?? [];
    foreach ($requiredSources as $name) {
        if (! array_key_exists($name, $sources)) {
            $refuse('missing project input: '.$name);
        }
    }
    foreach ($sources as $name => $hash) {
        if (! in_array($name, [...$requiredSources, 'phpstan-baseline.neon'], true)) {
            $refuse('unexpected project manifest path.');
        }
        $regular($project.'/'.$name);
        if (hash_file('sha256', $project.'/'.$name) !== $hash) {
            $refuse('project input changed: '.$name);
        }
    }
    foreach (['APP_ENV' => 'testing', 'APP_MAINTENANCE_DRIVER' => 'file', 'BCRYPT_ROUNDS' => '4', 'BROADCAST_CONNECTION' => 'null', 'CACHE_STORE' => 'array', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'PULSE_ENABLED' => 'false', 'TELESCOPE_ENABLED' => 'false', 'NIGHTWATCH_ENABLED' => 'false', 'LARAVEL_CLOUD' => '0'] as $key => $expected) {
        foreach ([getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
            if ($key === 'DB_URL' && ($value === false || $value === null)) {
                continue;
            }
            if ($value !== null && $value !== $expected) {
                $refuse('unsafe synthetic environment: '.$key);
            }
        }
    }
    foreach (['APP_BASE_PATH', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'LARAVEL_BOOTSTRAP_PATH', 'LARAVEL_STORAGE_PATH'] as $key) {
        foreach ([getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
            if ($value !== null && $value !== false && $value !== '') {
                $refuse('inherited application path override: '.$key);
            }
        }
    }
    foreach (['TEMP', 'TMP', 'TMPDIR'] as $key) {
        if ($normalize((string) realpath((string) getenv($key))) !== $normalize($runtime.'/tmp')) {
            $refuse('temporary directory override differs: '.$key);
        }
    }
    if (ini_get('auto_prepend_file') !== '' || ini_get('auto_append_file') !== '') {
        $refuse('PHP automatic includes must be disabled by the owned INI.');
    }
    $scanned = preg_split('/,\s*/', php_ini_scanned_files() ?: '', -1, PREG_SPLIT_NO_EMPTY);
    if (! in_array($normalize($runtime.'/php-scan/99-analysis.ini'), array_map(static fn (string $path): string => $normalize(trim($path)), $scanned), true)) {
        $refuse('the owned PHP INI was not loaded.');
    }
    $expectedArguments = ['analyse', '--configuration='.$runtime.'/phpstan.neon', '--debug', '--no-progress', '--no-ansi', '--error-format=json', '--memory-limit=1G'];
    if (array_slice($_SERVER['argv'] ?? [], 1) !== $expectedArguments) {
        $refuse('only the reviewed serial PHPStan command is allowed.');
    }
    if (! is_file($runtime.'/guard-passed.json')) {
        $stream = fopen($runtime.'/guard-passed.json', 'x');
        if ($stream === false) {
            $refuse('cannot publish the safety-before-Laravel receipt.');
        }
        fwrite($stream, json_encode(['schema' => 'ANALYSIS_GUARD_V1', 'token' => $token, 'cwd' => getcwd(), 'project' => $project, 'laravel_loaded' => class_exists(Application::class, false)], JSON_THROW_ON_ERROR));
        fclose($stream);
    }

    return ['runtime' => $runtime, 'project' => $project, 'token' => $token];
})();
