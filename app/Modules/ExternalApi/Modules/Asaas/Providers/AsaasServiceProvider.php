<?php

namespace App\Modules\ExternalApi\Modules\Asaas\Providers;

use App\Modules\ExternalApi\Modules\Asaas\Configuration\AsaasConfiguration;
use App\Modules\ExternalApi\Modules\Asaas\Http\AsaasHttpClient;
use App\Modules\ExternalApi\Modules\Asaas\Services\AsaasManager;
use Illuminate\Support\ServiceProvider;

class AsaasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AsaasConfiguration::class);

        $this->app->bind(AsaasHttpClient::class, function ($app) {
            return new AsaasHttpClient($app->make(AsaasConfiguration::class));
        });

        $this->app->bind(AsaasManager::class, function ($app) {
            return new AsaasManager(
                $app->make(AsaasConfiguration::class),
                $app->make(AsaasHttpClient::class)
            );
        });

        $this->app->alias(AsaasManager::class, 'asaas');
    }

    public function boot(): void
    {
        //
    }
}
