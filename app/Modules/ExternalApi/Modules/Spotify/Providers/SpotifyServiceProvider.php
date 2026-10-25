<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Providers;

use App\Modules\ExternalApi\Modules\Spotify\Configuration\SpotifyConfiguration;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyMockHttpClient;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use Illuminate\Support\ServiceProvider;

class SpotifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpotifyConfiguration::class);

        $this->app->bind(SpotifyHttpClient::class, function ($app) {
            $configuration = $app->make(SpotifyConfiguration::class);

            return $configuration->isFake()
                ? new SpotifyMockHttpClient($configuration)
                : new SpotifyHttpClient($configuration);
        });

        $this->app->bind(SpotifyManager::class, function ($app) {
            return new SpotifyManager(
                $app->make(SpotifyConfiguration::class),
                $app->make(SpotifyHttpClient::class)
            );
        });

        $this->app->alias(SpotifyManager::class, 'spotify');
    }
}
