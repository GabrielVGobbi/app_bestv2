<?php

declare(strict_types=1);

namespace App\Modules\Sales\Providers;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Policies\SalePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(Sale::class, SalePolicy::class);
    }
}
