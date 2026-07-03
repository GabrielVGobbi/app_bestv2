<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Providers;

use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Policies\SupplierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SuppliersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::model('supplier', Supplier::class);
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Gate::policy(Supplier::class, SupplierPolicy::class);
    }
}
