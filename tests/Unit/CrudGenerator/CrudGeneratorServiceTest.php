<?php

declare(strict_types=1);

namespace Tests\Unit\CrudGenerator;

use App\Modules\CrudGenerator\DTOs\CrudBlueprint;
use App\Modules\CrudGenerator\DTOs\CrudField;
use App\Modules\CrudGenerator\Services\CrudGeneratorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CrudGeneratorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        $this->freezeTime();

        foreach ([
            'config/navigation.php' => "<?php\nreturn [\n    'prefixes' => [\n                '/exportacoes',\n    ],\n    'items' => [\n                [\n                    'slug' => 'exportacoes',\n                ],\n    ],\n];\n",
            'config/exports.php' => "<?php\n\nuse App\\Models\\User;\n\nreturn ['allowed_models' => [\n        User::class,\n]];\n",
            'resources/js/lib/api/api-routes.ts' => "export const apiRoutes = {\n    exports: {\n    },\n};\n",
            'routes/web.php' => "<?php\n\nuse App\\Http\\Controllers\\AppController;\n\nRoute::middleware('auth')->group(function () {\n    Route::get('dashboard', [AppController::class, 'dashboard'])->name('app.dashboard');\n});\n",
            'routes/api/api.php' => "<?php\n\nuse App\\Http\\Controllers\\Api\\UserApiController;\n\nRoute::middleware('auth:sanctum')->group(function () {\n        Route::apiResource('users', UserApiController::class)->only('index');\n});\n",
            'app/Providers/AppServiceProvider.php' => "<?php\n\nnamespace App\\Providers;\n\nuse App\\Listeners\\LogUserLogin;\n\nclass AppServiceProvider\n{\n    private function registerObservers(): void {}\n}\n",
        ] as $path => $contents) {
            File::ensureDirectoryExists(dirname(base_path($path)));
            File::put(base_path($path), $contents);
        }
    }

    private function blueprint(string $target = 'module'): CrudBlueprint
    {
        return new CrudBlueprint(
            model: 'SafeClient', module: 'SafeClients', fields: [new CrudField(name: 'name')],
            withExport: true, withObserver: true, target: $target,
        );
    }

    /** @return array<string, string> */
    private function projectSnapshot(): array
    {
        $snapshot = [];

        foreach (File::allFiles(base_path(), true) as $file) {
            if ($file->getRelativePathname() === '.start-kit-generator.lock') {
                continue;
            }

            $snapshot[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }

        ksort($snapshot);

        return $snapshot;
    }

    #[DataProvider('generationTargets')]
    public function test_apply_and_force_retry_only_write_owned_generation(string $target): void
    {
        $blueprint = $this->blueprint($target);
        $generator = app(CrudGeneratorService::class);
        $result = $generator->apply($blueprint);

        foreach ($result->files as $file) {
            $this->assertFileExists($file->path);

            if (str_ends_with($file->path, '.php')) {
                $this->assertNotEmpty(token_get_all(File::get($file->path), TOKEN_PARSE));
                [$exitCode, $output] = $this->lintOwnedPhp($file->path);
                $this->assertSame(0, $exitCode, $output);
            }
        }

        $before = $this->projectSnapshot();
        $this->travel(2)->seconds();
        $generator->apply($blueprint, true);
        $this->assertSame($before, $this->projectSnapshot());
    }

    public static function generationTargets(): array
    {
        return [['module'], ['app']];
    }

    /** @return array<string, array{string, string, string}> */
    public static function protectedIdentifiers(): array
    {
        return [
            'user custom module preview' => ['User', 'module', 'preview'],
            'plural user app apply' => ['Users', 'app', 'apply'],
            'case variant force' => ['uSeR', 'module', 'force'],
            'role custom module' => ['Role', 'module', 'apply'],
            'permission app' => ['Permission', 'app', 'preview'],
            'client custom module' => ['Client', 'module', 'force'],
            'activity model' => ['Activity', 'module', 'preview'],
            'activities alias' => ['Activities', 'app', 'apply'],
            'activity log capability alias' => ['ActivityLog', 'module', 'force'],
            'personal access tokens' => ['PersonalAccessToken', 'module', 'apply'],
            'user role pivot' => ['UsersRole', 'module', 'preview'],
            'user permission pivot' => ['UsersPermission', 'app', 'force'],
            'role permission pivot' => ['RolesPermission', 'module', 'apply'],
        ];
    }

    #[DataProvider('protectedIdentifiers')]
    public function test_protected_model_route_and_table_identifiers_are_refused_before_planning_or_writing(string $model, string $target, string $operation): void
    {
        $blueprint = new CrudBlueprint(
            model: $model, module: 'IsolatedAuthorityLookalikes', fields: [new CrudField(name: 'name')],
            withExport: false, withObserver: false, withPolicy: false, withFactory: false,
            withSeeder: false, withTests: false, registerProvider: false, target: $target,
        );
        $before = $this->projectSnapshot();
        $generator = app(CrudGeneratorService::class);

        try {
            if ($operation === 'preview') {
                $generator->preview($blueprint);
            } else {
                $generator->apply($blueprint, $operation === 'force');
            }
            $this->fail('Protected identifiers must be refused even for a different module and disabled policy/provider options.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('model', $exception->errors());
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    /** @return array<string, array{string}> */
    public static function protectedTableSettings(): array
    {
        return ['configured role table' => ['permissions.tables.roles'], 'configured pivot table' => ['permissions.tables.users_roles'], 'configured activity table' => ['activitylog.table_name']];
    }

    #[DataProvider('protectedTableSettings')]
    public function test_configured_protected_table_is_refused_without_schema_discovery(string $setting): void
    {
        $blueprint = $this->blueprint();
        $blueprint->model = 'ConfiguredAuthorityRecord';
        config([$setting => $blueprint->tableName()]);
        $before = $this->projectSnapshot();

        try {
            app(CrudGeneratorService::class)->apply($blueprint, true);
            $this->fail('The configured protected table must not be scaffolded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('model', $exception->errors());
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    #[DataProvider('generationTargets')]
    public function test_generated_regression_tests_assert_denial_before_domain_authority_is_integrated(string $target): void
    {
        $blueprint = $this->blueprint($target);
        $result = app(CrudGeneratorService::class)->preview($blueprint);
        $path = base_path('tests/Feature/Crud/SafeClientCrudTest.php');
        $generated = collect($result->files)->first(fn ($file): bool => $file->path === $path);
        $this->assertNotNull($generated);
        $this->assertStringContainsString('->assertForbidden()', $generated->content);
        $this->assertStringNotContainsString('->assertOk()', $generated->content);
        $this->assertFileDoesNotExist($path);
    }

    public function test_generated_pages_follow_the_visual_contract(): void
    {
        $source = collect(app(CrudGeneratorService::class)->preview($this->blueprint())->files)
            ->filter(fn ($file): bool => str_ends_with($file->path, '.tsx'))
            ->map(fn ($file): string => $file->content)
            ->implode("\n");

        // Header (dashboard/header) é um invólucro do PageHeader: os dois seguem o contrato.
        $this->assertMatchesRegularExpression('~<(?:PageHeader|Header title=)~', $source);
        $this->assertStringNotContainsString("from '@/components/heading'", $source);
        $this->assertDoesNotMatchRegularExpression('~>\s*\w+\.uuid\s*<~', $source, 'UUID must be rendered, not printed as text');
        $this->assertDoesNotMatchRegularExpression('~bg-primary/10|bg-muted/30|bg-background text-foreground shadow-sm|Visão Geral|Informações de~', $source);
    }

    /** @return array{int, string} */
    private function lintOwnedPhp(string $path): array
    {
        $this->assertStringStartsWith(realpath(base_path()).DIRECTORY_SEPARATOR, realpath($path));
        $process = new Process([PHP_BINARY, '-n', '-l', $path], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'PHPRC' => false, 'PHP_INI_SCAN_DIR' => false,
        ], timeout: 10);
        $exitCode = $process->run();

        return [$exitCode, $process->getOutput().$process->getErrorOutput()];
    }

    #[DataProvider('phpSymbolCollisions')]
    public function test_generation_refuses_conflicting_php_imports_and_declarations_before_writing(string $model, string $module, string $lintPath): void
    {
        $blueprint = $this->blueprint();
        $blueprint->model = $model;
        $blueprint->module = $module;
        $before = $this->projectSnapshot();
        $diagnostics = '';
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($blueprint);
            [$exitCode, $output] = $this->lintOwnedPhp(base_path($lintPath));
            $diagnostics = 'Isolated PHP lint exit '.$exitCode.': '.$output;
            $this->fail('Generation accepted conflicting PHP symbols; isolated lint exit '.$exitCode.': '.$output);
        } finally {
            $this->assertSame($before, $this->projectSnapshot(), $diagnostics);
        }
    }

    public static function phpSymbolCollisions(): array
    {
        return [
            ['AliasClient', 'App', 'bootstrap/providers.php'],
            ['AliasClient', 'Fortify', 'bootstrap/providers.php'],
            ['Model', 'AliasModels', 'app/Modules/AliasModels/Models/Model.php'],
        ];
    }

    public function test_interpolation_in_central_file_cannot_hide_conflicting_php_imports(): void
    {
        $path = base_path('bootstrap/providers.php');
        $contents = preg_replace_callback('/\A<\?php\R/', fn (array $match): string => $match[0]."\$x = 'fixture';\n\$ignored = \"{\$x}\";\n", File::get($path));
        File::put($path, $contents);
        $blueprint = $this->blueprint();
        $blueprint->model = 'InterpolatedClient';
        $blueprint->module = 'App';
        $before = $this->projectSnapshot();
        $diagnostics = '';
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($blueprint);
            [$exitCode, $output] = $this->lintOwnedPhp($path);
            $diagnostics = 'Isolated PHP lint exit '.$exitCode.': '.$output;
            $this->fail('Interpolation must not conceal conflicting PHP imports. '.$diagnostics);
        } finally {
            $this->assertSame($before, $this->projectSnapshot(), $diagnostics);
        }
    }

    #[DataProvider('similarCentralRegistrations')]
    public function test_similar_class_name_cannot_mask_exact_central_registration(string $kind): void
    {
        $blueprint = $this->blueprint();
        $blueprint->model = 'Foo';
        $blueprint->module = 'Foo';
        $path = $kind === 'provider' ? base_path('bootstrap/providers.php') : config_path('exports.php');
        $anchor = $kind === 'provider' ? '    NotificationsServiceProvider::class,' : '        User::class,';
        $other = $kind === 'provider' ? '    OtherFooServiceProvider::class,' : '        OtherFoo::class,';
        File::put($path, str_replace($anchor, $other."\n".$anchor, File::get($path)));
        $generator = app(CrudGeneratorService::class);
        $generator->apply($blueprint);
        $registered = require $path;
        $this->assertContains(
            $kind === 'provider' ? 'App\\Modules\\Foo\\Providers\\FooServiceProvider' : 'App\\Modules\\Foo\\Models\\Foo',
            $kind === 'provider' ? $registered : $registered['allowed_models'],
        );
        $before = $this->projectSnapshot();
        $generator->apply($blueprint, true);
        $this->assertSame($before, $this->projectSnapshot());
    }

    public static function similarCentralRegistrations(): array
    {
        return [['provider'], ['export']];
    }

    #[DataProvider('similarCentralRegistrations')]
    public function test_comment_cannot_masquerade_as_real_php_import(string $kind): void
    {
        $blueprint = $this->blueprint();
        $blueprint->model = 'CommentClient';
        $blueprint->module = 'CommentClients';
        $class = $kind === 'provider'
            ? 'App\\Modules\\CommentClients\\Providers\\CommentClientsServiceProvider'
            : 'App\\Modules\\CommentClients\\Models\\CommentClient';
        $path = $kind === 'provider' ? base_path('bootstrap/providers.php') : config_path('exports.php');
        $contents = preg_replace_callback('/\A<\?php\R/', fn (array $match): string => $match[0].'/* use '.$class.'; */'."\n", File::get($path));
        File::put($path, $contents);
        $generator = app(CrudGeneratorService::class);
        $generator->apply($blueprint);
        $registered = require $path;
        $this->assertContains($class, $kind === 'provider' ? $registered : $registered['allowed_models']);
        $before = $this->projectSnapshot();
        $generator->apply($blueprint, true);
        $this->assertSame($before, $this->projectSnapshot());
    }

    #[DataProvider('nonCodeClassEntries')]
    public function test_non_code_text_cannot_masquerade_as_real_class_registration(string $kind, string $format): void
    {
        $blueprint = $this->blueprint();
        $blueprint->model = 'EntryClient';
        $blueprint->module = 'EntryClients';
        $class = $kind === 'provider'
            ? 'App\\Modules\\EntryClients\\Providers\\EntryClientsServiceProvider'
            : 'App\\Modules\\EntryClients\\Models\\EntryClient';
        $short = $kind === 'provider' ? 'EntryClientsServiceProvider' : 'EntryClient';
        $path = $kind === 'provider' ? base_path('bootstrap/providers.php') : config_path('exports.php');
        $nonCode = $format === 'comment'
            ? "/*\n    {$short}::class,\n*/\n"
            : "\$ignored = <<<'REGISTRATION'\n    {$short}::class,\nREGISTRATION;\n";
        $contents = preg_replace_callback('/\A<\?php\R/', fn (array $match): string => $match[0].'use '.$class.';'."\n".$nonCode, File::get($path));
        File::put($path, $contents);
        $generator = app(CrudGeneratorService::class);
        $generator->apply($blueprint);
        $registered = require $path;
        $this->assertContains($class, $kind === 'provider' ? $registered : $registered['allowed_models']);
        $before = $this->projectSnapshot();
        $generator->apply($blueprint, true);
        $this->assertSame($before, $this->projectSnapshot());
    }

    public static function nonCodeClassEntries(): array
    {
        return [['provider', 'comment'], ['provider', 'heredoc'], ['export', 'comment'], ['export', 'heredoc']];
    }

    #[DataProvider('similarCentralRegistrations')]
    public function test_multiple_namespaces_cannot_mask_registration_import_scope(string $kind): void
    {
        $blueprint = $this->blueprint();
        $blueprint->model = 'ScopedClient';
        $blueprint->module = 'ScopedClients';
        $class = $kind === 'provider'
            ? 'App\\Modules\\ScopedClients\\Providers\\ScopedClientsServiceProvider'
            : 'App\\Modules\\ScopedClients\\Models\\ScopedClient';
        $path = $kind === 'provider' ? base_path('bootstrap/providers.php') : config_path('exports.php');
        $body = preg_replace('/\A<\?php\R/', '', File::get($path));
        File::put($path, "<?php\nnamespace Dummy {\nuse {$class};\n}\nnamespace {\n{$body}\n}\n");
        $before = $this->projectSnapshot();
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($blueprint);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    public function test_generation_ownership_remains_valid_when_project_is_copied(): void
    {
        $blueprint = $this->blueprint();
        app(CrudGeneratorService::class)->apply($blueprint);
        $before = $this->projectSnapshot();
        $copy = $this->testEnvironment->path.'/copied-project';
        File::copyDirectory(base_path(), $copy);
        $this->app->setBasePath($copy);
        $this->app->useAppPath($copy.'/app');
        $this->app->useConfigPath($copy.'/config');
        $this->app->useDatabasePath($copy.'/database');
        $this->travel(2)->seconds();
        app(CrudGeneratorService::class)->apply($blueprint, true);
        $this->assertSame($before, $this->projectSnapshot());
    }

    #[DataProvider('generationTargets')]
    public function test_conflict_without_force_preserves_all_files(string $target): void
    {
        $generator = app(CrudGeneratorService::class);
        $blueprint = $this->blueprint($target);
        $generator->apply($blueprint);
        $before = $this->projectSnapshot();
        $this->expectException(RuntimeException::class);

        try {
            $generator->apply($blueprint);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    #[DataProvider('unownedTargets')]
    public function test_force_cannot_overwrite_unowned_sources(string $target, string $model, string $module, string $path): void
    {
        $blueprint = $this->blueprint($target);
        $blueprint->model = $model;
        $blueprint->module = $module;
        File::ensureDirectoryExists(dirname(base_path($path)));
        File::put(base_path($path), "<?php\n// Existing application source, not generator-owned.\n");
        $before = $this->projectSnapshot();
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($blueprint, true);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    public static function unownedTargets(): array
    {
        return [
            ['app', 'User', 'Users', 'app/Models/User.php'],
            ['module', 'CrudGenerator', 'CrudGenerator', 'app/Modules/CrudGenerator/routes.php'],
            ['module', 'SafeClient', 'SafeClients', 'app/Modules/SafeClients/Models/SafeClient.php'],
        ];
    }

    #[DataProvider('caseCollisions')]
    public function test_generation_refuses_case_collisions_with_existing_source_identity(string $target, string $model, string $module, string $existingPath): void
    {
        File::ensureDirectoryExists(dirname(base_path($existingPath)));
        File::put(base_path($existingPath), "<?php\n// Existing source identity must remain unique.\n");
        $blueprint = $this->blueprint($target);
        $blueprint->model = $model;
        $blueprint->module = $module;
        $before = $this->projectSnapshot();
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($blueprint, true);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    public static function caseCollisions(): array
    {
        return [
            ['app', 'USER', 'Users', 'app/Models/User.php'],
            ['module', 'CaseClient', 'Crudgenerator', 'app/Modules/CrudGenerator/source-sentinel.php'],
        ];
    }

    #[DataProvider('invalidBlueprintNames')]
    public function test_direct_blueprint_cannot_escape_generation_destinations(string $property, string $value): void
    {
        $blueprint = $this->blueprint();
        $blueprint->{$property} = $value;
        $before = $this->projectSnapshot();
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($blueprint, true);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    public static function invalidBlueprintNames(): array
    {
        return [
            ['model', '../Escape'], ['module', '../Escape'], ['module', 'Nested/Module'],
            ['model', 'C:\\Escape'], ['model', "Client\n"], ['module', "Clients\r\n"],
            ['target', 'unknown'],
        ];
    }

    #[DataProvider('reservedModelNames')]
    public function test_reserved_model_name_is_refused_without_any_generation(string $model): void
    {
        $blueprint = $this->blueprint();
        $blueprint->model = $model;
        $before = $this->projectSnapshot();
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($blueprint, true);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    public static function reservedModelNames(): array
    {
        return [
            ['parent'], ['Self'], ['INT'], ['Float'], ['bool'], ['String'], ['TRUE'], ['False'],
            ['Null'], ['Void'], ['Iterable'], ['Object'], ['Mixed'], ['Never'], ['Static'],
        ];
    }

    public function test_reserved_type_word_remains_valid_as_namespace_module_component(): void
    {
        $blueprint = $this->blueprint();
        $blueprint->module = 'String';
        $result = app(CrudGeneratorService::class)->apply($blueprint);

        foreach ($result->files as $file) {
            if (str_ends_with($file->path, '.php')) {
                [$exitCode, $output] = $this->lintOwnedPhp($file->path);
                $this->assertSame(0, $exitCode, $output);
            }
        }

        $this->assertFileExists(app_path('Modules/String/Models/SafeClient.php'));
    }

    #[DataProvider('invalidFieldDefinitions')]
    public function test_direct_fields_cannot_inject_generated_code(CrudField $field): void
    {
        $blueprint = $this->blueprint();
        $blueprint->fields = [$field];
        $before = $this->projectSnapshot();
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($blueprint, true);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    public static function invalidFieldDefinitions(): array
    {
        return [
            [new CrudField(name: "name\n")],
            [new CrudField(name: 'name', type: 'unsupported')],
            [new CrudField(name: 'name', validation: [123])],
        ];
    }

    #[DataProvider('unsafeServiceEnvironments')]
    public function test_service_refuses_forbidden_or_divergent_environment(string $environment, string $configured): void
    {
        $this->app->instance('env', $environment);
        config(['app.env' => $configured]);
        $before = $this->projectSnapshot();
        $this->expectException(AuthorizationException::class);

        try {
            app(CrudGeneratorService::class)->apply($this->blueprint(), true);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
            $this->assertFileDoesNotExist(base_path('.start-kit-generator.lock'));
        }
    }

    public static function unsafeServiceEnvironments(): array
    {
        return [['production', 'production'], ['testing', 'local'], ['staging', 'staging']];
    }

    #[DataProvider('redirectedPaths')]
    public function test_all_paths_are_preflighted_before_any_generation(string $path): void
    {
        $before = $this->projectSnapshot();
        $outside = $this->testEnvironment->path.'/outside';
        File::ensureDirectoryExists($outside);

        match ($path) {
            'app' => $this->app->useAppPath($outside),
            'config' => $this->app->useConfigPath($outside),
            'database' => $this->app->useDatabasePath($outside),
        };
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($this->blueprint('app'), true);
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
            $this->assertSame([], File::allFiles($outside));
        }
    }

    public static function redirectedPaths(): array
    {
        return [['app'], ['config'], ['database']];
    }

    #[DataProvider('symlinkDestinations')]
    public function test_force_refuses_symlinks_without_following_or_removing_them(string $kind): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Native symlink protection requires the isolated Linux runner.');
        }

        $outside = $this->testEnvironment->path.'/outside';
        File::ensureDirectoryExists($outside);
        File::put($outside.'/sentinel', 'outside unchanged');
        $link = match ($kind) {
            'directory' => app_path('Modules/SafeClients'),
            'central' => config_path('navigation.php'),
            'dangling' => app_path('Modules/SafeClients/Models/SafeClient.php'),
            'lock' => base_path('.start-kit-generator.lock'),
            'resources' => base_path('resources'),
        };
        File::ensureDirectoryExists(dirname($link));
        $backup = $this->testEnvironment->path.'/resources-original';

        if ($kind === 'resources') {
            $this->assertTrue(rename($link, $backup));
        }

        if (is_file($link)) {
            unlink($link);
        }

        $destination = match ($kind) {
            'directory', 'resources' => $outside,
            'dangling' => $outside.'/missing.php',
            default => $outside.'/sentinel',
        };
        $this->assertTrue(symlink($destination, $link));
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($this->blueprint(), true);
        } finally {
            try {
                $this->assertSame('outside unchanged', File::get($outside.'/sentinel'));
                $this->assertTrue(is_link($link));
                $this->assertSame(['sentinel'], array_map(fn ($file): string => $file->getFilename(), File::allFiles($outside)));
            } finally {
                if (is_link($link)) {
                    unlink($link);
                }

                if ($kind === 'resources') {
                    rename($backup, $link);
                }
            }
        }
    }

    public static function symlinkDestinations(): array
    {
        return [['directory'], ['central'], ['dangling'], ['lock'], ['resources']];
    }

    #[DataProvider('generationTargets')]
    public function test_generation_registers_consumers_in_owned_copies_of_real_central_files(string $target): void
    {
        $source = dirname(__DIR__, 3);
        $originals = [];

        foreach (['bootstrap/providers.php', 'routes/web.php', 'routes/api/api.php', 'app/Providers/AppServiceProvider.php', 'config/exports.php', 'config/navigation.php', 'resources/js/lib/api/api-routes.ts'] as $path) {
            $this->assertNotSame(realpath($source), realpath(base_path()));
            $originals[$path] = hash_file('sha256', $source.'/'.$path);
            File::ensureDirectoryExists(dirname(base_path($path)));
            File::copy($source.'/'.$path, base_path($path));
        }

        $blueprint = $this->blueprint($target);
        $blueprint->model = 'RealClient';
        $blueprint->module = 'RealClients';
        app(CrudGeneratorService::class)->apply($blueprint);

        foreach ($originals as $path => $hash) {
            $this->assertSame($hash, hash_file('sha256', $source.'/'.$path));

            if (str_ends_with($path, '.php')) {
                $this->assertNotEmpty(token_get_all(File::get(base_path($path)), TOKEN_PARSE));
            }
        }

        $navigation = require config_path('navigation.php');
        $values = array_values(Arr::dot($navigation));
        $this->assertSame(1, count(array_filter($values, fn ($value): bool => $value === 'real_clients')));
        $this->assertSame(1, count(array_filter($values, fn ($value): bool => $value === 'real-clients.index')));
        $this->assertContains('/real-clients', $values);
        $exports = require config_path('exports.php');
        $this->assertContains($target === 'app' ? 'App\\Models\\RealClient' : 'App\\Modules\\RealClients\\Models\\RealClient', $exports['allowed_models']);
        $this->assertSame(1, substr_count(File::get(resource_path('js/lib/api/api-routes.ts')), '    realClients: {'));

        if ($target === 'module') {
            $providers = require base_path('bootstrap/providers.php');
            $this->assertContains('App\\Modules\\RealClients\\Providers\\RealClientsServiceProvider', $providers);
        } else {
            $this->assertStringContainsString("Route::resource('real-clients', RealClientController::class)", File::get(base_path('routes/web.php')));
            $this->assertStringContainsString("Route::apiResource('real-clients', RealClientApiController::class)", File::get(base_path('routes/api/api.php')));
            $this->assertStringContainsString('RealClient::observe(RealClientObserver::class)', File::get(app_path('Providers/AppServiceProvider.php')));
        }
    }

    #[DataProvider('invalidCentralAnchors')]
    public function test_missing_or_ambiguous_central_anchor_refuses_before_any_write(string $path, string $kind, string $target): void
    {
        $contents = File::get(base_path($path));
        File::put(base_path($path), $kind === 'missing' ? "<?php\nreturn [];\n" : $contents.$contents);
        $before = $this->projectSnapshot();
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($this->blueprint($target));
        } finally {
            $this->assertSame($before, $this->projectSnapshot());
        }
    }

    public static function invalidCentralAnchors(): array
    {
        return [
            ['config/navigation.php', 'missing', 'module'],
            ['config/navigation.php', 'ambiguous', 'module'],
            ['resources/js/lib/api/api-routes.ts', 'missing', 'module'],
            ['resources/js/lib/api/api-routes.ts', 'ambiguous', 'module'],
            ['config/exports.php', 'missing', 'module'],
            ['routes/web.php', 'missing', 'app'],
            ['routes/api/api.php', 'missing', 'app'],
            ['app/Providers/AppServiceProvider.php', 'missing', 'app'],
            ['bootstrap/providers.php', 'missing', 'module'],
        ];
    }

    public function test_force_refuses_even_an_owned_file_with_external_hardlink(): void
    {
        $generator = app(CrudGeneratorService::class);
        $file = $generator->preview($this->blueprint())->files[0];
        $outside = $this->testEnvironment->path.'/outside.php';
        File::put($outside, $file->content);
        File::ensureDirectoryExists(dirname($file->path));
        $this->assertTrue(link($outside, $file->path));
        $this->expectException(ValidationException::class);

        try {
            $generator->apply($this->blueprint(), true);
        } finally {
            try {
                $this->assertSame($file->content, File::get($outside));
            } finally {
                unlink($file->path);
            }
        }
    }

    #[DataProvider('hardlinkDestinations')]
    public function test_force_refuses_hardlinked_central_or_lock_destinations(string $kind): void
    {
        $path = $kind === 'central' ? config_path('navigation.php') : base_path('.start-kit-generator.lock');

        if ($kind === 'lock') {
            File::put($path, 'lock fixture');
        }

        $contents = File::get($path);
        $outside = $this->testEnvironment->path.'/hardlink-outside.php';
        File::put($outside, $contents);
        unlink($path);
        $this->assertTrue(link($outside, $path));
        $before = $this->projectSnapshot();
        $this->expectException(ValidationException::class);

        try {
            app(CrudGeneratorService::class)->apply($this->blueprint(), true);
        } finally {
            try {
                $this->assertSame($contents, File::get($outside));
                $this->assertSame($contents, File::get($path));
                $this->assertSame($before, $this->projectSnapshot());
            } finally {
                unlink($path);
            }
        }
    }

    public static function hardlinkDestinations(): array
    {
        return [['central'], ['lock']];
    }

    #[DataProvider('displayFields')]
    public function test_generated_resource_display_uses_name_or_uuid_fallback(array $fields, string $expected): void
    {
        $blueprint = $this->blueprint();
        $blueprint->fields = array_map(fn (string $name): CrudField => new CrudField(name: $name), $fields);
        $result = app(CrudGeneratorService::class)->preview($blueprint);
        $show = collect($result->files)->first(fn ($file): bool => str_ends_with($file->path, '/show.tsx'))->content;
        $this->assertStringContainsString('resourceName={String(safeClient.'.$expected.' ?? safeClient.uuid)}', $show);
    }

    public static function displayFields(): array
    {
        return [[['name'], 'name'], [['title', 'name'], 'name'], [['title'], 'uuid']];
    }

    public function test_failed_first_generation_removes_only_its_new_files(): void
    {
        $before = $this->projectSnapshot();
        $files = new class extends Filesystem
        {
            private int $writes = 0;

            public function replace($path, $content, $mode = null): void
            {
                if (++$this->writes === 4) {
                    throw new RuntimeException('Injected new-file write failure.');
                }

                parent::replace($path, $content, $mode);
            }
        };

        try {
            (new CrudGeneratorService($files))->apply($this->blueprint());
            $this->fail('A failed first generation must not report success.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Injected new-file write failure', $exception->getMessage());
        }

        $this->assertSame($before, $this->projectSnapshot());
    }

    public function test_quotes_and_backslashes_round_trip_as_php_literals(): void
    {
        $icon = "Icon'\\Quoted\$value";
        $rule = "regex:/^[A-Z'\\\\]+\$/";
        $blueprint = $this->blueprint();
        $blueprint->model = 'LiteralClient';
        $blueprint->module = 'LiteralClients';
        $blueprint->icon = $icon;
        $blueprint->fields = [new CrudField(name: 'name', validation: [$rule])];
        $result = app(CrudGeneratorService::class)->apply($blueprint);

        foreach ($result->files as $file) {
            if (str_ends_with($file->path, '.php')) {
                $this->assertNotEmpty(token_get_all(File::get($file->path), TOKEN_PARSE));
            }
        }

        $this->assertNotEmpty(token_get_all(File::get(config_path('navigation.php')), TOKEN_PARSE));
        $navigation = require config_path('navigation.php');
        $this->assertSame($icon, $navigation['items'][0]['icon']);

        foreach (['Store', 'Update'] as $verb) {
            require app_path("Modules/LiteralClients/Http/Requests/LiteralClient/{$verb}LiteralClientRequest.php");
            $class = "App\\Modules\\LiteralClients\\Http\\Requests\\LiteralClient\\{$verb}LiteralClientRequest";
            $this->assertSame(['required', 'string', 'max:255', $rule], (new $class)->rules()['name']);
        }
    }

    public function test_intermediate_write_failure_rolls_back_bytes_modes_and_can_retry(): void
    {
        $blueprint = $this->blueprint();
        $generator = app(CrudGeneratorService::class);
        $generator->apply($blueprint);
        $before = $this->projectSnapshot();
        $path = app_path('Modules/SafeClients/Models/SafeClient.php');
        chmod($path, 0640);
        clearstatcache(true, $path);
        $mode = fileperms($path) & 0777;
        $blueprint->fields = [new CrudField(name: 'changed')];
        $files = new class extends Filesystem
        {
            private int $writes = 0;

            public function replace($path, $content, $mode = null): void
            {
                if (++$this->writes === 4) {
                    throw new RuntimeException('Injected fourth write failure.');
                }

                parent::replace($path, $content, $mode);
            }
        };
        $generator = new CrudGeneratorService($files);

        try {
            $generator->apply($blueprint, true);
            $this->fail('A failed write must not report success.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Injected fourth write failure', $exception->getMessage());
        }

        $this->assertSame($before, $this->projectSnapshot());
        clearstatcache(true, $path);
        $this->assertSame($mode, fileperms($path) & 0777);
        $generator->apply($blueprint, true);
        $this->assertStringContainsString("'changed'", File::get($path));
    }

    public function test_partial_replace_and_residual_temporary_file_cannot_report_success_or_escape_cleanup(): void
    {
        $before = $this->projectSnapshot();
        $files = new class extends Filesystem
        {
            private int $writes = 0;

            public function replace($path, $content, $mode = null): void
            {
                if (++$this->writes === 4) {
                    parent::replace($path, substr($content, 0, 7), $mode);
                    parent::put(dirname($path).'/replace-residue.tmp', 'temporary generated code');

                    return;
                }

                parent::replace($path, $content, $mode);
            }
        };

        try {
            (new CrudGeneratorService($files))->apply($this->blueprint());
            $this->fail('A short write must not report success.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not written completely', $exception->getMessage());
        }

        $this->assertSame($before, $this->projectSnapshot());
    }

    public function test_preview_builds_modular_crud_plan_without_writing_files(): void
    {
        $blueprint = new CrudBlueprint(
            model: 'SpecClient',
            module: 'SpecClients',
            fields: [
                new CrudField(name: 'name', searchable: true, filterable: true),
                new CrudField(name: 'email', nullable: true, unique: true, searchable: true),
            ],
            withExport: true,
            withObserver: true,
        );

        $result = app(CrudGeneratorService::class)->preview($blueprint);
        $paths = array_map(fn ($file): string => $file->path, $result->files);

        $this->assertFalse($result->hasConflicts());
        $this->assertContains(app_path('Modules/SpecClients/Models/SpecClient.php'), $paths);
        $this->assertContains(app_path('Modules/SpecClients/Http/Controllers/SpecClientController.php'), $paths);
        $this->assertContains(app_path('Modules/SpecClients/Http/Requests/SpecClient/StoreSpecClientRequest.php'), $paths);
        $this->assertContains(app_path('Modules/SpecClients/Database/Factories/SpecClientFactory.php'), $paths);
        $this->assertContains(app_path('Modules/SpecClients/Database/Seeders/SpecClientSeeder.php'), $paths);
        $this->assertContains(resource_path('js/pages/app/spec-clients/index.tsx'), $paths);
        $this->assertContains(resource_path('js/types/app/spec-client.ts'), $paths);
        $this->assertTrue(
            collect($paths)->contains(fn (string $path): bool => str_contains($path, 'app/Modules/SpecClients/Database/Migrations') || str_contains($path, 'app\\Modules/SpecClients/Database/Migrations')),
        );

        $files = collect($result->files)->keyBy(fn ($file): string => $file->path);
        $controller = $files->get(app_path('Modules/SpecClients/Http/Controllers/SpecClientController.php'))->content;
        $indexPage = $files->get(resource_path('js/pages/app/spec-clients/index.tsx'))->content;
        $createPage = $files->get(resource_path('js/pages/app/spec-clients/create.tsx'))->content;
        $showPage = $files->get(resource_path('js/pages/app/spec-clients/show.tsx'))->content;
        $editPage = $files->get(resource_path('js/pages/app/spec-clients/edit.tsx'))->content;
        $fieldsPage = $files->get(resource_path('js/pages/app/spec-clients/partials/spec-client-form-fields.tsx'))->content;

        $this->assertStringContainsString("return to_route('spec-clients.index', [], 303);", $controller);
        $this->assertStringContainsString('const dateFilterColumns: DataTableDateColumn[]', $indexPage);
        $this->assertStringContainsString("void queryClient.invalidateQueries({ queryKey: ['spec-clients'] });", $indexPage);
        $this->assertStringContainsString('dateFilterColumns={dateFilterColumns}', $indexPage);
        $this->assertStringContainsString('tableId="spec-clients"', $indexPage);
        foreach ([$createPage, $editPage] as $formPage) {
            $this->assertStringContainsString('<div className="flex min-h-full flex-col">', $formPage);
            $this->assertStringContainsString('className="page-wrapper-md flex-1 pb-8"', $formPage);
            $this->assertStringContainsString('md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]', $formPage);
            $this->assertStringContainsString('<div className="sticky bottom-0 z-10 border-t bg-card/95">', $formPage);
            $this->assertStringContainsString('<Button type="submit" disabled={processing}>', $formPage);
            $this->assertStringContainsString("import { Card, CardContent } from '@/components/ui/card';", $formPage);
            $this->assertStringNotContainsString('CardTitle', $formPage);
            $this->assertStringNotContainsString('page-wrapper-sm', $formPage);
        }
        $this->assertStringContainsString('<Link href={index.url()}><X className="size-4" aria-hidden="true" />Cancelar</Link>', $createPage);
        $this->assertStringContainsString('<Link href={show.url(specClient.uuid)}><X className="size-4" aria-hidden="true" />Cancelar</Link>', $editPage);
        $this->assertStringContainsString("toast.error('Verifique os campos obrigatórios.')", $createPage);
        $this->assertStringContainsString("import Header from '@/components/dashboard/header';", $showPage);
        $this->assertStringContainsString('<Header title={String(specClient.name ?? specClient.uuid)}', $showPage);
        $this->assertStringNotContainsString('>specClient.uuid</p>', $showPage);
        $this->assertStringContainsString('<Header title="Novo Spec Client"', $createPage);
        $this->assertStringContainsString('<Header title="Spec Clients"', $indexPage);
        $this->assertStringContainsString('<Header title={String(specClient.name ?? specClient.uuid)}', $editPage);
        $this->assertStringNotContainsString('>specClient.uuid</p>', $editPage);
        $this->assertStringContainsString('<DeleteButton', $showPage);
        $this->assertStringContainsString('resourceName={String(specClient.name ?? specClient.uuid)}', $showPage);
        $this->assertStringContainsString('className="page-wrapper-lg"', $showPage);
        $this->assertStringContainsString('className="flex h-full flex-1 flex-col gap-6"', $showPage);
        $this->assertStringContainsString("import FormField from '@/components/ui/forms/form-field';", $fieldsPage);
        $this->assertStringContainsString('id="field-name"', $fieldsPage);
        $this->assertStringContainsString('<div className="grid gap-4 sm:grid-cols-2">', $fieldsPage);
        $this->assertStringNotContainsString('space-y-1', $fieldsPage);

        $this->assertContains('Allow App\Modules\SpecClients\Models\SpecClient in config/exports.php', $result->changes);
        $this->assertFileDoesNotExist(app_path('Modules/SpecClients/Models/SpecClient.php'));
    }

    public function test_preview_can_skip_provider_registration(): void
    {
        $blueprint = new CrudBlueprint(
            model: 'ManualClient',
            module: 'ManualClients',
            fields: [
                new CrudField(name: 'name', searchable: true),
            ],
            registerProvider: false,
        );

        $result = app(CrudGeneratorService::class)->preview($blueprint);

        $paths = array_map(fn ($file): string => $file->path, $result->files);

        $this->assertContains(
            'Skip ServiceProvider generation and bootstrap/providers.php registration for App\Modules\ManualClients\Providers\ManualClientsServiceProvider',
            $result->changes,
        );
        $this->assertNotContains(
            app_path('Modules/ManualClients/Providers/ManualClientsServiceProvider.php'),
            $paths,
        );
        $this->assertNotContains(
            'Generate and register App\Modules\ManualClients\Providers\ManualClientsServiceProvider in bootstrap/providers.php',
            $result->changes,
        );
    }

    public function test_preview_can_target_app_structure(): void
    {
        $blueprint = new CrudBlueprint(
            model: 'AppClient',
            module: 'AppClients',
            fields: [
                new CrudField(name: 'name', searchable: true),
            ],
            registerProvider: false,
            target: 'app',
        );

        $result = app(CrudGeneratorService::class)->preview($blueprint);
        $paths = array_map(fn ($file): string => $file->path, $result->files);

        $this->assertContains(app_path('Models/AppClient.php'), $paths);
        $this->assertContains(app_path('Http/Controllers/AppClientController.php'), $paths);
        $this->assertContains(app_path('Http/Controllers/Api/AppClientApiController.php'), $paths);
        $this->assertTrue(
            collect($paths)->contains(fn (string $path): bool => str_contains($path, 'app/Http/Requests/AppClient/StoreAppClientRequest.php') || str_contains($path, 'app\\Http/Requests/AppClient/StoreAppClientRequest.php')),
        );
        $this->assertContains(database_path('factories/AppClientFactory.php'), $paths);
        $this->assertContains(database_path('seeders/AppClientSeeder.php'), $paths);
        $this->assertTrue(
            collect($paths)->contains(fn (string $path): bool => str_contains($path, 'database/migrations') || str_contains($path, 'database\\migrations')),
        );
        $this->assertNotContains(app_path('Modules/AppClients/routes.php'), $paths);
        $this->assertContains('Register App\Http\Controllers\AppClientController in routes/web.php', $result->changes);
        $this->assertContains('Register App\Http\Controllers\Api\AppClientApiController in routes/api/api.php', $result->changes);
    }
}
