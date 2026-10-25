<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpotifyBrowserController extends Controller
{
    public function __construct(private SpotifyManager $spotify) {}

    public function artists(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = $validated['q'] ?? 'Daft Punk';

        return Inertia::render('app/external-api/spotify/artists', [
            'query' => $query,
            'artists' => $this->spotify
                ->search()
                ->artists($query, limit: 12)
                ->toArray()['items'],
            'isFake' => $this->spotify->getConfiguration()->isFake(),
        ]);
    }

    public function playlists(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = $validated['q'] ?? 'Focus';

        return Inertia::render('app/external-api/spotify/playlists', [
            'query' => $query,
            'playlists' => $this->spotify
                ->search()
                ->playlists($query, limit: 12)
                ->toArray()['items'],
            'isFake' => $this->spotify->getConfiguration()->isFake(),
        ]);
    }
}
