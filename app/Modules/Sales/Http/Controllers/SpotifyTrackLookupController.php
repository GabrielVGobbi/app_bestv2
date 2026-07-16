<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Sales\Exceptions\SpotifyTrackLookupException;
use App\Modules\Sales\Services\SpotifyTrackLookupService;
use App\Modules\Sales\Support\SpotifyTrackInputParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SpotifyTrackLookupController extends Controller
{
    public function __invoke(
        Request $request,
        SpotifyTrackInputParser $parser,
        SpotifyTrackLookupService $lookup,
    ): JsonResponse {
        $user = $request->user();
        abort_unless(
            $user instanceof User && $this->hasPermission($user, 'sales.items.spotify-track.lookup'),
            403,
        );

        $validated = $request->validate([
            'spotify_input' => ['required', 'string', 'max:255'],
        ]);

        $spotifyTrackId = $parser->parse($validated['spotify_input']);

        if ($spotifyTrackId === null) {
            $resourceType = $parser->resourceType($validated['spotify_input']);

            throw ValidationException::withMessages([
                'spotify_input' => $resourceType !== null && $resourceType !== 'track'
                    ? 'Este campo aceita apenas músicas do Spotify. O link informado é de '.$this->resourceLabel($resourceType).'.'
                    : 'Informe um link, URI ou ID válido de música do Spotify.',
            ]);
        }

        try {
            $preview = $lookup->preview($spotifyTrackId);
        } catch (SpotifyTrackLookupException $exception) {
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
            'playlist' => 'playlist',
            default => $resourceType,
        };
    }

    private function hasPermission(User $user, string $slug): bool
    {
        return $user->hasAnyPermission($slug);
    }
}
