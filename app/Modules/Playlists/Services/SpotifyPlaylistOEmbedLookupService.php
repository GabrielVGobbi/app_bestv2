<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Services;

use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SpotifyPlaylistOEmbedLookupService
{
    /** @return array<string, mixed>|null */
    public function preview(string $spotifyPlaylistId): ?array
    {
        $spotifyUrl = 'https://open.spotify.com/playlist/'.$spotifyPlaylistId;

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.spotify.timeout', 10))
                ->connectTimeout((int) config('services.spotify.connect_timeout', 5))
                ->get('https://open.spotify.com/oembed', [
                    'url' => $spotifyUrl,
                ]);
        } catch (ConnectionException) {
            throw new SpotifyPlaylistLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
        }

        if (! $response->successful()) {
            return null;
        }

        $title = $response->json('title');

        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        return [
            'spotify_playlist_id' => $spotifyPlaylistId,
            'spotify_url' => $spotifyUrl,
            'name' => trim($title),
            'followers_count' => null,
            'spotify_owner_id' => null,
            'spotify_owner_name' => null,
            'spotify_tracks_total' => null,
            'spotify_snapshot_id' => null,
            'public' => null,
            'source' => 'oembed',
            'is_partial' => true,
        ];
    }
}
