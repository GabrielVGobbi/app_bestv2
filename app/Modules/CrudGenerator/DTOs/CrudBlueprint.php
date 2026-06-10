<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\DTOs;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class CrudBlueprint
{
    /**
     * @param  array<int, CrudField>  $fields
     */
    public function __construct(
        public string $model,
        public string $module,
        public array $fields,
        public bool $withExport = true,
        public bool $withObserver = false,
        public bool $withPolicy = true,
        public bool $withFactory = true,
        public bool $withSeeder = true,
        public bool $withTests = true,
        public bool $registerProvider = true,
        public string $target = 'module',
        public string $icon = 'TableProperties',
    ) {
        if ($this->fields === []) {
            throw new InvalidArgumentException('At least one field is required.');
        }

        if (! in_array($this->target, ['app', 'module'], true)) {
            throw new InvalidArgumentException('Target must be app or module.');
        }
    }

    /**
     * @param  array<int, string>  $fieldDefinitions
     */
    public static function fromCli(string $model, ?string $module, array $fieldDefinitions, array $options = []): self
    {
        return new self(
            model: Str::studly($model),
            module: Str::studly($module ?: Str::pluralStudly($model)),
            fields: array_map(fn (string $field): CrudField => CrudField::fromCli($field), $fieldDefinitions),
            withExport: (bool) ($options['with_export'] ?? true),
            withObserver: (bool) ($options['with_observer'] ?? false),
            withPolicy: (bool) ($options['with_policy'] ?? true),
            withFactory: (bool) ($options['with_factory'] ?? true),
            withSeeder: (bool) ($options['with_factory'] ?? true) && (bool) ($options['with_seeder'] ?? true),
            withTests: (bool) ($options['with_tests'] ?? true),
            registerProvider: (bool) ($options['register_provider'] ?? true),
            target: (string) ($options['target'] ?? 'module'),
            icon: (string) ($options['icon'] ?? 'TableProperties'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            model: Str::studly((string) $data['model']),
            module: Str::studly((string) ($data['module'] ?? Str::pluralStudly((string) $data['model']))),
            fields: array_map(fn (array $field): CrudField => CrudField::fromArray($field), $data['fields'] ?? []),
            withExport: (bool) ($data['with_export'] ?? true),
            withObserver: (bool) ($data['with_observer'] ?? false),
            withPolicy: (bool) ($data['with_policy'] ?? true),
            withFactory: (bool) ($data['with_factory'] ?? true),
            withSeeder: (bool) ($data['with_factory'] ?? true) && (bool) ($data['with_seeder'] ?? true),
            withTests: (bool) ($data['with_tests'] ?? true),
            registerProvider: (bool) ($data['register_provider'] ?? true),
            target: (string) ($data['target'] ?? 'module'),
            icon: (string) ($data['icon'] ?? 'TableProperties'),
        );
    }

    public function isAppTarget(): bool
    {
        return $this->target === 'app';
    }

    public function isModuleTarget(): bool
    {
        return $this->target === 'module';
    }

    public function modelVariable(): string
    {
        return Str::camel($this->model);
    }

    public function modelPluralVariable(): string
    {
        return Str::plural(Str::camel($this->model));
    }

    public function routeName(): string
    {
        return Str::kebab(Str::plural($this->model));
    }

    public function tableName(): string
    {
        return Str::snake(Str::pluralStudly($this->model));
    }

    public function pageDirectory(): string
    {
        return Str::kebab(Str::plural($this->model));
    }

    public function pageDirectorySingular(): string
    {
        return Str::kebab($this->model);
    }

    public function modelClass(): string
    {
        if ($this->isAppTarget()) {
            return "App\\Models\\{$this->model}";
        }

        return "App\\Modules\\{$this->module}\\Models\\{$this->model}";
    }

    public function controllerClass(): string
    {
        if ($this->isAppTarget()) {
            return "App\\Http\\Controllers\\{$this->model}Controller";
        }

        return "App\\Modules\\{$this->module}\\Http\\Controllers\\{$this->model}Controller";
    }

    public function apiControllerClass(): string
    {
        if ($this->isAppTarget()) {
            return "App\\Http\\Controllers\\Api\\{$this->model}ApiController";
        }

        return "App\\Modules\\{$this->module}\\Http\\Controllers\\Api\\{$this->model}ApiController";
    }

    public function requestNamespace(): string
    {
        if ($this->isAppTarget()) {
            return "App\\Http\\Requests\\{$this->model}";
        }

        return "App\\Modules\\{$this->module}\\Http\\Requests\\{$this->model}";
    }

    public function resourceClass(): string
    {
        if ($this->isAppTarget()) {
            return "App\\Http\\Resources\\{$this->model}Resource";
        }

        return "App\\Modules\\{$this->module}\\Http\\Resources\\{$this->model}Resource";
    }

    public function factoryClass(): string
    {
        if ($this->isAppTarget()) {
            return "Database\\Factories\\{$this->model}Factory";
        }

        return "App\\Modules\\{$this->module}\\Database\\Factories\\{$this->model}Factory";
    }

    public function seederClass(): string
    {
        if ($this->isAppTarget()) {
            return "Database\\Seeders\\{$this->model}Seeder";
        }

        return "App\\Modules\\{$this->module}\\Database\\Seeders\\{$this->model}Seeder";
    }

    public function policyClass(): string
    {
        if ($this->isAppTarget()) {
            return "App\\Policies\\{$this->model}Policy";
        }

        return "App\\Modules\\{$this->module}\\Policies\\{$this->model}Policy";
    }

    public function observerClass(): string
    {
        if ($this->isAppTarget()) {
            return "App\\Observers\\{$this->model}Observer";
        }

        return "App\\Modules\\{$this->module}\\Observers\\{$this->model}Observer";
    }

    public function controllerActionImport(): string
    {
        if ($this->isAppTarget()) {
            return "@/actions/App/Http/Controllers/{$this->model}Controller";
        }

        return "@/actions/App/Modules/{$this->module}/Http/Controllers/{$this->model}Controller";
    }

    public function providerClass(): string
    {
        return "App\\Modules\\{$this->module}\\Providers\\{$this->module}ServiceProvider";
    }
}
