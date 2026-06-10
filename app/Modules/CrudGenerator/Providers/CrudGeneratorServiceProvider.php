<?php

declare(strict_types=1);

namespace App\Modules\CrudGenerator\Providers;

use App\Modules\CrudGenerator\Console\Commands\StartkitCrudCommand;
use Illuminate\Support\ServiceProvider;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                StartkitCrudCommand::class,
            ]);
        }
    }
}
