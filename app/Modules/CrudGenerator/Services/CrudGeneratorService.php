<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\Services;

use App\Modules\CrudGenerator\DTOs\CrudBlueprint;
use App\Modules\CrudGenerator\DTOs\CrudField;
use App\Modules\CrudGenerator\DTOs\CrudGeneratedFile;
use App\Modules\CrudGenerator\DTOs\CrudGenerationResult;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ParseError;
use RuntimeException;
use Throwable;

class CrudGeneratorService
{
    public function __construct(private readonly Filesystem $files) {}

    public function preview(CrudBlueprint $blueprint): CrudGenerationResult
    {
        $this->assertBlueprint($blueprint);

        return new CrudGenerationResult(
            blueprint: $blueprint,
            files: $this->plannedFiles($blueprint),
            changes: $this->centralChanges($blueprint),
        );
    }

    public function apply(CrudBlueprint $blueprint, bool $force = false): CrudGenerationResult
    {
        $environment = app()->environment();

        if (! in_array($environment, ['local', 'testing'], true) || config('app.env') !== $environment) {
            throw new AuthorizationException('CRUD generation is unavailable in this environment.');
        }

        $this->assertBlueprint($blueprint);
        $lockPath = base_path('.start-kit-generator.lock');
        $this->assertDestination($lockPath);
        $lock = fopen($lockPath, 'c+b');

        if ($lock === false) {
            throw ValidationException::withMessages(['generation' => 'Unable to open the generation lock.']);
        }

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw ValidationException::withMessages(['generation' => 'Another generation is in progress. Retry after it finishes.']);
            }

            $this->assertDestination($lockPath);
            $result = $this->preview($blueprint);
            $writes = [];

            foreach ($result->files as $file) {
                $this->assertDestination($file->path);

                if ($file->exists && $force && ! $this->ownsFile($blueprint, $file->path)) {
                    throw ValidationException::withMessages(['generation' => 'Force may only overwrite files owned by the same CRUD generation.']);
                }

                $writes[$file->path] = $file->content;
            }

            if ($result->hasConflicts() && ! $force) {
                throw new RuntimeException('Generation has file conflicts. Use force to overwrite.');
            }

            foreach ($this->centralPaths($blueprint) as $path) {
                $this->assertDestination($path);
            }

            if ($blueprint->isModuleTarget() && $blueprint->registerProvider) {
                $writes += $this->registerProvider($blueprint);
            }

            if ($blueprint->isAppTarget()) {
                $writes += $this->registerWebRoute($blueprint);
                $writes += $this->registerApiRouteFile($blueprint);

                if ($blueprint->withObserver) {
                    $writes += $this->registerAppObserver($blueprint);
                }
            }

            $writes += $this->registerExportModel($blueprint);
            $writes += $this->registerNavigation($blueprint);
            $writes += $this->registerApiRoute($blueprint);
            $this->persistGeneration($writes);

            return new CrudGenerationResult(
                $blueprint,
                array_map(fn (CrudGeneratedFile $file): CrudGeneratedFile => new CrudGeneratedFile($file->path, $file->content, true), $result->files),
                $result->changes,
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function assertBlueprint(CrudBlueprint $blueprint): void
    {
        foreach (['model' => $blueprint->model, 'module' => $blueprint->module] as $name => $value) {
            if (strlen($value) > 80 || ! preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/', $value)) {
                throw ValidationException::withMessages([$name => 'Use a simple PHP identifier without path separators.']);
            }
        }

        if (in_array(strtolower($blueprint->model), ['parent', 'self', 'int', 'float', 'bool', 'string', 'true', 'false', 'null', 'void', 'iterable', 'object', 'mixed', 'never', 'static'], true)) {
            throw ValidationException::withMessages(['model' => 'Choose a model name that is not reserved by PHP.']);
        }

        try {
            $tokens = token_get_all('<?php class '.$blueprint->model.' {}', TOKEN_PARSE);

            if ($tokens === []) {
                throw ValidationException::withMessages(['model' => 'The model must be a valid PHP class name.']);
            }
        } catch (ParseError) {
            throw ValidationException::withMessages(['model' => 'The model must be a valid PHP class name.']);
        }

        if (! in_array($blueprint->target, ['app', 'module'], true) || $blueprint->fields === []) {
            throw ValidationException::withMessages(['generation' => 'Choose a supported target and at least one field.']);
        }

        $this->assertProtectedIdentifiers($blueprint);

        foreach ($blueprint->fields as $field) {
            $this->assertField($field);
        }
    }

    private function assertProtectedIdentifiers(CrudBlueprint $blueprint): void
    {
        $models = ['user', 'role', 'permission', 'client', 'activity', 'activitylog', 'personalaccesstoken'];
        $routes = ['users', 'roles', 'permissions', 'clients', 'activity-log', 'activity-logs', 'activities'];
        $tables = ['users', 'roles', 'permissions', 'clients', 'activity_log', 'activities', 'personal_access_tokens', 'users_roles', 'users_permissions', 'roles_permissions'];

        foreach (['roles', 'permissions', 'users_roles', 'users_permissions', 'roles_permissions'] as $key) {
            $table = config('permissions.tables.'.$key);
            if (is_string($table) && $table !== '') {
                $tables[] = strtolower($table);
            }
        }
        $activityTable = config('activitylog.table_name', 'activity_log');
        if (is_string($activityTable) && $activityTable !== '') {
            $tables[] = strtolower($activityTable);
        }

        if (in_array(strtolower($blueprint->model), $models, true)
            || in_array(strtolower($blueprint->routeName()), $routes, true)
            || in_array(strtolower($blueprint->tableName()), $tables, true)) {
            throw ValidationException::withMessages(['model' => 'Choose identifiers outside the protected application domains.']);
        }
    }

    private function assertField(mixed $field): void
    {
        if (! $field instanceof CrudField
            || ! preg_match('/\A[a-z][a-z0-9_]*\z/', $field->name)
            || ! in_array($field->type, ['string', 'text', 'integer', 'bigInteger', 'decimal', 'float', 'double', 'boolean', 'date', 'dateTime', 'timestamp', 'json'], true)) {
            throw ValidationException::withMessages(['fields' => 'Use supported field types and snake_case identifiers.']);
        }

        foreach ($field->validation as $rule) {
            $this->assertValidationRule($rule);
        }
    }

    private function assertValidationRule(mixed $rule): void
    {
        if (! is_string($rule)) {
            throw ValidationException::withMessages(['fields' => 'Validation rules must be strings.']);
        }
    }

    /** @return list<string> */
    private function centralPaths(CrudBlueprint $blueprint): array
    {
        $paths = [config_path('navigation.php'), resource_path('js/lib/api/api-routes.ts')];

        if ($blueprint->isModuleTarget() && $blueprint->registerProvider) {
            $paths[] = base_path('bootstrap/providers.php');
        }

        if ($blueprint->isAppTarget()) {
            $paths[] = base_path('routes/web.php');
            $paths[] = base_path('routes/api/api.php');

            if ($blueprint->withObserver) {
                $paths[] = app_path('Providers/AppServiceProvider.php');
            }
        }

        if ($blueprint->withExport) {
            $paths[] = config_path('exports.php');
        }

        return $paths;
    }

    private function assertDestination(string $path): void
    {
        $normalize = fn (string $value): string => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $value)) : str_replace('\\', '/', $value);
        $base = rtrim($normalize(base_path()), '/');
        $normalized = $normalize($path);
        $root = realpath(base_path());

        if ($root === false || is_link(base_path()) || ! str_starts_with($normalized, $base.'/')) {
            throw ValidationException::withMessages(['generation' => 'Generation destinations must remain inside the project.']);
        }

        $relative = substr(str_replace('\\', '/', $path), strlen($base) + 1);
        $parts = explode('/', $relative);
        $current = base_path();

        foreach ($parts as $index => $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_contains($part, ':') || str_contains($part, "\0")) {
                throw ValidationException::withMessages(['generation' => 'Invalid generation destination.']);
            }

            if (is_dir($current)) {
                $siblings = scandir($current);

                if ($siblings === false) {
                    throw ValidationException::withMessages(['generation' => 'Unable to inspect generation source identities.']);
                }

                foreach ($siblings as $sibling) {
                    if ($sibling !== $part && strcasecmp($sibling, $part) === 0) {
                        throw ValidationException::withMessages(['generation' => 'A source identity already exists with different letter casing.']);
                    }
                }
            }

            $current .= DIRECTORY_SEPARATOR.$part;
            clearstatcache(true, $current);

            if (! file_exists($current) && ! is_link($current)) {
                continue;
            }

            $stat = lstat($current);

            if ($stat === false) {
                throw ValidationException::withMessages(['generation' => 'Unable to inspect a generation destination.']);
            }

            $resolved = realpath($current);
            $isFile = $index === count($parts) - 1;

            if (is_link($current) || $resolved === false
                || ! str_starts_with($normalize($resolved), rtrim($normalize($root), '/').'/')
                || ($stat['mode'] & 0170000) !== ($isFile ? 0100000 : 0040000)
                || ($isFile && $stat['nlink'] > 1)) {
                throw ValidationException::withMessages(['generation' => 'Generation refuses linked, special or redirected destinations.']);
            }
        }
    }

    private function ownershipMarker(CrudBlueprint $blueprint, string $path): string
    {
        $relative = substr(str_replace('\\', '/', $path), strlen(rtrim(str_replace('\\', '/', base_path()), '/')) + 1);

        return '// @start-kit-crud:'.hash('sha256', implode('|', ['v1', $blueprint->target, $blueprint->model, $blueprint->module, $relative]));
    }

    private function ownedContent(CrudBlueprint $blueprint, string $path, string $content): string
    {
        $marker = $this->ownershipMarker($blueprint, $path)."\n";

        return str_ends_with($path, '.php') ? "<?php\n".$marker.substr($content, strlen("<?php\n")) : $marker.$content;
    }

    private function ownsFile(CrudBlueprint $blueprint, string $path): bool
    {
        $prefix = str_ends_with($path, '.php') ? "<?php\n" : '';

        return str_starts_with($this->files->get($path), $prefix.$this->ownershipMarker($blueprint, $path)."\n");
    }

    /** @param array<string, string> $writes */
    private function persistGeneration(array $writes): void
    {
        $originals = [];

        foreach ($writes as $path => $content) {
            $this->assertDestination($path);

            if (str_ends_with($path, '.php')) {
                try {
                    $this->assertPhpSymbols(token_get_all($content, TOKEN_PARSE));
                } catch (ParseError $error) {
                    throw ValidationException::withMessages(['generation' => 'Generated PHP or its central registration is invalid: '.$error->getMessage()]);
                }
            }

            $exists = is_file($path);
            $originals[$path] = ['content' => $exists ? $this->files->get($path) : null, 'mode' => $exists ? fileperms($path) & 0777 : null];
        }

        $attempted = [];
        $directories = [];

        try {
            foreach ($writes as $path => $content) {
                if ($originals[$path]['content'] === $content) {
                    continue;
                }

                $this->assertDestination($path);

                for ($directory = dirname($path); ! is_dir($directory); $directory = dirname($directory)) {
                    $directories[$directory] = $directory;
                }

                $this->files->ensureDirectoryExists(dirname($path));
                $attempted[] = $path;
                $this->replaceFile($path, $content, $originals[$path]['mode']);
            }
        } catch (Throwable $failure) {
            $rollbackErrors = [];

            foreach (array_reverse($attempted) as $path) {
                try {
                    $this->assertDestination($path);
                    $original = $originals[$path];

                    if ($original['content'] !== null) {
                        if (! is_file($path) || $this->files->get($path) !== $original['content'] || (fileperms($path) & 0777) !== $original['mode']) {
                            $this->replaceFile($path, $original['content'], $original['mode']);
                        }
                    } elseif (is_file($path) && ! $this->files->delete($path)) {
                        throw new RuntimeException('Unable to remove a new generation file.');
                    }
                } catch (Throwable $rollbackFailure) {
                    $rollbackErrors[] = $rollbackFailure->getMessage();
                }
            }

            uksort($directories, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

            foreach ($directories as $directory) {
                if (is_dir($directory) && ! is_link($directory) && scandir($directory) === ['.', '..']) {
                    rmdir($directory);
                }
            }

            if ($rollbackErrors !== []) {
                throw new RuntimeException('Generation failed and rollback was incomplete: '.implode('; ', $rollbackErrors), 0, $failure);
            }

            throw $failure;
        }
    }

    private function replaceFile(string $path, string $content, ?int $mode): void
    {
        $this->assertDestination($path);
        $mode ??= 0666 & ~umask();
        $staging = dirname($path).'/.start-kit-crud-'.bin2hex(random_bytes(16));
        $temporary = $staging.'/content';
        $this->assertDestination($temporary);

        if (! $this->files->makeDirectory($staging, 0700)) {
            throw new RuntimeException('Unable to create private generation staging.');
        }

        $failure = null;

        try {
            $this->assertDestination($temporary);
            $this->files->replace($temporary, $content, $mode);
            $this->assertDestination($temporary);

            if (! is_file($temporary) || $this->files->get($temporary) !== $content || (fileperms($temporary) & 0777) !== $mode) {
                throw new RuntimeException('A generation file was not written completely with its original permissions.');
            }

            $this->assertDestination($path);

            if (! $this->files->move($temporary, $path)) {
                throw new RuntimeException('Unable to replace a generation file atomically.');
            }

            $this->assertDestination($path);

            if ($this->files->get($path) !== $content || (fileperms($path) & 0777) !== $mode) {
                throw new RuntimeException('A generation file was not written completely with its original permissions.');
            }
        } catch (Throwable $error) {
            $failure = $error;

            throw $error;
        } finally {
            try {
                foreach (scandir($staging) as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }

                    $this->assertDestination($staging.'/'.$entry);

                    if (! $this->files->delete($staging.'/'.$entry)) {
                        throw new RuntimeException('Unable to remove an owned generation temporary file.');
                    }
                }

                if (! rmdir($staging)) {
                    throw new RuntimeException('Unable to remove private generation staging.');
                }
            } catch (Throwable $cleanupFailure) {
                throw new RuntimeException('Generation staging cleanup failed: '.$cleanupFailure->getMessage(), 0, $failure ?? $cleanupFailure);
            }
        }
    }

    /**
     * @param  list<array{int, string, int}|string>  $tokens
     * @return list<array{class: string, alias: string}>
     */
    private function assertPhpSymbols(array $tokens): array
    {
        $depth = 0;
        $namespaceDepth = 0;
        $namespaceCount = 0;
        $symbols = [];
        $imports = [];

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($token === '}') {
                if ($depth === $namespaceDepth) {
                    $symbols = [];
                    $namespaceDepth = 0;
                }

                $depth--;
            }

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                if (++$namespaceCount > 1) {
                    throw ValidationException::withMessages(['generation' => 'Multiple PHP namespaces require manual registration review.']);
                }

                $symbols = [];

                while (isset($tokens[++$index]) && $tokens[$index] !== ';' && $tokens[$index] !== '{') {
                    continue;
                }

                if (($tokens[$index] ?? null) === '{') {
                    $depth++;
                }

                $namespaceDepth = $depth;
            } elseif ($token[0] === T_USE && $depth === $namespaceDepth) {
                $next = $index + 1;

                while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $next++;
                }

                if (($tokens[$next] ?? null) === '(') {
                    continue;
                }

                $statement = '';

                while (isset($tokens[++$index]) && $tokens[$index] !== ';') {
                    $part = $tokens[$index];

                    if (is_array($part)) {
                        $statement .= in_array($part[0], [T_COMMENT, T_DOC_COMMENT], true) ? ' ' : $part[1];
                    } else {
                        $statement .= $part;
                    }
                }

                foreach ($this->importDeclarations($statement) as $import) {
                    if (isset($symbols[$import['alias']])) {
                        throw ValidationException::withMessages(['generation' => 'Conflicting PHP imports or declarations require a different generation name.']);
                    }

                    $symbols[$import['alias']] = true;
                    $imports[] = $import;
                }
            } elseif (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $next = $index + 1;

                while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $next++;
                }

                if (isset($tokens[$next]) && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                    $alias = strtolower($tokens[$next][1]);

                    if (isset($symbols[$alias])) {
                        throw ValidationException::withMessages(['generation' => 'Conflicting PHP imports or declarations require a different generation name.']);
                    }

                    $symbols[$alias] = true;
                }
            }
        }

        return $imports;
    }

    /** @return list<array{class: string, alias: string}> */
    private function importDeclarations(string $statement): array
    {
        $statement = trim($statement);

        if (str_starts_with($statement, '(') || preg_match('/\A(?:function|const)\s+/i', $statement)) {
            return [];
        }

        $group = strpos($statement, '{');
        $prefix = '';

        if ($group !== false) {
            $end = strrpos($statement, '}');

            if ($end === false) {
                throw ValidationException::withMessages(['generation' => 'Unable to inspect a grouped PHP import.']);
            }

            $prefix = rtrim(trim(substr($statement, 0, $group)), '\\').'\\';
            $statement = substr($statement, $group + 1, $end - $group - 1);
        }

        $imports = [];

        foreach (explode(',', $statement) as $entry) {
            $entry = trim($entry);

            if ($entry === '' || preg_match('/\A(?:function|const)\s+/i', $entry)) {
                continue;
            }

            $parts = preg_split('/\s+as\s+/i', $entry);

            if ($parts === false) {
                throw ValidationException::withMessages(['generation' => 'Unable to inspect a PHP import.']);
            }

            $class = ltrim($prefix.trim($parts[0]), '\\');
            $alias = count($parts) === 2 ? trim($parts[1]) : Str::afterLast($class, '\\');
            $imports[] = ['class' => $class, 'alias' => strtolower($alias)];
        }

        return $imports;
    }

    private function hasPhpImport(string $contents, string $class): bool
    {
        $alias = strtolower(Str::afterLast($class, '\\'));

        foreach ($this->assertPhpSymbols(token_get_all($contents, TOKEN_PARSE)) as $import) {
            if ($import['class'] === $class && $import['alias'] === $alias) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, CrudGeneratedFile>
     */
    private function plannedFiles(CrudBlueprint $blueprint): array
    {
        $base = $this->backendBasePath($blueprint);
        $pageBase = resource_path("js/pages/app/{$blueprint->pageDirectory()}");
        $migration = $this->migrationPath($blueprint);

        $files = [
            $this->modelPath($blueprint) => $this->model($blueprint),
            $this->webControllerPath($blueprint) => $this->webController($blueprint),
            $this->apiControllerPath($blueprint) => $this->apiController($blueprint),
            "{$base}/Http/Requests/{$blueprint->model}/Store{$blueprint->model}Request.php" => $this->storeRequest($blueprint),
            "{$base}/Http/Requests/{$blueprint->model}/Update{$blueprint->model}Request.php" => $this->updateRequest($blueprint),
            "{$base}/Http/Resources/{$blueprint->model}Resource.php" => $this->resource($blueprint),
            $migration => $this->migration($blueprint),
            resource_path("js/types/app/{$blueprint->pageDirectorySingular()}.ts") => $this->typeScriptType($blueprint),
            "{$pageBase}/index.tsx" => $this->indexPage($blueprint),
            "{$pageBase}/create.tsx" => $this->createPage($blueprint),
            "{$pageBase}/edit.tsx" => $this->editPage($blueprint),
            "{$pageBase}/show.tsx" => $this->showPage($blueprint),
            "{$pageBase}/partials/{$blueprint->pageDirectorySingular()}-form-fields.tsx" => $this->formFieldsPage($blueprint),
            "{$pageBase}/components/{$blueprint->pageDirectorySingular()}-sub-nav.tsx" => $this->subNavComponent($blueprint),
        ];

        if ($blueprint->isModuleTarget()) {
            $files["{$base}/routes.php"] = $this->routes($blueprint);
        }

        if ($blueprint->isModuleTarget() && $blueprint->registerProvider) {
            $files["{$base}/Providers/{$blueprint->module}ServiceProvider.php"] = $this->provider($blueprint);
        }

        if ($blueprint->withFactory) {
            $files[$this->factoryPath($blueprint)] = $this->factory($blueprint);
        }

        if ($blueprint->withFactory && $blueprint->withSeeder) {
            $files[$this->seederPath($blueprint)] = $this->seeder($blueprint);
        }

        if ($blueprint->withPolicy) {
            $files[$this->policyPath($blueprint)] = $this->policy($blueprint);
        }

        if ($blueprint->withObserver) {
            $files[$this->observerPath($blueprint)] = $this->observer($blueprint);
        }

        if ($blueprint->withTests) {
            $files[base_path("tests/Feature/Crud/{$blueprint->model}CrudTest.php")] = $this->featureTest($blueprint);
        }

        return array_map(
            fn (string $path, string $content): CrudGeneratedFile => new CrudGeneratedFile($path, $this->ownedContent($blueprint, $path, $content), $this->files->exists($path)),
            array_keys($files),
            array_values($files),
        );
    }

    /**
     * @return array<int, string>
     */
    private function centralChanges(CrudBlueprint $blueprint): array
    {
        $changes = [
            "Add {$blueprint->routeName()} to config/navigation.php",
            "Add {$blueprint->routeName()} endpoints to resources/js/lib/api/api-routes.ts",
        ];

        if ($blueprint->isAppTarget()) {
            array_unshift(
                $changes,
                "Register {$blueprint->controllerClass()} in routes/web.php",
                "Register {$blueprint->apiControllerClass()} in routes/api/api.php",
            );

            if ($blueprint->withObserver) {
                $changes[] = "Register {$blueprint->observerClass()} in app/Providers/AppServiceProvider.php";
            }
        } elseif ($blueprint->registerProvider) {
            array_unshift($changes, "Generate and register {$blueprint->providerClass()} in bootstrap/providers.php");
        } else {
            array_unshift($changes, "Skip ServiceProvider generation and bootstrap/providers.php registration for {$blueprint->providerClass()}");
        }

        if ($blueprint->withExport) {
            $changes[] = "Allow {$blueprint->modelClass()} in config/exports.php";
        }

        return $changes;
    }

    private function backendBasePath(CrudBlueprint $blueprint): string
    {
        if ($blueprint->isAppTarget()) {
            return app_path();
        }

        return app_path("Modules/{$blueprint->module}");
    }

    private function modelPath(CrudBlueprint $blueprint): string
    {
        if ($blueprint->isAppTarget()) {
            return app_path("Models/{$blueprint->model}.php");
        }

        return app_path("Modules/{$blueprint->module}/Models/{$blueprint->model}.php");
    }

    private function webControllerPath(CrudBlueprint $blueprint): string
    {
        if ($blueprint->isAppTarget()) {
            return app_path("Http/Controllers/{$blueprint->model}Controller.php");
        }

        return app_path("Modules/{$blueprint->module}/Http/Controllers/{$blueprint->model}Controller.php");
    }

    private function apiControllerPath(CrudBlueprint $blueprint): string
    {
        if ($blueprint->isAppTarget()) {
            return app_path("Http/Controllers/Api/{$blueprint->model}ApiController.php");
        }

        return app_path("Modules/{$blueprint->module}/Http/Controllers/Api/{$blueprint->model}ApiController.php");
    }

    private function migrationPath(CrudBlueprint $blueprint): string
    {
        $directory = $blueprint->isAppTarget() ? database_path('migrations') : app_path("Modules/{$blueprint->module}/Database/Migrations");
        $path = $directory.'/'.$this->migrationName($blueprint);
        $this->assertDestination($path);
        $candidates = $this->files->glob($directory.'/*_create_'.$blueprint->tableName().'_table.php');

        if (count($candidates) > 1) {
            throw ValidationException::withMessages(['generation' => 'Multiple create-table migrations require manual review.']);
        }

        if ($candidates !== []) {
            $path = $candidates[0];
            $this->assertDestination($path);

            if (! $this->ownsFile($blueprint, $path)) {
                throw ValidationException::withMessages(['generation' => 'An existing create-table migration is not owned by this generation.']);
            }
        }

        return $path;
    }

    private function factoryPath(CrudBlueprint $blueprint): string
    {
        if ($blueprint->isAppTarget()) {
            return database_path("factories/{$blueprint->model}Factory.php");
        }

        return app_path("Modules/{$blueprint->module}/Database/Factories/{$blueprint->model}Factory.php");
    }

    private function seederPath(CrudBlueprint $blueprint): string
    {
        if ($blueprint->isAppTarget()) {
            return database_path("seeders/{$blueprint->model}Seeder.php");
        }

        return app_path("Modules/{$blueprint->module}/Database/Seeders/{$blueprint->model}Seeder.php");
    }

    private function policyPath(CrudBlueprint $blueprint): string
    {
        if ($blueprint->isAppTarget()) {
            return app_path("Policies/{$blueprint->model}Policy.php");
        }

        return app_path("Modules/{$blueprint->module}/Policies/{$blueprint->model}Policy.php");
    }

    private function observerPath(CrudBlueprint $blueprint): string
    {
        if ($blueprint->isAppTarget()) {
            return app_path("Observers/{$blueprint->model}Observer.php");
        }

        return app_path("Modules/{$blueprint->module}/Observers/{$blueprint->model}Observer.php");
    }

    /** @return array<string, string> */
    private function registerProvider(CrudBlueprint $blueprint): array
    {
        $path = base_path('bootstrap/providers.php');
        $contents = $this->files->get($path);
        $use = "use {$blueprint->providerClass()};";
        $entry = "{$blueprint->module}ServiceProvider::class,";
        $newline = $this->lineEnding($contents);

        if (! $this->hasPhpImport($contents, $blueprint->providerClass())) {
            $contents = $this->patchUnique($contents, '/^use App\\\\Providers\\\\TelescopeServiceProvider;\R/m', fn (array $match): string => $match[0].$use.$newline);
        }

        if (! $this->hasExactClassEntry($contents, $entry)) {
            $contents = $this->patchUnique($contents, '/^([ \t]*)NotificationsServiceProvider::class,[ \t]*\R/m', fn (array $match): string => $match[0].$match[1].$entry.$newline);
        }

        return [$path => $contents];
    }

    /** @return array<string, string> */
    private function registerWebRoute(CrudBlueprint $blueprint): array
    {
        $path = base_path('routes/web.php');
        $contents = $this->files->get($path);
        $use = "use {$blueprint->controllerClass()};";
        $newline = $this->lineEnding($contents);

        if (! $this->hasPhpImport($contents, $blueprint->controllerClass())) {
            $contents = $this->patchUnique($contents, '/^use App\\\\Modules\\\\Clients\\\\Http\\\\Controllers\\\\ClientController;\R/m', fn (array $match): string => $match[0].$use.$newline);
        }

        if (! str_contains($contents, "Route::resource('{$blueprint->routeName()}', {$blueprint->model}Controller::class)")) {
            // Best Play keeps the ACL resources in routes/admin.php; business CRUDs anchor on the clients resource of routes/web.php.
            $contents = $this->patchUnique($contents, "/^([ \t]*)Route::resource\('clients', ClientController::class\)->parameters\(\['clients' => 'client'\]\);[ \t]*\R/m", fn (array $match): string => $match[0].$newline.$match[1]."Route::resource('{$blueprint->routeName()}', {$blueprint->model}Controller::class)".$newline.$match[1]."    ->parameters(['{$blueprint->routeName()}' => '{$blueprint->modelVariable()}']);".$newline);
        }

        return [$path => $contents];
    }

    /** @return array<string, string> */
    private function registerApiRouteFile(CrudBlueprint $blueprint): array
    {
        $path = base_path('routes/api/api.php');
        $contents = $this->files->get($path);
        $use = "use {$blueprint->apiControllerClass()};";
        $newline = $this->lineEnding($contents);

        if (! $this->hasPhpImport($contents, $blueprint->apiControllerClass())) {
            $contents = $this->patchUnique($contents, '/^use App\\\\Http\\\\Controllers\\\\Api\\\\UserApiController;\R/m', fn (array $match): string => $match[0].$use.$newline);
        }

        if (! str_contains($contents, "Route::apiResource('{$blueprint->routeName()}', {$blueprint->model}ApiController::class)")) {
            $contents = $this->patchUnique($contents, "/^([ \t]*)Route::apiResource\('users', UserApiController::class\)->only\('index'\);[ \t]*\R/m", fn (array $match): string => $match[0].$newline.$match[1]."Route::apiResource('{$blueprint->routeName()}', {$blueprint->model}ApiController::class)".$newline.$match[1]."    ->parameters(['{$blueprint->routeName()}' => '{$blueprint->modelVariable()}']);".$newline);
        }

        return [$path => $contents];
    }

    /** @return array<string, string> */
    private function registerAppObserver(CrudBlueprint $blueprint): array
    {
        $path = app_path('Providers/AppServiceProvider.php');
        $contents = $this->files->get($path);
        $modelUse = "use {$blueprint->modelClass()};";
        $observerUse = "use {$blueprint->observerClass()};";
        $registration = "{$blueprint->model}::observe({$blueprint->model}Observer::class);";
        $newline = $this->lineEnding($contents);

        if (! $this->hasPhpImport($contents, $blueprint->modelClass())) {
            $contents = $this->patchUnique($contents, '/^use App\\\\Listeners\\\\LogUserLogin;\R/m', fn (array $match): string => $match[0].$modelUse.$newline);
        }

        if (! $this->hasPhpImport($contents, $blueprint->observerClass())) {
            $contents = $this->patchUnique($contents, '/^use App\\\\Listeners\\\\LogUserLogin;\R/m', fn (array $match): string => $match[0].$observerUse.$newline);
        }

        if (! str_contains($contents, $registration)) {
            if (preg_match('/private function registerObservers\(\): void[ \t]*\{\}/', $contents)) {
                $contents = $this->patchUnique($contents, '/^([ \t]*)private function registerObservers\(\): void[ \t]*\{\}[ \t]*\R/m', fn (array $match): string => $match[1].'private function registerObservers(): void'.$newline.$match[1].'{'.$newline.$match[1].'    '.$registration.$newline.$match[1].'}'.$newline);
            } else {
                $contents = $this->patchUnique($contents, '/^([ \t]*)private function registerObservers\(\): void\R[ \t]*\{\R/m', fn (array $match): string => $match[0].$match[1].'    '.$registration.$newline);
            }
        }

        return [$path => $contents];
    }

    /** @return array<string, string> */
    private function registerExportModel(CrudBlueprint $blueprint): array
    {
        if (! $blueprint->withExport) {
            return [];
        }

        $path = config_path('exports.php');
        $contents = $this->files->get($path);
        $use = "use {$blueprint->modelClass()};";
        $entry = "{$blueprint->model}::class,";
        $newline = $this->lineEnding($contents);

        if (! $this->hasPhpImport($contents, $blueprint->modelClass())) {
            $contents = $this->patchUnique($contents, '/\A<\?php\R/', fn (array $match): string => $match[0].$newline.$use.$newline);
        }

        if (! $this->hasExactClassEntry($contents, $entry)) {
            $contents = $this->patchUnique($contents, '/^([ \t]*)User::class,[ \t]*\R/m', fn (array $match): string => $match[0].$match[1].$entry.$newline);
        }

        return [$path => $contents];
    }

    /** @return array<string, string> */
    private function registerNavigation(CrudBlueprint $blueprint): array
    {
        $path = config_path('navigation.php');
        $contents = $this->files->get($path);
        $prefix = "'/{$blueprint->routeName()}',";
        $slug = Str::snake(Str::pluralStudly($blueprint->model));
        $newline = $this->lineEnding($contents);

        if (! str_contains($contents, $prefix)) {
            $contents = $this->patchUnique($contents, "/^([ \t]*)'\/exportacoes',[ \t]*\R/m", fn (array $match): string => $match[1].$prefix.$newline.$match[0]);
        }

        if (! str_contains($contents, "'slug' => '{$slug}'")) {
            $rows = [
                "'slug' => ".var_export($slug, true).',',
                "'label' => ".var_export($this->humanPlural($blueprint), true).',',
                "'icon' => ".var_export($blueprint->icon, true).',',
                "'type' => 'link',",
                "'href' => ['route' => ".var_export($blueprint->routeName().'.index', true).'],',
                "'order' => 6,", "'enabled' => true,", "'visible' => true,",
            ];
            $contents = $this->patchUnique($contents, "/^([ \t]*)\[\R([ \t]*)'slug' => 'exportacoes',/m", function (array $match) use ($rows, $newline): string {
                return $match[1].'['.$newline.implode($newline, array_map(fn (string $row): string => $match[2].$row, $rows)).$newline.$match[1].'],'.$newline.$match[0];
            });
        }

        return [$path => $contents];
    }

    /** @return array<string, string> */
    private function registerApiRoute(CrudBlueprint $blueprint): array
    {
        $path = resource_path('js/lib/api/api-routes.ts');
        $contents = $this->files->get($path);
        $key = Str::camel($blueprint->modelPluralVariable());

        if (str_contains($contents, "    {$key}: {")) {
            return [];
        }

        $block = <<<TS
    {$key}: {
        index: '/api/v1/{$blueprint->routeName()}',
        show: (id: number | string) => `/api/v1/{$blueprint->routeName()}/\${id}`,
        store: '/api/v1/{$blueprint->routeName()}',
        update: (id: number | string) => `/api/v1/{$blueprint->routeName()}/\${id}`,
        destroy: (id: number | string) => `/api/v1/{$blueprint->routeName()}/\${id}`,
    },

TS;

        $contents = $this->patchUnique($contents, '/^([ \t]*)exports:[ \t]*\{\R/m', fn (array $match): string => str_replace("\n", $this->lineEnding($contents), $block).$match[0]);

        return [$path => $contents];
    }

    /** @param Closure(array<int, string>): string $replace */
    private function patchUnique(string $contents, string $pattern, Closure $replace): string
    {
        $updated = preg_replace_callback($pattern, $replace, $contents, -1, $count);

        if ($updated === null || $count !== 1) {
            throw ValidationException::withMessages(['generation' => 'A central registration anchor is missing or ambiguous. Review the project before generation.']);
        }

        return $updated;
    }

    private function lineEnding(string $contents): string
    {
        return str_contains($contents, "\r\n") ? "\r\n" : "\n";
    }

    private function hasExactClassEntry(string $contents, string $entry): bool
    {
        $class = Str::before($entry, '::class,');
        $tokens = array_values(array_filter(token_get_all($contents, TOKEN_PARSE), fn (array|string $token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $count = 0;

        for ($index = 0; $index + 3 < count($tokens); $index++) {
            $name = $tokens[$index];
            $separator = $tokens[$index + 1];
            $keyword = $tokens[$index + 2];

            if (is_array($name) && $name[0] === T_STRING && $name[1] === $class
                && is_array($separator) && $separator[0] === T_DOUBLE_COLON
                && is_array($keyword) && $keyword[0] === T_STRING && strcasecmp($keyword[1], 'class') === 0
                && $tokens[$index + 3] === ',') {
                $count++;
            }
        }

        if ($count > 1) {
            throw ValidationException::withMessages(['generation' => 'A central class registration is ambiguous.']);
        }

        return $count === 1;
    }

    private function provider(CrudBlueprint $blueprint): string
    {
        $observerRegistration = $blueprint->withObserver
            ? "\n        \\{$blueprint->modelClass()}::observe(\\{$blueprint->observerClass()}::class);"
            : '';

        $policyRegistration = $blueprint->withPolicy
            ? "\n        Gate::policy(\\{$blueprint->modelClass()}::class, \\{$blueprint->policyClass()}::class);"
            : '';

        $gateUse = $blueprint->withPolicy ? "\nuse Illuminate\\Support\\Facades\\Gate;" : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Modules\\{$blueprint->module}\\Providers;
{$gateUse}
use Illuminate\\Support\\ServiceProvider;

class {$blueprint->module}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \$this->loadRoutesFrom(__DIR__.'/../routes.php');
        \$this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');{$policyRegistration}{$observerRegistration}
    }
}

PHP;
    }

    private function routes(CrudBlueprint $blueprint): string
    {
        $apiController = $blueprint->apiControllerClass();
        $controller = $blueprint->controllerClass();

        return <<<PHP
<?php

use {$apiController};
use {$controller};
use Illuminate\\Support\\Facades\\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::resource('{$blueprint->routeName()}', {$blueprint->model}Controller::class)
        ->parameters(['{$blueprint->routeName()}' => '{$blueprint->modelVariable()}']);
});

Route::prefix('api/v1')
    ->as('api.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::apiResource('{$blueprint->routeName()}', {$blueprint->model}ApiController::class)
            ->parameters(['{$blueprint->routeName()}' => '{$blueprint->modelVariable()}']);
    });

PHP;
    }

    private function model(CrudBlueprint $blueprint): string
    {
        $namespace = Str::beforeLast($blueprint->modelClass(), '\\');
        $uses = [
            'use App\\Concerns\\Searchable;',
            'use App\\Supports\\Traits\\HasUuidIdentifier;',
        ];

        if ($blueprint->withExport) {
            array_unshift($uses, 'use App\\Concerns\\Exportable;');
        }

        $factoryUse = $blueprint->withFactory ? "use {$blueprint->factoryClass()};\nuse Illuminate\\Database\\Eloquent\\Factories\\Factory;\nuse Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\n" : '';
        $traitUses = array_filter([
            $blueprint->withExport ? 'Exportable' : null,
            $blueprint->withFactory ? 'HasFactory' : null,
            'HasUuidIdentifier',
            'Searchable',
        ]);

        $fillable = $this->phpArray(array_merge(['uuid'], array_map(fn (CrudField $field): string => $field->name, $blueprint->fields)));
        $searchable = $this->phpArray(array_map(fn (CrudField $field): string => $field->name, array_filter($blueprint->fields, fn (CrudField $field): bool => $field->searchable)));
        $filterable = $this->phpArray(array_values(array_unique(array_merge(['uuid'], array_map(fn (CrudField $field): string => $field->name, array_filter($blueprint->fields, fn (CrudField $field): bool => $field->filterable)), ['created_at', 'updated_at']))));
        $sortable = $this->phpArray(array_values(array_unique(array_merge(['id', 'uuid'], array_map(fn (CrudField $field): string => $field->name, array_filter($blueprint->fields, fn (CrudField $field): bool => $field->sortable)), ['created_at', 'updated_at']))));
        $exportable = $this->phpAssocArray(array_filter($blueprint->fields, fn (CrudField $field): bool => $field->exportable));
        $casts = $this->casts($blueprint);
        $traitLine = 'use '.implode(', ', $traitUses).';';
        $exportBlock = $blueprint->withExport ? "\n    /** @var array<string, string> */\n    protected array \$exportable = {$exportable};\n" : '';
        $factoryBlock = $blueprint->withFactory ? "\n    protected static function newFactory(): Factory\n    {\n        return {$blueprint->model}Factory::new();\n    }\n" : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$factoryUse}use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Attributes\\Fillable;
{$this->implodeLines($uses)}

#[Fillable({$fillable})]
class {$blueprint->model} extends Model
{
    {$traitLine}
{$exportBlock}
    /** @var array<int, string> */
    protected array \$searchable = {$searchable};

    /** @var array<int, string> */
    protected array \$filterable = {$filterable};

    /** @var array<int, string> */
    protected array \$sortable = {$sortable};

    protected string \$defaultSortColumn = 'id';

    protected string \$defaultSortDirection = 'desc';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return {$casts};
    }
{$factoryBlock}
}

PHP;
    }

    private function webController(CrudBlueprint $blueprint): string
    {
        $var = $blueprint->modelVariable();
        $namespace = Str::beforeLast($blueprint->controllerClass(), '\\');
        $modelClass = $blueprint->modelClass();
        $resourceClass = $blueprint->resourceClass();
        $requestNamespace = $blueprint->requestNamespace();
        $controllerUse = $blueprint->isAppTarget() ? '' : "use App\\Http\\Controllers\\Controller;\n";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$controllerUse}use {$requestNamespace}\\Store{$blueprint->model}Request;
use {$requestNamespace}\\Update{$blueprint->model}Request;
use {$resourceClass};
use {$modelClass};
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Support\\Facades\\Gate;
use Inertia\\Inertia;
use Inertia\\Response;

class {$blueprint->model}Controller extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', {$blueprint->model}::class);

        return Inertia::render('app/{$blueprint->pageDirectory()}/index');
    }

    public function create(): Response
    {
        Gate::authorize('create', {$blueprint->model}::class);

        return Inertia::render('app/{$blueprint->pageDirectory()}/create');
    }

    public function store(Store{$blueprint->model}Request \$request): RedirectResponse
    {
        Gate::authorize('create', {$blueprint->model}::class);

        \${$var} = {$blueprint->model}::create(\$request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('{$blueprint->model} created.')]);

        return Gate::allows('view', \${$var})
            ? to_route('{$blueprint->routeName()}.show', \${$var})
            : to_route('{$blueprint->routeName()}.create');
    }

    public function show({$blueprint->model} \${$var}): Response
    {
        Gate::authorize('view', \${$var});

        return Inertia::render('app/{$blueprint->pageDirectory()}/show', [
            '{$var}Data' => new {$blueprint->model}Resource(\${$var}),
        ]);
    }

    public function edit({$blueprint->model} \${$var}): Response
    {
        Gate::authorize('update', \${$var});

        return Inertia::render('app/{$blueprint->pageDirectory()}/edit', [
            '{$var}Data' => new {$blueprint->model}Resource(\${$var}),
        ]);
    }

    public function update(Update{$blueprint->model}Request \$request, {$blueprint->model} \${$var}): RedirectResponse
    {
        Gate::authorize('update', \${$var});

        \${$var}->update(\$request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('{$blueprint->model} updated.')]);

        return Gate::allows('view', \${$var})
            ? to_route('{$blueprint->routeName()}.show', \${$var})
            : to_route('{$blueprint->routeName()}.edit', \${$var});
    }

    public function destroy({$blueprint->model} \${$var}): RedirectResponse
    {
        Gate::authorize('delete', \${$var});

        \${$var}->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('{$blueprint->model} deleted.')]);

        if (Gate::allows('viewAny', {$blueprint->model}::class)) {
            return to_route('{$blueprint->routeName()}.index', [], 303);
        }

        return to_route('app.dashboard', [], 303);
    }
}

PHP;
    }

    private function apiController(CrudBlueprint $blueprint): string
    {
        $var = $blueprint->modelVariable();
        $namespace = Str::beforeLast($blueprint->apiControllerClass(), '\\');
        $modelClass = $blueprint->modelClass();
        $resourceClass = $blueprint->resourceClass();
        $requestNamespace = $blueprint->requestNamespace();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use App\\Http\\Controllers\\Controller;
use App\\Http\\Requests\\PaginationRequest;
use {$requestNamespace}\\Store{$blueprint->model}Request;
use {$requestNamespace}\\Update{$blueprint->model}Request;
use {$resourceClass};
use {$modelClass};
use App\\Supports\\Api\\ApiDisplay;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection;
use Illuminate\\Support\\Facades\\Gate;

class {$blueprint->model}ApiController extends Controller
{
    public function index(PaginationRequest \$request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', {$blueprint->model}::class);

        return ApiDisplay::for({$blueprint->model}::class, {$blueprint->model}Resource::class)->paginate(\$request);
    }

    public function store(Store{$blueprint->model}Request \$request): {$blueprint->model}Resource
    {
        Gate::authorize('create', {$blueprint->model}::class);

        \${$var} = {$blueprint->model}::create(\$request->validated());

        return new {$blueprint->model}Resource(\${$var});
    }

    public function show({$blueprint->model} \${$var}): {$blueprint->model}Resource
    {
        Gate::authorize('view', \${$var});

        return new {$blueprint->model}Resource(\${$var});
    }

    public function update(Update{$blueprint->model}Request \$request, {$blueprint->model} \${$var}): {$blueprint->model}Resource
    {
        Gate::authorize('update', \${$var});

        \${$var}->update(\$request->validated());

        return new {$blueprint->model}Resource(\${$var});
    }

    public function destroy({$blueprint->model} \${$var}): JsonResponse
    {
        Gate::authorize('delete', \${$var});

        \${$var}->delete();

        return response()->json(null, 204);
    }
}

PHP;
    }

    private function storeRequest(CrudBlueprint $blueprint): string
    {
        return $this->request($blueprint, false);
    }

    private function updateRequest(CrudBlueprint $blueprint): string
    {
        return $this->request($blueprint, true);
    }

    private function request(CrudBlueprint $blueprint, bool $update): string
    {
        $class = ($update ? 'Update' : 'Store').$blueprint->model.'Request';
        $rules = $this->rules($blueprint, $update);
        $namespace = $blueprint->requestNamespace();
        $modelClass = $blueprint->modelClass();
        $authorization = $update
            ? "\$model = \$this->route('{$blueprint->modelVariable()}');\n\n        return \$model instanceof {$blueprint->model} && (\$this->user()?->can('update', \$model) ?? false);"
            : "return \$this->user()?->can('create', {$blueprint->model}::class) ?? false;";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$modelClass};
use Illuminate\\Foundation\\Http\\FormRequest;
use Illuminate\\Validation\\Rule;

class {$class} extends FormRequest
{
    public function authorize(): bool
    {
        {$authorization}
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return {$rules};
    }
}

PHP;
    }

    private function resource(CrudBlueprint $blueprint): string
    {
        $namespace = Str::beforeLast($blueprint->resourceClass(), '\\');
        $fields = collect($blueprint->fields)
            ->map(fn (CrudField $field): string => "            '{$field->name}' => \$this->{$field->name},")
            ->implode("\n");

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

class {$blueprint->model}Resource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request \$request): array
    {
        return [
            'id' => \$this->id,
            'uuid' => \$this->uuid,
{$fields}
            'created_at' => \$this->created_at,
            'updated_at' => \$this->updated_at,
        ];
    }
}

PHP;
    }

    private function migration(CrudBlueprint $blueprint): string
    {
        $columns = collect($blueprint->fields)
            ->map(fn (CrudField $field): string => '            '.$this->migrationColumn($field))
            ->implode("\n");

        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$blueprint->tableName()}', function (Blueprint \$table): void {
            \$table->bigIncrements('id');
            \$table->uuid('uuid')->unique();
{$columns}
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$blueprint->tableName()}');
    }
};

PHP;
    }

    private function factory(CrudBlueprint $blueprint): string
    {
        $namespace = Str::beforeLast($blueprint->factoryClass(), '\\');
        $modelClass = $blueprint->modelClass();
        $fields = collect($blueprint->fields)
            ->map(fn (CrudField $field): string => "            '{$field->name}' => {$this->fakerValue($field)},")
            ->implode("\n");

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$modelClass};
use Illuminate\\Database\\Eloquent\\Factories\\Factory;
use Illuminate\\Support\\Str;

/**
 * @extends Factory<{$blueprint->model}>
 */
class {$blueprint->model}Factory extends Factory
{
    protected \$model = {$blueprint->model}::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
{$fields}
        ];
    }
}

PHP;
    }

    private function seeder(CrudBlueprint $blueprint): string
    {
        $namespace = Str::beforeLast($blueprint->seederClass(), '\\');
        $modelClass = $blueprint->modelClass();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$modelClass};
use Illuminate\\Database\\Seeder;

class {$blueprint->model}Seeder extends Seeder
{
    public function run(): void
    {
        {$blueprint->model}::factory()
            ->count(10)
            ->create();
    }
}

PHP;
    }

    private function policy(CrudBlueprint $blueprint): string
    {
        $namespace = Str::beforeLast($blueprint->policyClass(), '\\');
        $modelClass = $blueprint->modelClass();
        $resource = $blueprint->routeName();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use App\\Models\\User;
use {$modelClass};
use Illuminate\\Support\\Facades\\Gate;
use Laravel\\Sanctum\\PersonalAccessToken;
use Laravel\\Sanctum\\TransientToken;

class {$blueprint->model}Policy
{
    public function viewAny(User \$user): bool
    {
        return \$this->hasCapability(\$user, 'view-{$resource}');
    }

    public function view(User \$user, {$blueprint->model} \${$blueprint->modelVariable()}): bool
    {
        return \$this->hasCapability(\$user, 'view-{$resource}');
    }

    public function create(User \$user): bool
    {
        return \$this->hasCapability(\$user, 'create-{$resource}');
    }

    public function update(User \$user, {$blueprint->model} \${$blueprint->modelVariable()}): bool
    {
        return \$this->hasCapability(\$user, 'update-{$resource}');
    }

    public function delete(User \$user, {$blueprint->model} \${$blueprint->modelVariable()}): bool
    {
        return \$this->hasCapability(\$user, 'delete-{$resource}');
    }

    private function hasCapability(User \$user, string \$capability): bool
    {
        return Gate::forUser(\$user)->allows(\$capability)
            && \$this->tokenAllows(\$user->currentAccessToken(), \$capability);
    }

    private function tokenAllows(mixed \$token, string \$capability): bool
    {
        return \$token === null || \$token instanceof TransientToken
            || (\$token instanceof PersonalAccessToken && is_array(\$token->abilities)
                && in_array(\$capability, \$token->abilities, true));
    }
}

PHP;
    }

    private function observer(CrudBlueprint $blueprint): string
    {
        $namespace = Str::beforeLast($blueprint->observerClass(), '\\');
        $modelClass = $blueprint->modelClass();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$modelClass};

class {$blueprint->model}Observer
{
    public function created({$blueprint->model} \${$blueprint->modelVariable()}): void
    {
        //
    }

    public function updated({$blueprint->model} \${$blueprint->modelVariable()}): void
    {
        //
    }

    public function deleted({$blueprint->model} \${$blueprint->modelVariable()}): void
    {
        //
    }
}

PHP;
    }

    private function typeScriptType(CrudBlueprint $blueprint): string
    {
        $fields = collect($blueprint->fields)
            ->map(fn (CrudField $field): string => "    {$field->name}: {$field->tsType()}".($field->nullable ? ' | null' : '').';')
            ->implode("\n");

        return <<<TS
export interface {$blueprint->model} {
    id: number;
    uuid: string;
{$fields}
    created_at: string;
    updated_at: string;
}

export interface {$blueprint->model}Resource {
    data: {$blueprint->model};
}

export interface {$blueprint->model}CollectionResource {
    data: {$blueprint->model}[];
    links?: unknown;
    meta?: unknown;
}

TS;
    }

    private function indexPage(CrudBlueprint $blueprint): string
    {
        $model = $blueprint->model;
        $var = $blueprint->modelVariable();
        $route = $blueprint->routeName();
        $apiKey = Str::camel($blueprint->modelPluralVariable());
        $typePath = $blueprint->pageDirectorySingular();
        $columns = $this->tsxColumns($blueprint);
        $filters = $this->tsxFilters($blueprint);
        $modelClass = str_replace('\\', '\\\\', $blueprint->modelClass());
        $actionImport = $blueprint->controllerActionImport();
        $exportConfig = $blueprint->withExport ? "\nconst exportConfig: DataTableExportConfig = {\n    modelClass: '{$modelClass}',\n    columnsEndpoint: apiRoutes.exports.columns,\n};\n" : '';
        $exportImports = $blueprint->withExport ? "    DataTableExportConfig,\n" : '';
        $exportProps = $blueprint->withExport ? "\n                        exportConfig={exportConfig}" : '';
        $exportFeature = $blueprint->withExport ? "\n                            export: true," : '';
        $dateImport = $this->hasDateColumns($blueprint) ? "import { formatDate } from '@/lib/utils';\n" : '';

        $humanPlural = $this->humanPlural($blueprint);
        $humanSingular = $this->humanSingular($blueprint);

        return <<<TSX
import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import { create, destroy, edit, index, show } from '{$actionImport}';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableDateColumn,
{$exportImports}    DataTableFilterConfig,
} from '@/components/data-table';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { apiRoutes } from '@/lib/api/api-routes';
{$dateImport}import type { {$model} } from '@/types/app/{$typePath}';

{$columns}

{$filters}

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];
{$exportConfig}
export default function {$model}Index() {
    setLayoutProps({
        breadcrumbs: [{ title: '{$humanPlural}', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [{$var}ToDelete, set{$model}ToDelete] = useState<{$model} | null>(null);

    const actions = useMemo<DataTableAction<{$model}>[]>(
        () => [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: ({$var}) => show.url({$var}.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },

            {
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: ({$var}) => set{$model}ToDelete({$var}),
            },
        ],
        [],
    );

    function delete{$model}(): void {
        if (!{$var}ToDelete) {
            return;
        }

        router.delete(destroy.url({$var}ToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                set{$model}ToDelete(null);
                void queryClient.invalidateQueries({ queryKey: ['{$route}'] });
            },
        });
    }

    return (
        <>
            <Head title="{$humanPlural}" />

            <Header title="{$humanPlural}" subtitle="Gerencie os registros de {$route}">
                <Button asChild>
                    <Link href={create.url()}>
                        <Plus className="size-4" />
                        Novo {$humanSingular}
                    </Link>
                </Button>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Dialog open={{$var}ToDelete !== null} onOpenChange={(open) => !open && set{$model}ToDelete(null)}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Excluir {$humanSingular}</DialogTitle>
                                <DialogDescription>
                                    Esta ação não pode ser desfeita.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => set{$model}ToDelete(null)}>
                                    <X className="size-4" />
                                    Cancelar
                                </Button>
                                <Button variant="destructive" onClick={delete{$model}}>
                                    <Trash2 className="size-4" />
                                    Excluir
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<{$model}>
                        queryKey="{$route}"
                        tableId="{$route}"
                        endpoint={apiRoutes.{$apiKey}.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={({$var}) => {$var}.uuid}
                        features={{
                            search: true,
                            filters: true,
                            columnVisibility: true,
                            refresh: true,{$exportFeature}
                        }}{$exportProps}
                        filters={filters}
                        dateFilterColumns={dateFilterColumns}
                        actions={actions}
                        actionsConfig={{ display: 'inline', label: 'Ações', buttonSize: 'icon', showTooltips: true, sticky: true }}
                        infiniteScrollConfig={{ fitViewport: true, viewportBottomOffset: 96, minHeight: 320 }}
                        appearance={{ size: 'md', gridLines: 'none', rowStyle: 'soft-striped' }}
                        searchPlaceholder="Buscar {$route}"
                    />
                </div>
            </div>
        </>
    );
}

TSX;
    }

    private function createPage(CrudBlueprint $blueprint): string
    {
        return $this->formPage($blueprint, false);
    }

    private function editPage(CrudBlueprint $blueprint): string
    {
        return $this->formPage($blueprint, true);
    }

    private function formPage(CrudBlueprint $blueprint, bool $isEdit): string
    {
        $model = $blueprint->model;
        $var = $blueprint->modelVariable();
        $typePath = $blueprint->pageDirectorySingular();
        $pageDir = $blueprint->pageDirectory();
        $actionImport = $blueprint->controllerActionImport();
        $humanSingular = $this->humanSingular($blueprint);
        $humanSingularLower = Str::lower($humanSingular);
        $humanPlural = $this->humanPlural($blueprint);
        $displayField = $this->displayField($blueprint);
        $resourceType = Str::lower($humanSingular);

        if ($isEdit) {
            return <<<TSX
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

import { destroy, index, show, update } from '{$actionImport}';
import DeleteButton from '@/components/delete-button';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {$model}FormFields from '@/pages/app/{$pageDir}/partials/{$typePath}-form-fields';
import type { {$model}Resource } from '@/types/app/{$typePath}';

import { {$model}SubNav } from './components/{$typePath}-sub-nav';

interface Props {
    {$var}Data: {$model}Resource;
}

export default function Edit{$model}({ {$var}Data }: Props) {
    const {$var} = {$var}Data.data;

    setLayoutProps({
        breadcrumbs: [
            { title: '{$humanPlural}', href: index.url() },
            { title: String({$var}.{$displayField} ?? {$var}.uuid), href: show.url({$var}.uuid) },
            { title: 'Editar' },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title="Editar {$humanSingular}" />

            <Header title={String({$var}.{$displayField} ?? {$var}.uuid)} backHref={index.url()}>
                <div className="flex items-center gap-2">
                    <{$model}SubNav {$var}Uuid={{$var}.uuid} currentPage="edit" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <DeleteButton
                        url={destroy.url({$var}.uuid)}
                        resourceName={String({$var}.{$displayField} ?? {$var}.uuid)}
                        resourceType="{$resourceType}"
                        redirectTo={index.url()}
                    />
                </div>
            </Header>

            <Form
                action={update.url({$var}.uuid)}
                method="put"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="page-wrapper-md flex-1 pb-8">
                            <section className="grid gap-x-10 gap-y-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                                <div className="md:pt-6">
                                    <h2 className="text-sm font-semibold">Informações do registro</h2>
                                    <p className="mt-1 text-xs text-muted-foreground">Campos principais do cadastro.</p>
                                </div>
                                <Card>
                                    <CardContent>
                                        <{$model}FormFields {$var}={{$var}} errors={errors} />
                                    </CardContent>
                                </Card>
                            </section>
                        </div>

                        {/* Encaixado na base da área de conteúdo: visível mesmo com formulário curto ou longo. */}
                        <div className="sticky bottom-0 z-10 border-t bg-card/95">
                            <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 md:px-6">
                                <p className="hidden text-xs text-muted-foreground sm:block">
                                    Campos marcados com <span className="text-destructive">*</span> são obrigatórios
                                </p>
                                <div className="ml-auto flex items-center gap-2">
                                    <Button type="button" variant="ghost" asChild disabled={processing}>
                                        <Link href={show.url({$var}.uuid)}><X className="size-4" aria-hidden="true" />Cancelar</Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Save />
                                        {processing ? 'Salvando...' : 'Salvar'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}

TSX;
        }

        return <<<TSX
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';

import { create, index, store } from '{$actionImport}';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {$model}FormFields from '@/pages/app/{$pageDir}/partials/{$typePath}-form-fields';

export default function Create{$model}() {
    setLayoutProps({
        breadcrumbs: [
            { title: '{$humanPlural}', href: index.url() },
            { title: 'Novo {$humanSingular}', href: create.url() },
        ],
    });

    return (
        <div className="flex min-h-full flex-col">
            <Head title="Novo {$humanSingular}" />

            <Header title="Novo {$humanSingular}" subtitle="Cadastrar novo {$humanSingularLower}" backHref={index.url()} />

            <Form
                action={store.url()}
                method="post"
                options={{ preserveScroll: true }}
                onError={() => toast.error('Verifique os campos obrigatórios.')}
                className="flex flex-1 flex-col"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="page-wrapper-md flex-1 pb-8">
                            <section className="grid gap-x-10 gap-y-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                                <div className="md:pt-6">
                                    <h2 className="text-sm font-semibold">Informações do registro</h2>
                                    <p className="mt-1 text-xs text-muted-foreground">Campos principais do cadastro.</p>
                                </div>
                                <Card>
                                    <CardContent>
                                        <{$model}FormFields errors={errors} />
                                    </CardContent>
                                </Card>
                            </section>
                        </div>

                        {/* Encaixado na base da área de conteúdo: visível mesmo com formulário curto ou longo. */}
                        <div className="sticky bottom-0 z-10 border-t bg-card/95">
                            <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 md:px-6">
                                <p className="hidden text-xs text-muted-foreground sm:block">
                                    Campos marcados com <span className="text-destructive">*</span> são obrigatórios
                                </p>
                                <div className="ml-auto flex items-center gap-2">
                                    <Button type="button" variant="ghost" asChild disabled={processing}>
                                        <Link href={index.url()}><X className="size-4" aria-hidden="true" />Cancelar</Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Save />
                                        {processing ? 'Salvando...' : 'Salvar'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}

TSX;
    }

    private function showPage(CrudBlueprint $blueprint): string
    {
        $model = $blueprint->model;
        $var = $blueprint->modelVariable();
        $typePath = $blueprint->pageDirectorySingular();
        $actionImport = $blueprint->controllerActionImport();
        $displayField = $this->displayField($blueprint);
        $resourceType = Str::lower($this->humanSingular($blueprint));
        $humanPlural = $this->humanPlural($blueprint);
        $humanSingular = $this->humanSingular($blueprint);
        $fields = collect($blueprint->fields)
            ->map(fn (CrudField $field): string => "                                    <InfoField label=\"{$field->label()}\" value={String({$var}.{$field->name} ?? '-')} />")
            ->implode("\n");

        return <<<TSX
import { Head, setLayoutProps } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';

import { destroy, index, show } from '{$actionImport}';
import DeleteButton from '@/components/delete-button';
import Header from '@/components/dashboard/header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatDate } from '@/lib/utils';
import type { {$model}Resource } from '@/types/app/{$typePath}';

import { {$model}SubNav } from './components/{$typePath}-sub-nav';

interface Props {
    {$var}Data: {$model}Resource;
}

function InfoField({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-sm">{value || '-'}</p>
        </div>
    );
}

export default function Show{$model}({ {$var}Data }: Props) {
    const {$var} = {$var}Data.data;

    setLayoutProps({
        breadcrumbs: [
            { title: '{$humanPlural}', href: index.url() },
            { title: String({$var}.{$displayField} ?? {$var}.uuid), href: show.url({$var}.uuid) },
        ],
    });

    const initials = String({$var}.{$displayField} ?? {$var}.uuid)
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

    return (
        <>
            <Head title={String({$var}.{$displayField} ?? {$var}.uuid)} />

            <Header title={String({$var}.{$displayField} ?? {$var}.uuid)} backHref={index.url()}>
                <div className="flex items-center gap-2">
                    <{$model}SubNav {$var}Uuid={{$var}.uuid} currentPage="show" />
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <DeleteButton
                        url={destroy.url({$var}.uuid)}
                        resourceName={String({$var}.{$displayField} ?? {$var}.uuid)}
                        resourceType="{$resourceType}"
                        redirectTo={index.url()}
                    />
                </div>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="grid gap-6 md:grid-cols-3">
                        <aside className="space-y-4 md:col-span-1">
                            <Card>
                                <CardContent className="flex flex-col items-center gap-4 p-6">
                                    <div className="flex size-20 items-center justify-center rounded-2xl bg-primary-soft text-2xl font-bold text-primary-soft-foreground">
                                        {initials}
                                    </div>
                                    <div className="text-center">
                                        <h2 className="text-lg font-semibold">{String({$var}.{$displayField} ?? {$var}.uuid)}</h2>
                                    </div>

                                    <Separator className="w-full" />

                                    <div className="w-full space-y-1 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-2">
                                            <CalendarDays className="size-3.5" />
                                            <span>Criado {formatDate({$var}.created_at)}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </aside>

                        <main className="space-y-6 md:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Informações do registro</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
{$fields}
                                    <InfoField label="Criado em" value={formatDate({$var}.created_at)} />
                                    <InfoField label="Atualizado em" value={formatDate({$var}.updated_at)} />
                                </CardContent>
                            </Card>
                        </main>
                    </div>
                </div>
            </div>
        </>
    );
}

TSX;
    }

    private function subNavComponent(CrudBlueprint $blueprint): string
    {
        $model = $blueprint->model;
        $var = $blueprint->modelVariable();
        $typePath = $blueprint->pageDirectorySingular();
        $actionImport = $blueprint->controllerActionImport();

        return <<<TSX
import { Link } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';

import { edit, show } from '{$actionImport}';
import { cn } from '@/lib/utils';

type {$model}Page = 'show' | 'edit';

interface {$model}SubNavProps {
    {$var}Uuid: string;
    currentPage: {$model}Page;
}

const navItems: {
    key: {$model}Page;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    getHref: (uuid: string) => string;
}[] = [
    {
        key: 'show',
        label: 'Detalhes',
        icon: Eye,
        getHref: (uuid) => show.url(uuid),
    },
    {
        key: 'edit',
        label: 'Editar',
        icon: Pencil,
        getHref: (uuid) => edit.url(uuid),
    },
];

export function {$model}SubNav({ {$var}Uuid, currentPage }: {$model}SubNavProps) {
    return (
        <nav className="flex gap-1 rounded-lg border border-border bg-muted p-1">
            {navItems.map(({ key, label, icon: Icon, getHref }) => {
                const isActive = key === currentPage;

                return (
                    <Link
                        key={key}
                        href={getHref({$var}Uuid)}
                        className={cn(
                            'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-card text-foreground shadow-xs'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )}
                    >
                        <Icon className="size-3.5" />
                        <span className="hidden sm:inline">{label}</span>
                    </Link>
                );
            })}
        </nav>
    );
}

TSX;
    }

    private function formFieldsPage(CrudBlueprint $blueprint): string
    {
        $model = $blueprint->model;
        $typePath = $blueprint->pageDirectorySingular();
        $var = $blueprint->modelVariable();
        $fields = collect($blueprint->fields)
            ->map(function (CrudField $field): string {
                $fieldId = "field-{$field->name}";
                $required = $field->nullable ? '' : "\n                required";

                if ($field->type === 'boolean') {
                    return <<<TSX
            <FormField
                id="{$fieldId}"
                label="{$field->label()}"
                variant="stacked"{$required}
                error={errors.{$field->name}}
            >
                <div className="flex items-center gap-2">
                    <input type="hidden" name="{$field->name}" value="0" />
                    <input
                        id="{$fieldId}"
                        name="{$field->name}"
                        type="checkbox"
                        defaultChecked={Boolean(record?.{$field->name} ?? false)}
                        value="1"
                        aria-invalid={!!errors.{$field->name}}
                        className="size-4 rounded-[4px] border border-input shadow-xs"
                    />
                </div>
            </FormField>
TSX;
                }

                return <<<TSX
            <FormField
                id="{$fieldId}"
                label="{$field->label()}"
                variant="stacked"{$required}
                error={errors.{$field->name}}
            >
                <Input
                    id="{$fieldId}"
                    name="{$field->name}"
                    type="{$field->inputType()}"
                    defaultValue={record?.{$field->name} ?? ''}{$required}
                    aria-invalid={!!errors.{$field->name}}
                />
            </FormField>
TSX;
            })
            ->implode("\n\n");

        return <<<TSX
import FormField from '@/components/ui/forms/form-field';
import { Input } from '@/components/ui/input';
import type { {$model} } from '@/types/app/{$typePath}';

interface Props {
    {$var}?: Partial<{$model}>;
    errors: Record<string, string>;
}

export default function {$model}FormFields({ {$var}, errors }: Props) {
    const record = {$var};

    return (
        <div className="grid gap-4 sm:grid-cols-2">
{$fields}
        </div>
    );
}

TSX;
    }

    private function featureTest(CrudBlueprint $blueprint): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\\Feature\\Crud;

use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

/**
 * Add permitted-flow cases after explicitly integrating this domain's policy,
 * functional capability catalog, and grants. Generation assigns no authority.
 */
class {$blueprint->model}CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_user_cannot_view_{$blueprint->modelVariable()}_index(): void
    {
        \$user = User::factory()->create();

        \$this->actingAs(\$user)
            ->get(route('{$blueprint->routeName()}.index'))
            ->assertForbidden();
    }

    public function test_api_denies_{$blueprint->modelVariable()}_records_without_domain_authority(): void
    {
        \$user = User::factory()->create();

        \$this->actingAs(\$user)
            ->getJson('/api/v1/{$blueprint->routeName()}')
            ->assertForbidden();
    }
}

PHP;
    }

    private function migrationName(CrudBlueprint $blueprint): string
    {
        return now()->format('Y_m_d_His')."_create_{$blueprint->tableName()}_table.php";
    }

    private function migrationColumn(CrudField $field): string
    {
        $column = match ($field->type) {
            'text' => "\$table->text('{$field->name}')",
            'integer' => "\$table->integer('{$field->name}')",
            'bigInteger' => "\$table->bigInteger('{$field->name}')",
            'decimal' => "\$table->decimal('{$field->name}', 12, 2)",
            'float' => "\$table->float('{$field->name}')",
            'double' => "\$table->double('{$field->name}')",
            'boolean' => "\$table->boolean('{$field->name}')->default(false)",
            'date' => "\$table->date('{$field->name}')",
            'dateTime' => "\$table->dateTime('{$field->name}')",
            'timestamp' => "\$table->timestamp('{$field->name}')",
            'json' => "\$table->json('{$field->name}')",
            default => "\$table->string('{$field->name}')",
        };

        if ($field->nullable && $field->type !== 'boolean') {
            $column .= '->nullable()';
        }

        if ($field->unique) {
            $column .= '->unique()';
        }

        return $column.';';
    }

    private function rules(CrudBlueprint $blueprint, bool $update): string
    {
        $lines = collect($blueprint->fields)
            ->map(function (CrudField $field) use ($blueprint, $update): string {
                $rules = [$field->nullable ? "'nullable'" : "'required'"];
                $rules[] = match ($field->type) {
                    'integer', 'bigInteger' => "'integer'",
                    'decimal', 'float', 'double' => "'numeric'",
                    'boolean' => "'boolean'",
                    'date', 'dateTime', 'timestamp' => "'date'",
                    'json' => "'array'",
                    default => "'string'",
                };

                if (in_array($field->type, ['string', 'text'], true)) {
                    $rules[] = "'max:255'";
                }

                foreach ($field->validation as $rule) {
                    $rules[] = var_export($rule, true);
                }

                if ($field->unique) {
                    $ignore = $update ? "->ignore(\$this->route('{$blueprint->modelVariable()}'))" : '';
                    $rules[] = "Rule::unique('{$blueprint->tableName()}', '{$field->name}'){$ignore}";
                }

                return "            '{$field->name}' => [".implode(', ', $rules).'],';
            })
            ->implode("\n");

        return "[\n{$lines}\n        ]";
    }

    private function casts(CrudBlueprint $blueprint): string
    {
        $casts = [
            "            'created_at' => 'datetime',",
            "            'updated_at' => 'datetime',",
        ];

        foreach ($blueprint->fields as $field) {
            $cast = match ($field->type) {
                'integer', 'bigInteger' => 'integer',
                'decimal', 'float', 'double' => 'decimal:2',
                'boolean' => 'boolean',
                'date' => 'date',
                'dateTime', 'timestamp' => 'datetime',
                'json' => 'array',
                default => null,
            };

            if ($cast) {
                array_unshift($casts, "            '{$field->name}' => '{$cast}',");
            }
        }

        return "[\n".implode("\n", $casts)."\n        ]";
    }

    private function tsxColumns(CrudBlueprint $blueprint): string
    {
        $columns = collect($blueprint->fields)
            ->map(function (CrudField $field): string {
                $cell = in_array($field->type, ['date', 'dateTime', 'timestamp'], true)
                    ? "\n        cell: ({ row }) => formatDate(row.original.{$field->name}),"
                    : '';

                return <<<TSX
    {
        id: '{$field->name}',
        accessorKey: '{$field->name}',
        header: '{$field->label()}',{$cell}
        meta: { label: '{$field->label()}' },
    },
TSX;
            })
            ->implode("\n");

        return "const columns: ColumnDef<{$blueprint->model}>[] = [\n{$columns}\n];";
    }

    private function tsxFilters(CrudBlueprint $blueprint): string
    {
        $filters = collect($blueprint->fields)
            ->filter(fn (CrudField $field): bool => $field->filterable || $field->searchable)
            ->map(function (CrudField $field): string {
                $type = match ($field->type) {
                    'integer', 'bigInteger', 'decimal', 'float', 'double' => 'number',
                    'boolean' => 'boolean',
                    'date', 'dateTime', 'timestamp' => 'date',
                    default => 'text',
                };

                return <<<TSX
    {
        id: '{$field->name}',
        label: '{$field->label()}',
        type: '{$type}',
        operator: '{$this->filterOperator($type)}',
    },
TSX;
            })
            ->implode("\n");

        return "const filters: DataTableFilterConfig[] = [\n{$filters}\n];";
    }

    private function filterOperator(string $type): string
    {
        return match ($type) {
            'number', 'boolean', 'date' => 'eq',
            default => 'contains',
        };
    }

    private function fakerValue(CrudField $field): string
    {
        return match ($field->type) {
            'integer', 'bigInteger' => 'fake()->numberBetween(1, 1000)',
            'decimal', 'float', 'double' => 'fake()->randomFloat(2, 1, 1000)',
            'boolean' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'dateTime', 'timestamp' => 'fake()->dateTime()',
            'json' => "['sample' => fake()->word()]",
            default => $field->unique ? 'fake()->unique()->word()' : 'fake()->words(2, true)',
        };
    }

    /**
     * @param  array<int, string>  $items
     */
    private function phpArray(array $items): string
    {
        return '['.implode(', ', array_map(fn (string $item): string => var_export($item, true), $items)).']';
    }

    /**
     * @param  array<int, CrudField>  $fields
     */
    private function phpAssocArray(array $fields): string
    {
        if ($fields === []) {
            return '[]';
        }

        $lines = collect($fields)
            ->map(fn (CrudField $field): string => '        '.var_export($field->name, true).' => '.var_export($field->label(), true).',')
            ->implode("\n");

        return "[\n{$lines}\n    ]";
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function implodeLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    private function humanPlural(CrudBlueprint $blueprint): string
    {
        return Str::headline(Str::plural($blueprint->model));
    }

    private function humanSingular(CrudBlueprint $blueprint): string
    {
        return Str::headline($blueprint->model);
    }

    private function displayField(CrudBlueprint $blueprint): string
    {
        return collect($blueprint->fields)
            ->first(fn (CrudField $field): bool => $field->name === 'name')
            ->name ?? 'uuid';
    }

    private function hasDateColumns(CrudBlueprint $blueprint): bool
    {
        return collect($blueprint->fields)
            ->contains(fn (CrudField $field): bool => in_array($field->type, ['date', 'dateTime', 'timestamp'], true));
    }
}
