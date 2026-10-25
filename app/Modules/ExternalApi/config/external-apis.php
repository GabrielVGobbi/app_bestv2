<?php

use App\Modules\ExternalApi\Modules\Asaas\Configuration\AsaasConfiguration;
use App\Modules\ExternalApi\Modules\Asaas\Http\AsaasHttpClient;
use App\Modules\ExternalApi\Modules\Asaas\Providers\AsaasServiceProvider;
use App\Modules\ExternalApi\Modules\Asaas\Services\AsaasManager;
use App\Modules\ExternalApi\Modules\Iugu\Configuration\IuguConfiguration;
use App\Modules\ExternalApi\Modules\Iugu\Http\IuguHttpClient;
use App\Modules\ExternalApi\Modules\Iugu\Providers\IuguServiceProvider;
use App\Modules\ExternalApi\Modules\Iugu\Services\IuguManager;
use App\Modules\ExternalApi\Modules\Spotify\Configuration\SpotifyConfiguration;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyMockHttpClient;
use App\Modules\ExternalApi\Modules\Spotify\Providers\SpotifyServiceProvider;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;

return [
    'fake' => [
        'enabled' => env('EXTERNAL_API_FAKE_ENABLED', true),
    ],

    'cache' => [
        'enabled' => env('EXTERNAL_API_CACHE_ENABLED', false),
        'default_ttl' => env('EXTERNAL_API_CACHE_TTL', 3600),
        'driver' => env('EXTERNAL_API_CACHE_DRIVER', env('CACHE_STORE', 'database')),
        'recent_requests_limit' => env('EXTERNAL_API_RECENT_REQUESTS_LIMIT', 50),
    ],

    'retry' => [
        'base_delay_ms' => env('EXTERNAL_API_RETRY_BASE_DELAY_MS', 250),
        'max_delay_ms' => env('EXTERNAL_API_RETRY_MAX_DELAY_MS', 5000),
        'jitter_ms' => env('EXTERNAL_API_RETRY_JITTER_MS', 100),
        'retryable_statuses' => [408, 423, 425, 429, 500, 502, 503, 504],
    ],

    'rate_limit' => [
        'decay_seconds' => env('EXTERNAL_API_RATE_LIMIT_DECAY_SECONDS', 60),
        'lock_seconds' => env('EXTERNAL_API_RATE_LIMIT_LOCK_SECONDS', 3),
    ],

    'logging' => [
        'enabled' => env('EXTERNAL_API_LOGGING_ENABLED', true),
        'channel' => env('EXTERNAL_API_LOG_CHANNEL'),
    ],

    'diagnostics' => [
        'enabled' => env('EXTERNAL_API_DIAGNOSTICS_ENABLED', true),
    ],

    'modules' => [
        'iugu' => [
            'enabled' => env('IUGU_ENABLED', true),
            'provider' => IuguServiceProvider::class,
            'manager' => IuguManager::class,
            'configuration' => IuguConfiguration::class,
            'http_client' => IuguHttpClient::class,
            'fake' => env('IUGU_FAKE_ENABLED', env('EXTERNAL_API_FAKE_ENABLED', true)),
            'base_url' => env('IUGU_BASE_URL', 'https://api.iugu.com/v1'),
            'api_key' => env('IUGU_API_KEY'),
            'timeout' => env('IUGU_TIMEOUT', 30),
            'connect_timeout' => env('IUGU_CONNECT_TIMEOUT', 10),
            'retry_attempts' => env('IUGU_RETRY_ATTEMPTS', 3),
            'rate_limit' => env('IUGU_RATE_LIMIT', 100),
            'endpoints' => [],
        ],

        'asaas' => [
            'enabled' => env('ASAAS_ENABLED', true),
            'provider' => AsaasServiceProvider::class,
            'manager' => AsaasManager::class,
            'configuration' => AsaasConfiguration::class,
            'http_client' => AsaasHttpClient::class,
            'fake' => env('ASAAS_FAKE_ENABLED', env('EXTERNAL_API_FAKE_ENABLED', true)),
            'base_url' => env('ASAAS_BASE_URL', 'https://api.asaas.com/v3'),
            'api_key' => env('ASAAS_API_KEY'),
            'timeout' => env('ASAAS_TIMEOUT', 30),
            'connect_timeout' => env('ASAAS_CONNECT_TIMEOUT', 10),
            'retry_attempts' => env('ASAAS_RETRY_ATTEMPTS', 3),
            'rate_limit' => env('ASAAS_RATE_LIMIT', 200),
            'endpoints' => [],
        ],

        'spotify' => [
            'enabled' => env('SPOTIFY_ENABLED', true),
            'provider' => SpotifyServiceProvider::class,
            'manager' => SpotifyManager::class,
            'configuration' => SpotifyConfiguration::class,
            'http_client' => SpotifyHttpClient::class,
            'mock_client' => SpotifyMockHttpClient::class,
            'fake' => env('SPOTIFY_FAKE_ENABLED', env('EXTERNAL_API_FAKE_ENABLED', true)),
            'base_url' => env('SPOTIFY_BASE_URL', 'https://api.spotify.com/v1'),
            'auth_url' => env('SPOTIFY_AUTH_URL', 'https://accounts.spotify.com'),
            'client_id' => env('SPOTIFY_CLIENT_ID'),
            'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
            'redirect_uri' => env('SPOTIFY_REDIRECT_URI'),
            'scopes' => env('SPOTIFY_SCOPES', 'user-read-email playlist-read-private playlist-read-collaborative'),
            'timeout' => env('SPOTIFY_TIMEOUT', 30),
            'connect_timeout' => env('SPOTIFY_CONNECT_TIMEOUT', 10),
            'retry_attempts' => env('SPOTIFY_RETRY_ATTEMPTS', 3),
            'rate_limit' => env('SPOTIFY_RATE_LIMIT', 100),
            'endpoints' => [
                'search' => [
                    'cache' => [
                        'enabled' => env('SPOTIFY_SEARCH_CACHE_ENABLED', true),
                        'ttl' => env('SPOTIFY_SEARCH_CACHE_TTL', 300),
                    ],
                    'rate_limit' => env('SPOTIFY_SEARCH_RATE_LIMIT', env('SPOTIFY_RATE_LIMIT', 100)),
                ],
                'tracks' => [
                    'cache' => [
                        'enabled' => env('SPOTIFY_TRACKS_CACHE_ENABLED', true),
                        'ttl' => env('SPOTIFY_TRACKS_CACHE_TTL', 900),
                    ],
                ],
                'artists' => [
                    'cache' => [
                        'enabled' => env('SPOTIFY_ARTISTS_CACHE_ENABLED', true),
                        'ttl' => env('SPOTIFY_ARTISTS_CACHE_TTL', 900),
                    ],
                ],
                'albums' => [
                    'cache' => [
                        'enabled' => env('SPOTIFY_ALBUMS_CACHE_ENABLED', true),
                        'ttl' => env('SPOTIFY_ALBUMS_CACHE_TTL', 900),
                    ],
                ],
                'playlists' => [
                    'cache' => [
                        'enabled' => env('SPOTIFY_PLAYLISTS_CACHE_ENABLED', true),
                        'ttl' => env('SPOTIFY_PLAYLISTS_CACHE_TTL', 900),
                    ],
                ],
            ],
        ],
    ],

    'monitoring' => [
        'enabled' => env('EXTERNAL_API_CACHE_MONITORING', false),
        'log_hits' => env('EXTERNAL_API_CACHE_LOG_HITS', false),
        'log_misses' => env('EXTERNAL_API_CACHE_LOG_MISSES', true),
        'metrics_enabled' => env('EXTERNAL_API_CACHE_METRICS', false),
    ],
];
