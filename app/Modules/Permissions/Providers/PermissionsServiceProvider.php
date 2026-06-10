<?php

namespace App\Modules\Permissions\Providers;

use App\Modules\Permissions\Models\Permission;
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
        Gate::before(function ($user, $ability) {
            $super = config('permissions.super_role', 'developer');

            // Super role tem acesso a tudo
            if ($user->hasRole($super)) {
                return true;
            }

            // Verifica se existe uma permission com este slug
            $permission = Permission::where('slug', $ability)->first();

            if ($permission && $user->hasPermissionTo($permission)) {
                return true;
            }

            return null;
        });
    }
}
