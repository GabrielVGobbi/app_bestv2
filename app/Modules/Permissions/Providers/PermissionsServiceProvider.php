<?php

namespace App\Modules\Permissions\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerGates();
    }

    protected function registerGates(): void
    {
        Gate::before(fn ($user, string $ability): ?bool => $user->hasAnyPermission($ability) ? true : null);
    }
}
