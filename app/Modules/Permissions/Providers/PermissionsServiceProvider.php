<?php

namespace App\Modules\Permissions\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    /**
     * Gates de plataforma que exigem sessao web e ambiente local: o papel super
     * nao os atravessa pelo bypass, a propria gate decide (ver AppServiceProvider).
     *
     * @var array<int, string>
     */
    private const SESSION_ONLY_GATES = [
        'useDeveloperTools',
        'viewPulse',
        'manageIntegrationSettings',
        'view-email-logs',
        'resend-email-logs',
        'manage-email-settings',
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerGates();
    }

    protected function registerGates(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (in_array($ability, self::SESSION_ONLY_GATES, true)) {
                return null;
            }

            return $user->hasAnyPermission($ability) ? true : null;
        });
    }
}
