<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Services;

use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyArtist;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;

class ArtistService
{
    public function __construct(
        private SpotifyHttpClient $httpClient,
        private AuthService $auth
    ) {}

    public function find(string $id): SpotifyArtist
    {
        $response = $this->httpClient
            ->withAccessToken($this->auth->getClientAccessToken())
            ->get("/artists/{$id}");

        return SpotifyArtist::fromArray($response->getData());
    }
}
