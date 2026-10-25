<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Services;

use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyAlbum;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyArtist;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyPlaylist;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifySearchResult;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyTrack;
use App\Modules\ExternalApi\Modules\Spotify\Enums\SpotifySearchType;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;

class SearchService
{
    public function __construct(
        private SpotifyHttpClient $httpClient,
        private AuthService $auth
    ) {}

    public function tracks(string $query, int $limit = 20, ?string $market = null, int $offset = 0): SpotifySearchResult
    {
        $data = $this->search($query, SpotifySearchType::Track, $limit, $market, $offset);

        return new SpotifySearchResult(
            items: array_map(fn (array $track): SpotifyTrack => SpotifyTrack::fromArray($track), $this->validItems($data['tracks']['items'] ?? [])),
            type: SpotifySearchType::Track->value
        );
    }

    public function artists(string $query, int $limit = 20, ?string $market = null, int $offset = 0): SpotifySearchResult
    {
        $data = $this->search($query, SpotifySearchType::Artist, $limit, $market, $offset);

        return new SpotifySearchResult(
            items: array_map(fn (array $artist): SpotifyArtist => SpotifyArtist::fromArray($artist), $this->validItems($data['artists']['items'] ?? [])),
            type: SpotifySearchType::Artist->value
        );
    }

    public function albums(string $query, int $limit = 20, ?string $market = null, int $offset = 0): SpotifySearchResult
    {
        $data = $this->search($query, SpotifySearchType::Album, $limit, $market, $offset);

        return new SpotifySearchResult(
            items: array_map(fn (array $album): SpotifyAlbum => SpotifyAlbum::fromArray($album), $this->validItems($data['albums']['items'] ?? [])),
            type: SpotifySearchType::Album->value
        );
    }

    public function playlists(string $query, int $limit = 20, ?string $market = null, int $offset = 0): SpotifySearchResult
    {
        $data = $this->search($query, SpotifySearchType::Playlist, $limit, $market, $offset);

        return new SpotifySearchResult(
            items: array_map(fn (array $playlist): SpotifyPlaylist => SpotifyPlaylist::fromArray($playlist), $this->validItems($data['playlists']['items'] ?? [])),
            type: SpotifySearchType::Playlist->value
        );
    }

    private function search(string $query, SpotifySearchType $type, int $limit, ?string $market, int $offset): array
    {
        return $this->httpClient
            ->withAccessToken($this->auth->getClientAccessToken())
            ->get('/search', array_filter([
                'q' => $query,
                'type' => $type->value,
                'limit' => $limit,
                'market' => $market,
                'offset' => $offset,
            ], fn ($value): bool => $value !== null))
            ->getData();
    }

    private function validItems(array $items): array
    {
        return array_values(array_filter($items, fn (mixed $item): bool => is_array($item)));
    }
}
