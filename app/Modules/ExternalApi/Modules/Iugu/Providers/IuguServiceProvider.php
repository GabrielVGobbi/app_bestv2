<?php

namespace App\Modules\ExternalApi\Modules\Iugu\Providers;

use App\Modules\ExternalApi\Modules\Iugu\Configuration\IuguConfiguration;
use App\Modules\ExternalApi\Modules\Iugu\Http\IuguHttpClient;
use App\Modules\ExternalApi\Modules\Iugu\Services\IuguManager;
use Illuminate\Support\ServiceProvider;

class IuguServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IuguConfiguration::class);

        $this->app->bind(IuguHttpClient::class, function ($app) {
            return new IuguHttpClient($app->make(IuguConfiguration::class));
        });

        $this->app->bind(IuguManager::class, function ($app) {
            return new IuguManager(
                $app->make(IuguConfiguration::class),
                $app->make(IuguHttpClient::class)
            );
        });

        $this->app->alias(IuguManager::class, 'iugu');
    }
}
