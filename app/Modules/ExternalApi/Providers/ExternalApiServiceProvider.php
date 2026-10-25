<?php

namespace App\Modules\ExternalApi\Providers;

use App\Modules\ExternalApi\Console\Commands\ExternalApiInstallCommand;
use App\Modules\ExternalApi\Console\Commands\ExternalApiMakeCommand;
use App\Modules\ExternalApi\Core\Support\ExternalApiRateLimiter;
use App\Modules\ExternalApi\Core\Support\ExternalApiRegistry;
use App\Modules\ExternalApi\Core\Support\ExternalApiRequestLogger;
use App\Modules\ExternalApi\Core\Support\ExternalApiResponseCache;
use App\Modules\ExternalApi\Core\Support\OAuthStateManager;
use App\Modules\ExternalApi\Core\Support\RetryPolicy;
use Illuminate\Support\ServiceProvider;

class ExternalApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->modulePath('config/external-apis.php'), 'external-apis');

        $this->app->singleton(ExternalApiRegistry::class);
        $this->app->singleton(ExternalApiRateLimiter::class);
        $this->app->singleton(ExternalApiResponseCache::class);
        $this->app->singleton(ExternalApiRequestLogger::class);
        $this->app->singleton(RetryPolicy::class);
        $this->app->singleton(OAuthStateManager::class);

        foreach ($this->app->make(ExternalApiRegistry::class)->enabledProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom($this->modulePath('routes.php'));
        $this->loadMigrationsFrom($this->modulePath('Database/Migrations'));

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->modulePath('config/external-apis.php') => config_path('external-apis.php'),
            ], 'external-api-config');

            $this->commands([
                ExternalApiInstallCommand::class,
                ExternalApiMakeCommand::class,
            ]);
        }
    }

    private function modulePath(string $path = ''): string
    {
        return __DIR__.'/../'.ltrim($path, '/');
    }
}
