<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Actions\SyncPlaylistFromSpotifyAction;
use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use App\Modules\Playlists\Http\Resources\PlaylistResource;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SpotifyPlaylistSyncController extends Controller
{
    public function __invoke(Playlist $playlist, SyncPlaylistFromSpotifyAction $sync): JsonResponse
    {
        Gate::authorize('update', $playlist);

        try {
            $result = $sync->execute($playlist, 'manual');
        } catch (SpotifyPlaylistLookupException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'spotify_playlist_id' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Dados do Spotify sincronizados.',
            'data' => new PlaylistResource($result['playlist']),
            'snapshot' => $result['snapshot'],
            'events' => $result['events'],
        ]);
    }
}
