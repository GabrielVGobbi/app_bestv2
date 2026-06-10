<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\Console\Commands;

use App\Modules\CrudGenerator\DTOs\CrudBlueprint;
use App\Modules\CrudGenerator\Services\CrudGeneratorService;
use Illuminate\Console\Command;
use Throwable;

class StartkitCrudCommand extends Command
{
    protected $signature = 'startkit:crud
        {model : Model name, for example Client}
        {--target=module : Generation target: module or app}
        {--module= : Module name, defaults to plural model name}
        {--field=* : Field definition, for example name:string:required:searchable}
        {--with-export : Enable async export integration}
        {--without-export : Disable async export integration}
        {--with-observer : Generate and register observer}
        {--with-policy : Generate and register policy}
        {--without-policy : Skip policy generation}
        {--with-factory : Generate factory}
        {--without-factory : Skip factory generation}
        {--with-seeder : Generate seeder}
        {--without-seeder : Skip seeder generation}
        {--with-tests : Generate feature test}
        {--without-tests : Skip test generation}
        {--register-provider : Generate module provider and register it in bootstrap/providers.php}
        {--without-register-provider : Do not generate or register a module provider}
        {--icon=TableProperties : Lucide icon name for navigation}
        {--preview : Only show planned files and central changes}
        {--force : Overwrite existing generated files}';

    protected $description = 'Generate a StartKit CRUD with Laravel, Inertia, DataTable and optional export support.';

    public function handle(CrudGeneratorService $generator): int
    {
        try {
            $blueprint = CrudBlueprint::fromCli(
                model: (string) $this->argument('model'),
                module: $this->option('module') ? (string) $this->option('module') : null,
                fieldDefinitions: $this->option('field'),
                options: [
                    'with_export' => ! $this->option('without-export'),
                    'with_observer' => (bool) $this->option('with-observer'),
                    'with_policy' => ! $this->option('without-policy'),
                    'with_factory' => ! $this->option('without-factory'),
                    'with_seeder' => ! $this->option('without-seeder'),
                    'with_tests' => ! $this->option('without-tests'),
                    'register_provider' => ! $this->option('without-register-provider'),
                    'target' => (string) $this->option('target'),
                    'icon' => (string) $this->option('icon'),
                ],
            );

            $result = $this->option('preview')
                ? $generator->preview($blueprint)
                : $generator->apply($blueprint, (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($this->option('preview') ? 'Preview generated.' : 'CRUD generated.');
        $this->line('');
        $this->line("Model: {$result->blueprint->model}");
        $this->line("Target: {$result->blueprint->target}");
        $this->line("Module: {$result->blueprint->module}");
        $this->line('');

        $this->line('Files:');
        foreach ($result->files as $file) {
            $status = $file->exists ? '<fg=yellow>conflict/exists</>' : '<fg=green>create</>';
            $this->line("  {$status} {$file->path}");
        }

        $this->line('');
        $this->line('Central changes:');
        foreach ($result->changes as $change) {
            $this->line("  - {$change}");
        }

        if (! $this->option('preview')) {
            $this->line('');
            $this->warn('Run: php artisan wayfinder:generate --with-form --no-interaction');
        }

        return self::SUCCESS;
    }
}
