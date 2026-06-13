<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Http\Requests\PlaylistTop\StorePlaylistTopRequest;
use App\Modules\Playlists\Http\Requests\PlaylistTop\UpdatePlaylistTopRequest;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PlaylistTopController extends Controller
{
    public function store(StorePlaylistTopRequest $request, Playlist $playlist): RedirectResponse
    {
        $playlist->playlistTops()->create($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TOP vinculado à playlist.')]);

        return to_route('playlists.show', $playlist);
    }

    public function update(UpdatePlaylistTopRequest $request, Playlist $playlist, PlaylistTop $playlistTop): RedirectResponse
    {
        $this->ensureBelongsToPlaylist($playlist, $playlistTop);

        $playlistTop->update($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TOP da playlist atualizado.')]);

        return to_route('playlists.show', $playlist);
    }

    public function destroy(Playlist $playlist, PlaylistTop $playlistTop): RedirectResponse
    {
        $this->ensureBelongsToPlaylist($playlist, $playlistTop);

        Gate::authorize('detach', $playlistTop);

        $playlistTop->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TOP removido da playlist.')]);

        return to_route('playlists.show', $playlist);
    }

    private function ensureBelongsToPlaylist(Playlist $playlist, PlaylistTop $playlistTop): void
    {
        abort_unless($playlistTop->playlist_id === $playlist->id, 404);
    }
}
