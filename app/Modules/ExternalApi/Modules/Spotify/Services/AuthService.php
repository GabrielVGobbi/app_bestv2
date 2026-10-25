<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Services;

use App\Models\User;
use App\Modules\ExternalApi\Core\Exceptions\ConfigurationException;
use App\Modules\ExternalApi\Core\Exceptions\ExternalApiException;
use App\Modules\ExternalApi\Core\Support\OAuthStateManager;
use App\Modules\ExternalApi\Core\Support\RetryPolicy;
use App\Modules\ExternalApi\Models\ExternalApiConnection;
use App\Modules\ExternalApi\Modules\Spotify\Configuration\SpotifyConfiguration;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyToken;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;
use App\Modules\ExternalApi\Modules\Spotify\Mocks\SpotifyMockData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AuthService
{
    public function __construct(
        private SpotifyConfiguration $configuration,
        private SpotifyHttpClient $httpClient,
        private ?OAuthStateManager $stateManager = null,
        private ?RetryPolicy $retryPolicy = null,
    ) {}

    public function getAuthorizationUrl(User $user): string
    {
        $this->stateManager ??= app(OAuthStateManager::class);
        $state = $this->stateManager->create('spotify', $user);

        return $this->configuration->getAuthUrl().'/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->configuration->getClientId(),
            'redirect_uri' => $this->configuration->getRedirectUri(),
            'scope' => implode(' ', $this->configuration->getScopes()),
            'state' => $state,
            'show_dialog' => 'false',
        ]);
    }

    public function handleCallback(User $user, string $code, string $state): ExternalApiConnection
    {
        $this->stateManager ??= app(OAuthStateManager::class);
        $this->stateManager->validate('spotify', $user, $state);

        $token = $this->exchangeAuthorizationCode($code);
        $profile = $this->profile($token->accessToken);

        return ExternalApiConnection::updateOrCreate(
            ['user_id' => $user->id, 'provider' => 'spotify'],
            [
                'provider_user_id' => $profile['id'] ?? null,
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'token_type' => $token->tokenType,
                'scopes' => $token->scopes,
                'expires_at' => $token->expiresAt(),
                'metadata' => [
                    'display_name' => $profile['display_name'] ?? null,
                    'email' => $profile['email'] ?? null,
                ],
            ]
        );
    }

    public function disconnect(User $user): void
    {
        ExternalApiConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', 'spotify')
            ->delete();
    }

    public function getClientAccessToken(): string
    {
        if ($this->configuration->isFake()) {
            return SpotifyMockData::token()['access_token'];
        }

        $cachedToken = Cache::get($this->clientCredentialsCacheKey());

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $token = $this->clientCredentialsToken();
        Cache::put($this->clientCredentialsCacheKey(), $token->accessToken, now()->addSeconds(max(60, $token->expiresIn - 60)));

        return $token->accessToken;
    }

    public function getUserAccessToken(User $user): string
    {
        $connection = ExternalApiConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', 'spotify')
            ->first();

        if (! $connection) {
            throw new ExternalApiException('Spotify is not connected for this user.');
        }

        if ($connection->expires_at?->isFuture()) {
            return (string) $connection->access_token;
        }

        if ($connection->refresh_token === null) {
            throw new ExternalApiException('Spotify refresh token is missing.');
        }

        $this->configuration->validate();
        $lockSeconds = max($this->configuration->getTimeout(), $this->configuration->getConnectTimeout()) + 10;
        $lock = Cache::lock("external-api:oauth-refresh:spotify:{$user->id}", $lockSeconds);

        return $lock->block($lockSeconds, function () use ($connection): string {
            $connection->refresh();

            if ($connection->expires_at?->isFuture()) {
                return (string) $connection->access_token;
            }

            if ($connection->refresh_token === null) {
                throw new ExternalApiException('Spotify refresh token is missing.');
            }

            $token = $this->refreshToken((string) $connection->refresh_token);

            $connection->forceFill([
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken ?? $connection->refresh_token,
                'token_type' => $token->tokenType,
                'scopes' => $token->scopes ?: $connection->scopes,
                'expires_at' => $token->expiresAt(),
            ])->save();

            return $token->accessToken;
        });
    }

    public function clientCredentialsToken(): SpotifyToken
    {
        if ($this->configuration->isFake()) {
            return SpotifyToken::fromArray(SpotifyMockData::token());
        }

        return $this->tokenRequest([
            'grant_type' => 'client_credentials',
        ]);
    }

    private function exchangeAuthorizationCode(string $code): SpotifyToken
    {
        if ($this->configuration->isFake()) {
            return SpotifyToken::fromArray(SpotifyMockData::token());
        }

        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->configuration->getRedirectUri(),
        ]);
    }

    private function refreshToken(string $refreshToken): SpotifyToken
    {
        if ($this->configuration->isFake()) {
            return SpotifyToken::fromArray(SpotifyMockData::token());
        }

        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    private function tokenRequest(array $payload): SpotifyToken
    {
        $this->retryPolicy ??= app(RetryPolicy::class);

        if ($this->configuration->getClientId() === '' || $this->configuration->getClientSecret() === '') {
            throw new ConfigurationException('Spotify client ID and secret are required');
        }

        $response = Http::asForm()
            ->withBasicAuth($this->configuration->getClientId(), $this->configuration->getClientSecret())
            ->timeout($this->configuration->getTimeout())
            ->connectTimeout($this->configuration->getConnectTimeout())
            ->retry(
                max(1, $this->configuration->getRetryAttempts()),
                fn (int $attempt, Throwable $throwable): int => $this->retryPolicy->delayMilliseconds($attempt, $throwable),
                fn (Throwable $throwable): bool => $this->retryPolicy->shouldRetry($throwable, 'POST'),
                throw: false
            )
            ->post($this->configuration->getAuthUrl().'/api/token', $payload);

        if (! $response->successful()) {
            throw new ExternalApiException("Spotify token request failed (HTTP {$response->status()}).", $response->status());
        }

        return SpotifyToken::fromArray($response->json());
    }

    private function profile(string $accessToken): array
    {
        if ($this->configuration->isFake()) {
            return SpotifyMockData::me();
        }

        return $this->httpClient
            ->withAccessToken($accessToken)
            ->get('/me')
            ->getData();
    }

    private function clientCredentialsCacheKey(): string
    {
        return 'external-api:spotify:client-token:'.hash('xxh128', $this->configuration->getClientId());
    }
}
