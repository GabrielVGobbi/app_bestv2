<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AddressesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/addresses.php', 'addresses');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::middleware((array) config('addresses.route_middleware', ['api', 'auth:sanctum']))
            ->prefix((string) config('addresses.route_prefix', 'api/v1/addresses'))
            ->name((string) config('addresses.route_name', 'api.addresses.'))
            ->group(__DIR__.'/../Routes/api.php');
    }
}
