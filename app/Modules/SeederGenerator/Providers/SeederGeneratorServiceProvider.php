<?php

declare(strict_types=1);

namespace App\Modules\SeederGenerator\Providers;

use Illuminate\Support\ServiceProvider;

class SeederGeneratorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
    }
}
