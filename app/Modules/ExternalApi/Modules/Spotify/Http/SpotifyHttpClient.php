<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Http;

use App\Modules\ExternalApi\Core\Abstracts\AbstractHttpClient;

class SpotifyHttpClient extends AbstractHttpClient
{
    private ?string $accessToken = null;

    public function withAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    protected function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'Laravel-ExternalApi/1.0',
        ];

        if ($this->accessToken !== null) {
            $headers['Authorization'] = 'Bearer '.$this->accessToken;
        }

        return $headers;
    }
}
