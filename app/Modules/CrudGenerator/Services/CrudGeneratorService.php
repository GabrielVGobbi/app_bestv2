<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\Services;

use App\Modules\CrudGenerator\DTOs\CrudBlueprint;
use App\Modules\CrudGenerator\DTOs\CrudField;
use App\Modules\CrudGenerator\DTOs\CrudGeneratedFile;
use App\Modules\CrudGenerator\DTOs\CrudGenerationResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class CrudGeneratorService
{
    public function __construct(private readonly Filesystem $files) {}

    public function preview(CrudBlueprint $blueprint): CrudGenerationResult
    {
        return new CrudGenerationResult(
            blueprint: $blueprint,
            files: $this->plannedFiles($blueprint),
            changes: $this->centralChanges($blueprint),
        );
    }

    public function apply(CrudBlueprint $blueprint, bool $force = false): CrudGenerationResult
    {
        $result = $this->preview($blueprint);

        if ($result->hasConflicts() && ! $force) {
            throw new RuntimeException('Generation has file conflicts. Use force to overwrite.');
        }

        foreach ($result->files as $file) {
            $this->files->ensureDirectoryExists(dirname($file->path));
            $this->files->put($file->path, $file->content);
        }

        if ($blueprint->isModuleTarget() && $blueprint->registerProvider) {
            $this->registerProvider($blueprint);
        }

        if ($blueprint->isAppTarget()) {
            $this->registerWebRoute($blueprint);
            $this->registerApiRouteFile($blueprint);

            if ($blueprint->withObserver) {
                $this->registerAppObserver($blueprint);
            }
        }

        $this->registerExportModel($blueprint);
        $this->registerNavigation($blueprint);
        $this->registerApiRoute($blueprint);

        return $this->preview($blueprint);
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
            fn (string $path, string $content): CrudGeneratedFile => new CrudGeneratedFile($path, $content, $this->files->exists($path)),
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
                "Register {$blueprint->apiControllerClass()} in routes/api.php",
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
        if ($blueprint->isAppTarget()) {
            return database_path('migrations/'.$this->migrationName($blueprint));
        }

        return app_path("Modules/{$blueprint->module}/Database/Migrations/".$this->migrationName($blueprint));
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

    private function registerProvider(CrudBlueprint $blueprint): void
    {
        $path = base_path('bootstrap/providers.php');
        $contents = $this->files->get($path);
        $use = "use {$blueprint->providerClass()};";
        $entry = "    {$blueprint->module}ServiceProvider::class,";

        if (! str_contains($contents, $use)) {
            $contents = preg_replace('/(use App\\\\Providers\\\\TelescopeServiceProvider;\R)/', "$1{$use}\n", $contents) ?? $contents;
        }

        if (! str_contains($contents, $entry)) {
            $contents = str_replace("    NotificationsServiceProvider::class,\n", "    NotificationsServiceProvider::class,\n{$entry}\n", $contents);
        }

        $this->files->put($path, $contents);
    }

    private function registerWebRoute(CrudBlueprint $blueprint): void
    {
        $path = base_path('routes/web.php');
        $contents = $this->files->get($path);
        $use = "use {$blueprint->controllerClass()};";
        $route = "    Route::resource('{$blueprint->routeName()}', {$blueprint->model}Controller::class)\n        ->parameters(['{$blueprint->routeName()}' => '{$blueprint->modelVariable()}']);\n\n";

        if (! str_contains($contents, $use)) {
            $contents = preg_replace('/(use App\\\\Http\\\\Controllers\\\\UserController;\R)/', "$1{$use}\n", $contents, 1) ?? $contents;
        }

        if (! str_contains($contents, "Route::resource('{$blueprint->routeName()}', {$blueprint->model}Controller::class)")) {
            $contents = str_replace("    Route::resource('users', UserController::class);\n", "    Route::resource('users', UserController::class);\n\n{$route}", $contents);
        }

        $this->files->put($path, $contents);
    }

    private function registerApiRouteFile(CrudBlueprint $blueprint): void
    {
        $path = base_path('routes/api.php');
        $contents = $this->files->get($path);
        $use = "use {$blueprint->apiControllerClass()};";
        $route = "        Route::apiResource('{$blueprint->routeName()}', {$blueprint->model}ApiController::class)\n            ->parameters(['{$blueprint->routeName()}' => '{$blueprint->modelVariable()}']);\n\n";

        if (! str_contains($contents, $use)) {
            $contents = preg_replace('/(use App\\\\Http\\\\Controllers\\\\Api\\\\UserApiController;\R)/', "$1{$use}\n", $contents, 1) ?? $contents;
        }

        if (! str_contains($contents, "Route::apiResource('{$blueprint->routeName()}', {$blueprint->model}ApiController::class)")) {
            $contents = str_replace("        Route::apiResource('users', UserApiController::class)->only('index');\n", "        Route::apiResource('users', UserApiController::class)->only('index');\n\n{$route}", $contents);
        }

        $this->files->put($path, $contents);
    }

    private function registerAppObserver(CrudBlueprint $blueprint): void
    {
        $path = app_path('Providers/AppServiceProvider.php');
        $contents = $this->files->get($path);
        $modelUse = "use {$blueprint->modelClass()};";
        $observerUse = "use {$blueprint->observerClass()};";
        $registration = "        {$blueprint->model}::observe({$blueprint->model}Observer::class);";

        if (! str_contains($contents, $modelUse)) {
            $contents = preg_replace('/(use App\\\\Listeners\\\\LogUserLogin;\R)/', "$1{$modelUse}\n", $contents, 1) ?? $contents;
        }

        if (! str_contains($contents, $observerUse)) {
            $contents = preg_replace('/(use App\\\\Listeners\\\\LogUserLogin;\R)/', "$1{$observerUse}\n", $contents, 1) ?? $contents;
        }

        if (! str_contains($contents, $registration)) {
            if (str_contains($contents, '    private function registerObservers(): void {}')) {
                $contents = str_replace(
                    '    private function registerObservers(): void {}',
                    "    private function registerObservers(): void\n    {\n{$registration}\n    }",
                    $contents,
                );
            } else {
                $contents = preg_replace('/(private function registerObservers\(\): void\R    \{\R)/', "$1{$registration}\n", $contents, 1) ?? $contents;
            }
        }

        $this->files->put($path, $contents);
    }

    private function registerExportModel(CrudBlueprint $blueprint): void
    {
        if (! $blueprint->withExport) {
            return;
        }

        $path = config_path('exports.php');
        $contents = $this->files->get($path);
        $use = "use {$blueprint->modelClass()};";
        $entry = "        {$blueprint->model}::class,";

        if (! str_contains($contents, $use)) {
            $contents = preg_replace('/(<\?php\R\R)/', "$1{$use}\n", $contents, 1) ?? $contents;
        }

        if (! str_contains($contents, $entry)) {
            $contents = str_replace("        User::class,\n", "        User::class,\n{$entry}\n", $contents);
        }

        $this->files->put($path, $contents);
    }

    private function registerNavigation(CrudBlueprint $blueprint): void
    {
        $path = config_path('navigation.php');
        $contents = $this->files->get($path);
        $prefix = "                '/{$blueprint->routeName()}',";
        $slug = Str::snake($blueprint->routeName());

        if (! str_contains($contents, $prefix)) {
            $contents = str_replace("                '/exportacoes',\n", "{$prefix}\n                '/exportacoes',\n", $contents);
        }

        if (! str_contains($contents, "'slug' => '{$slug}'")) {
            $item = <<<PHP
                [
                    'slug' => '{$slug}',
                    'label' => '{$this->humanPlural($blueprint)}',
                    'icon' => '{$blueprint->icon}',
                    'type' => 'link',
                    'href' => ['route' => '{$blueprint->routeName()}.index'],
                    'order' => 6,
                    'enabled' => true,
                    'visible' => true,
                ],

PHP;

            $contents = str_replace("                [\n                    'slug' => 'exportacoes',", $item."                [\n                    'slug' => 'exportacoes',", $contents);
        }

        $this->files->put($path, $contents);
    }

    private function registerApiRoute(CrudBlueprint $blueprint): void
    {
        $path = resource_path('js/lib/api/api-routes.ts');
        $contents = $this->files->get($path);
        $key = Str::camel($blueprint->modelPluralVariable());

        if (str_contains($contents, "    {$key}: {")) {
            return;
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

        $contents = str_replace("    exports: {\n", $block."    exports: {\n", $contents);
        $this->files->put($path, $contents);
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
    ->middleware('auth:sanctum')
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
use Inertia\\Inertia;
use Inertia\\Response;

class {$blueprint->model}Controller extends Controller
{
    public function index(): Response
    {
        return Inertia::render('app/{$blueprint->pageDirectory()}/index');
    }

    public function create(): Response
    {
        return Inertia::render('app/{$blueprint->pageDirectory()}/create');
    }

    public function store(Store{$blueprint->model}Request \$request): RedirectResponse
    {
        \${$var} = {$blueprint->model}::create(\$request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('{$blueprint->model} created.')]);

        return to_route('{$blueprint->routeName()}.show', \${$var});
    }

    public function show({$blueprint->model} \${$var}): Response
    {
        return Inertia::render('app/{$blueprint->pageDirectory()}/show', [
            '{$var}Data' => new {$blueprint->model}Resource(\${$var}),
        ]);
    }

    public function edit({$blueprint->model} \${$var}): Response
    {
        return Inertia::render('app/{$blueprint->pageDirectory()}/edit', [
            '{$var}Data' => new {$blueprint->model}Resource(\${$var}),
        ]);
    }

    public function update(Update{$blueprint->model}Request \$request, {$blueprint->model} \${$var}): RedirectResponse
    {
        \${$var}->update(\$request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('{$blueprint->model} updated.')]);

        return to_route('{$blueprint->routeName()}.show', \${$var});
    }

    public function destroy({$blueprint->model} \${$var}): RedirectResponse
    {
        \${$var}->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('{$blueprint->model} deleted.')]);

        return to_route('{$blueprint->routeName()}.index', [], 303);
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

class {$blueprint->model}ApiController extends Controller
{
    public function index(PaginationRequest \$request): AnonymousResourceCollection
    {
        return ApiDisplay::for({$blueprint->model}::class, {$blueprint->model}Resource::class)->paginate(\$request);
    }

    public function store(Store{$blueprint->model}Request \$request): {$blueprint->model}Resource
    {
        \${$var} = {$blueprint->model}::create(\$request->validated());

        return new {$blueprint->model}Resource(\${$var});
    }

    public function show({$blueprint->model} \${$var}): {$blueprint->model}Resource
    {
        return new {$blueprint->model}Resource(\${$var});
    }

    public function update(Update{$blueprint->model}Request \$request, {$blueprint->model} \${$var}): {$blueprint->model}Resource
    {
        \${$var}->update(\$request->validated());

        return new {$blueprint->model}Resource(\${$var});
    }

    public function destroy({$blueprint->model} \${$var}): JsonResponse
    {
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

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\\Foundation\\Http\\FormRequest;
use Illuminate\\Validation\\Rule;

class {$class} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use App\\Models\\User;
use {$modelClass};

class {$blueprint->model}Policy
{
    public function viewAny(User \$user): bool
    {
        return true;
    }

    public function view(User \$user, {$blueprint->model} \${$blueprint->modelVariable()}): bool
    {
        return true;
    }

    public function create(User \$user): bool
    {
        return true;
    }

    public function update(User \$user, {$blueprint->model} \${$blueprint->modelVariable()}): bool
    {
        return true;
    }

    public function delete(User \$user, {$blueprint->model} \${$blueprint->modelVariable()}): bool
    {
        return true;
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
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import { create, destroy, edit, index, show } from '{$actionImport}';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableDateColumn,
{$exportImports}    DataTableFilterConfig,
} from '@/components/data-table';
import Heading from '@/components/heading';
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

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex items-center justify-between gap-4">
                        <Heading title="{$humanPlural}" description="Gerencie os registros de {$route}" />
                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                Novo {$humanSingular}
                            </Link>
                        </Button>
                    </div>

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
                                    Cancelar
                                </Button>
                                <Button variant="destructive" onClick={delete{$model}}>
                                    Excluir
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<{$model}>
                        queryKey="{$route}"
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
                        searchPlaceholder="Buscar {$route}..."
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
        $humanPlural = $this->humanPlural($blueprint);
        $humanSingularLower = Str::lower($humanSingular);
        $displayField = $this->displayField($blueprint);
        $resourceType = Str::lower($humanSingular);

        if ($isEdit) {
            return <<<TSX
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import { destroy, index, show, update } from '{$actionImport}';
import DeleteButton from '@/components/delete-button';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
        <>
            <Head title="Editar {$humanSingular}" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">{String({$var}.{$displayField} ?? {$var}.uuid)}</h1>
                        <p className="text-sm text-muted-foreground">{$var}.uuid</p>
                    </div>
                </div>
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

            <div className="page-wrapper-sm">
                <Form
                    action={update.url({$var}.uuid)}
                    method="put"
                    options={{ preserveScroll: true }}
                    onError={() => toast.error('Verifique os campos obrigatórios.')}
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-4">
                                <div className="flex flex-1 flex-col gap-6 ">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Informações de {$humanSingular}</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <{$model}FormFields {$var}={{$var}} errors={errors} />
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                                    <div className="flex items-center justify-between gap-3 py-3">
                                        <p className="text-xs text-muted-foreground">
                                            Campos marcados com <span className="text-destructive">*</span> são obrigatórios
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <Button type="button" variant="ghost" size="sm" asChild disabled={processing}>
                                                <Link href={show.url({$var}.uuid)}>Cancelar</Link>
                                            </Button>
                                            <Button type="submit" size="sm" disabled={processing}>
                                                <Save />
                                                {processing ? 'Salvando...' : 'Salvar'}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

TSX;
        }

        return <<<TSX
import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { toast } from 'sonner';

import { create, index, store } from '{$actionImport}';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {$model}FormFields from '@/pages/app/{$pageDir}/partials/{$typePath}-form-fields';

export default function Create{$model}() {
    setLayoutProps({
        breadcrumbs: [
            { title: '{$humanPlural}', href: index.url() },
            { title: 'Novo {$humanSingular}', href: create.url() },
        ],
    });

    return (
        <>
            <Head title="Novo {$humanSingular}" />

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">Novo {$humanSingular}</h1>
                        <p className="text-sm text-muted-foreground">Cadastrar novo {$humanSingularLower}</p>
                    </div>
                </div>
            </Header>

            <div className="page-wrapper-sm">
                <Form
                    action={store.url()}
                    method="post"
                    options={{ preserveScroll: true }}
                    onError={() => toast.error('Verifique os campos obrigatórios.')}
                    className="flex h-full flex-col"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-4">
                                <div className="flex flex-1 flex-col gap-6 ">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Informações de {$humanSingular}</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <{$model}FormFields errors={errors} />
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="sticky bottom-0 border-t bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                                    <div className="flex items-center justify-between gap-3 py-3">
                                        <p className="text-xs text-muted-foreground">
                                            Campos marcados com <span className="text-destructive">*</span> são obrigatórios
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <Button type="button" variant="ghost" size="sm" asChild disabled={processing}>
                                                <Link href={index.url()}>Cancelar</Link>
                                            </Button>
                                            <Button type="submit" size="sm" disabled={processing}>
                                                <Save />
                                                {processing ? 'Salvando...' : 'Salvar'}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
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
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, CalendarDays } from 'lucide-react';

import { destroy, index, show } from '{$actionImport}';
import DeleteButton from '@/components/delete-button';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
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

            <Header>
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" className="size-8" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-xl font-semibold">{String({$var}.{$displayField} ?? {$var}.uuid)}</h1>
                        <p className="text-sm text-muted-foreground">{$var}.uuid</p>
                    </div>
                </div>
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
                                    <div className="flex size-20 items-center justify-center rounded-2xl bg-primary/10 text-2xl font-bold text-primary">
                                        {initials}
                                    </div>
                                    <div className="text-center">
                                        <h2 className="text-lg font-semibold">{String({$var}.{$displayField} ?? {$var}.uuid)}</h2>
                                        <p className="text-sm text-muted-foreground">{$var}.uuid</p>
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
                                    <CardTitle>Informações de {$humanSingular}</CardTitle>
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
        label: 'Visão Geral',
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
        <nav className="flex gap-1 rounded-lg border bg-muted/30 p-1">
            {navItems.map(({ key, label, icon: Icon, getHref }) => {
                const isActive = key === currentPage;

                return (
                    <Link
                        key={key}
                        href={getHref({$var}Uuid)}
                        className={cn(
                            'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/50 hover:text-foreground',
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
                $required = $field->nullable ? '' : "\n                    required";

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
        <div className="grid gap-4">
            <div className="grid gap-2 space-y-1">
{$fields}
            </div>
        </div>
    );
}

TSX;
    }

    private function featureTest(CrudBlueprint $blueprint): string
    {
        $modelClass = $blueprint->modelClass();

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\\Feature\\Crud;

use App\\Models\\User;
use {$modelClass};
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class {$blueprint->model}CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_{$blueprint->modelVariable()}_index(): void
    {
        \$user = User::factory()->create();

        \$this->actingAs(\$user)
            ->get(route('{$blueprint->routeName()}.index'))
            ->assertOk();
    }

    public function test_api_returns_{$blueprint->modelVariable()}_records(): void
    {
        \$user = User::factory()->create();
        {$blueprint->model}::factory()->count(2)->create();

        \$this->actingAs(\$user)
            ->getJson('/api/v1/{$blueprint->routeName()}')
            ->assertOk()
            ->assertJsonCount(2, 'data');
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
                    $rules[] = "'{$rule}'";
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
        return '['.implode(', ', array_map(fn (string $item): string => "'{$item}'", $items)).']';
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
            ->map(fn (CrudField $field): string => "        '{$field->name}' => '{$field->label()}',")
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
            ?->name ?? 'uuid';
    }

    private function hasDateColumns(CrudBlueprint $blueprint): bool
    {
        return collect($blueprint->fields)
            ->contains(fn (CrudField $field): bool => in_array($field->type, ['date', 'dateTime', 'timestamp'], true));
    }
}
