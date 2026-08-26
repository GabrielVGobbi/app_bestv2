<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPlaylistFromSpotifyJob;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Http\Requests\Playlist\StorePlaylistRequest;
use App\Modules\Playlists\Http\Requests\Playlist\UpdatePlaylistRequest;
use App\Modules\Playlists\Http\Resources\MusicGenreResource;
use App\Modules\Playlists\Http\Resources\MusicSubgenreResource;
use App\Modules\Playlists\Http\Resources\PlaylistResource;
use App\Modules\Playlists\Http\Resources\PlaylistTopResource;
use App\Modules\Playlists\Http\Resources\TopResource;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Playlist::class);

        return Inertia::render('app/playlists/index', [
            'permissions' => $this->permissions(),
            'filters' => $this->formOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Playlist::class);

        return Inertia::render('app/playlists/create', [
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function store(StorePlaylistRequest $request): RedirectResponse
    {
        $playlist = Playlist::create($request->playlistPayload());
        $playlist->subgenres()->sync($request->subgenreIds());
        $this->dispatchSpotifySync($playlist, 'created');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playlist criada.')]);

        return to_route('playlists.show', $playlist);
    }

    public function show(Playlist $playlist): Response
    {
        Gate::authorize('view', $playlist);

        return Inertia::render('app/playlists/show', [
            'playlistData' => new PlaylistResource($playlist->load(['genre', 'subgenres', 'spotifyCoverUpload', 'latestSpotifySnapshot', 'latestSpotifySyncRun'])),
            'playlistTops' => PlaylistTopResource::collection(
                $playlist->playlistTops()
                    ->with('top')
                    ->orderBy('id')
                    ->get()
            ),
            'topOptions' => TopResource::collection(
                Top::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
            ),
            'permissions' => $this->permissions($playlist),
            'playlistTopPermissions' => $this->playlistTopPermissions(),
        ]);
    }

    public function edit(Playlist $playlist): Response
    {
        Gate::authorize('update', $playlist);

        return Inertia::render('app/playlists/edit', [
            'playlistData' => new PlaylistResource($playlist->load(['genre', 'subgenres', 'spotifyCoverUpload', 'latestSpotifySnapshot', 'latestSpotifySyncRun'])),
            'permissions' => $this->permissions($playlist),
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(UpdatePlaylistRequest $request, Playlist $playlist): RedirectResponse
    {
        $playlist->update($request->playlistPayload());
        $playlist->subgenres()->sync($request->subgenreIds());
        $this->dispatchSpotifySync($playlist, 'updated');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playlist atualizada.')]);

        return to_route('playlists.show', $playlist);
    }

    public function updateStatus(Request $request, Playlist $playlist): RedirectResponse
    {
        Gate::authorize('update', $playlist);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(PlaylistStatus::class)],
        ]);

        $playlist->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status da playlist atualizado.')]);

        return back();
    }

    public function destroy(Playlist $playlist): RedirectResponse
    {
        Gate::authorize('delete', $playlist);

        $playlist->update(['status' => PlaylistStatus::Archived]);
        $playlist->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playlist arquivada.')]);

        return to_route('playlists.index', [], 303);
    }

    public function restore(string $playlist): RedirectResponse
    {
        $playlistModel = Playlist::withTrashed()->whereUuid($playlist)->firstOrFail();

        Gate::authorize('restore', $playlistModel);

        $playlistModel->restore();

        if ($playlistModel->status === PlaylistStatus::Archived) {
            $playlistModel->update(['status' => PlaylistStatus::Active]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playlist restaurada.')]);

        return to_route('playlists.show', $playlistModel);
    }

    /** @return array<string, bool> */
    private function permissions(?Playlist $playlist = null): array
    {
        $playlistModel = $playlist ?? new Playlist;

        return [
            'can_create' => auth()->user()?->can('create', Playlist::class) ?? false,
            'can_update' => auth()->user()?->can('update', $playlistModel) ?? false,
            'can_delete' => auth()->user()?->can('delete', $playlistModel) ?? false,
            'can_restore' => auth()->user()?->can('restore', $playlistModel) ?? false,
        ];
    }

    /** @return array<string, bool> */
    private function playlistTopPermissions(): array
    {
        return [
            'can_attach' => auth()->user()?->can('attach', PlaylistTop::class) ?? false,
            'can_configure' => auth()->user()?->can('configure', new PlaylistTop) ?? false,
            'can_detach' => auth()->user()?->can('detach', new PlaylistTop) ?? false,
        ];
    }

    private function dispatchSpotifySync(Playlist $playlist, string $trigger): void
    {
        if (! is_string($playlist->spotify_playlist_id) || $playlist->spotify_playlist_id === '') {
            return;
        }

        if ($trigger !== 'created' && $playlist->spotify_last_synced_at !== null) {
            return;
        }

        SyncPlaylistFromSpotifyJob::dispatch($playlist->id, $trigger)->afterCommit();
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'statuses' => PlaylistStatus::options(),
            'can_create_music_catalog' => auth()->user()?->can('create', Playlist::class) ?? false,
            'can_update_music_catalog' => auth()->user()?->can('update', new Playlist) ?? false,
            'genres' => MusicGenreResource::collection(
                MusicGenre::query()
                    ->with(['subgenres' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
            )->resolve(),
            'subgenres' => MusicSubgenreResource::collection(
                MusicSubgenre::query()
                    ->with(['genres:id'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
            )->resolve(),
        ];
    }
}
