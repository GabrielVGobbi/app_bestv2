<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use App\Modules\Playlists\Http\Resources\PlaylistResource;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Services\SpotifyPlaylistLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SpotifyPlaylistSyncController extends Controller
{
    public function __invoke(Playlist $playlist, SpotifyPlaylistLookupService $lookup): JsonResponse
    {
        Gate::authorize('update', $playlist);

        if (! is_string($playlist->spotify_playlist_id) || $playlist->spotify_playlist_id === '') {
            return response()->json([
                'message' => 'Vincule um ID público do Spotify antes de sincronizar.',
                'errors' => [
                    'spotify_playlist_id' => ['Vincule um ID público do Spotify antes de sincronizar.'],
                ],
            ], 422);
        }

        try {
            $preview = $lookup->preview($playlist->spotify_playlist_id);
        } catch (SpotifyPlaylistLookupException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'spotify_playlist_id' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $spotifyName = $preview['name'] ?? null;

        $playlist->update([
            'name' => is_string($spotifyName) && trim($spotifyName) !== ''
                ? trim($spotifyName)
                : $playlist->name,
            'spotify_url' => $preview['spotify_url'] ?? $playlist->spotify_url,
            'spotify_snapshot_id' => $preview['spotify_snapshot_id'] ?? null,
            'spotify_owner_id' => $preview['spotify_owner_id'] ?? null,
            'spotify_owner_name' => $preview['spotify_owner_name'] ?? null,
            'spotify_tracks_total' => $preview['spotify_tracks_total'] ?? null,
            'spotify_last_synced_at' => now(),
            'followers_count' => $preview['followers_count'] ?? $playlist->followers_count,
        ]);

        return response()->json([
            'message' => 'Dados do Spotify sincronizados.',
            'data' => new PlaylistResource($playlist->fresh(['genre', 'subgenres'])),
        ]);
    }
}
