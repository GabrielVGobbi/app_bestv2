<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Services;

use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyPlaylist;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;

class PlaylistService
{
    public function __construct(
        private SpotifyHttpClient $httpClient,
        private AuthService $auth
    ) {}

    public function find(string $id, ?string $market = null): SpotifyPlaylist
    {
        $response = $this->httpClient
            ->withAccessToken($this->auth->getClientAccessToken())
            ->get("/playlists/{$id}", array_filter(['market' => $market]));

        return SpotifyPlaylist::fromArray($response->getData());
    }
}
