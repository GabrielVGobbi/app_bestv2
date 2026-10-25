<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Services;

use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyAlbum;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;

class AlbumService
{
    public function __construct(
        private SpotifyHttpClient $httpClient,
        private AuthService $auth
    ) {}

    public function find(string $id, ?string $market = null): SpotifyAlbum
    {
        $response = $this->httpClient
            ->withAccessToken($this->auth->getClientAccessToken())
            ->get("/albums/{$id}", array_filter(['market' => $market]));

        return SpotifyAlbum::fromArray($response->getData());
    }
}
