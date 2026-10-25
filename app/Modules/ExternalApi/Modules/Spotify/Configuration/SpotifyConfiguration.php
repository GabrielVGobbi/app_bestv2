<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Configuration;

use App\Modules\ExternalApi\Core\Abstracts\AbstractConfiguration;
use App\Modules\ExternalApi\Core\Exceptions\ConfigurationException;

class SpotifyConfiguration extends AbstractConfiguration
{
    public function getModuleKey(): string
    {
        return 'spotify';
    }

    public function getBaseUrl(): string
    {
        return (string) config('external-apis.modules.spotify.base_url', 'https://api.spotify.com/v1');
    }

    public function getAuthUrl(): string
    {
        return rtrim((string) config('external-apis.modules.spotify.auth_url', 'https://accounts.spotify.com'), '/');
    }

    public function getClientId(): string
    {
        return (string) config('external-apis.modules.spotify.client_id', '');
    }

    public function getClientSecret(): string
    {
        return (string) config('external-apis.modules.spotify.client_secret', '');
    }

    public function getRedirectUri(): string
    {
        return (string) config('external-apis.modules.spotify.redirect_uri', url('/external-api/spotify/callback'));
    }

    public function getScopes(): array
    {
        $scopes = config('external-apis.modules.spotify.scopes', '');

        if (is_array($scopes)) {
            return $scopes;
        }

        return array_values(array_filter(explode(' ', (string) $scopes)));
    }

    public function getApiKey(): string
    {
        return $this->getClientId();
    }

    public function validate(): void
    {
        parent::validate();

        if ($this->isFake()) {
            return;
        }

        if ($this->getClientId() === '' || $this->getClientSecret() === '') {
            throw new ConfigurationException('Spotify client ID and secret are required');
        }
    }
}
