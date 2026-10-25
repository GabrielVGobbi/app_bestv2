<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Services;

use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyTrack;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;

class TrackService
{
    public function __construct(
        private SpotifyHttpClient $httpClient,
        private AuthService $auth
    ) {}

    public function find(string $id, ?string $market = null): SpotifyTrack
    {
        $response = $this->httpClient
            ->withAccessToken($this->auth->getClientAccessToken())
            ->get("/tracks/{$id}", array_filter(['market' => $market]));

        return SpotifyTrack::fromArray($response->getData());
    }
}
