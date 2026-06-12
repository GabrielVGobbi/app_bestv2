<?php

declare(strict_types=1);

namespace App\Modules\Services\Providers;

use App\Modules\Services\Models\Service;
use App\Modules\Services\Policies\ServicePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ServicesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(Service::class, ServicePolicy::class);
    }
}
