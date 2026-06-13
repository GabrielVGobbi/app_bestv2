<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Services\SpotifyPlaylistLookupService;
use App\Modules\Playlists\Support\SpotifyPlaylistInputParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SpotifyPlaylistPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        SpotifyPlaylistInputParser $parser,
        SpotifyPlaylistLookupService $lookup,
    ): JsonResponse {
        Gate::authorize('create', Playlist::class);

        $validated = $request->validate([
            'spotify_input' => ['required', 'string', 'max:255'],
        ]);

        $spotifyPlaylistId = $parser->parse($validated['spotify_input']);

        if ($spotifyPlaylistId === null) {
            $resourceType = $parser->resourceType($validated['spotify_input']);

            throw ValidationException::withMessages([
                'spotify_input' => $resourceType !== null && $resourceType !== 'playlist'
                    ? 'Este campo aceita apenas playlists do Spotify. O link informado é de '.$this->resourceLabel($resourceType).'.'
                    : 'Informe um link ou ID válido de playlist do Spotify.',
            ]);
        }

        $existingPlaylist = Playlist::query()
            ->where('spotify_playlist_id', $spotifyPlaylistId)
            ->first();

        if ($existingPlaylist !== null) {
            return response()->json([
                'message' => 'Esta playlist já está cadastrada.',
                'errors' => [
                    'spotify_input' => ['Esta playlist já está cadastrada.'],
                ],
                'duplicate' => [
                    'uuid' => $existingPlaylist->uuid,
                    'name' => $existingPlaylist->name,
                    'spotify_playlist_id' => $existingPlaylist->spotify_playlist_id,
                ],
            ], 409);
        }

        try {
            $preview = $lookup->preview($spotifyPlaylistId);
        } catch (SpotifyPlaylistLookupException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'spotify_input' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json(['data' => $preview]);
    }

    private function resourceLabel(string $resourceType): string
    {
        return match ($resourceType) {
            'album' => 'álbum',
            'artist' => 'artista',
            'track' => 'música',
            default => $resourceType,
        };
    }
}
