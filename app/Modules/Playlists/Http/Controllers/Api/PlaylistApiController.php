<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Http\Requests\Playlist\StorePlaylistRequest;
use App\Modules\Playlists\Http\Requests\Playlist\UpdatePlaylistRequest;
use App\Modules\Playlists\Http\Resources\PlaylistResource;
use App\Modules\Playlists\Models\Playlist;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PlaylistApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Playlist::class);

        return ApiDisplay::for(Playlist::class, PlaylistResource::class)
            ->usingQuery(Playlist::query()->withCatalog())
            ->paginate($request);
    }

    public function store(StorePlaylistRequest $request): PlaylistResource
    {
        $playlist = Playlist::create($request->playlistPayload());
        $playlist->subgenres()->sync($request->subgenreIds());

        return new PlaylistResource($playlist->load(['genre', 'subgenres']));
    }

    public function show(Playlist $playlist): PlaylistResource
    {
        Gate::authorize('view', $playlist);

        return new PlaylistResource($playlist->load(['genre', 'subgenres']));
    }

    public function update(UpdatePlaylistRequest $request, Playlist $playlist): PlaylistResource
    {
        $playlist->update($request->playlistPayload());
        $playlist->subgenres()->sync($request->subgenreIds());

        return new PlaylistResource($playlist->load(['genre', 'subgenres']));
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        Gate::authorize('delete', $playlist);

        $playlist->update(['status' => PlaylistStatus::Archived]);
        $playlist->delete();

        return response()->json(null, 204);
    }

    public function restore(string $playlist): PlaylistResource
    {
        $playlistModel = Playlist::withTrashed()->whereUuid($playlist)->firstOrFail();

        Gate::authorize('restore', $playlistModel);

        $playlistModel->restore();

        if ($playlistModel->status === PlaylistStatus::Archived) {
            $playlistModel->update(['status' => PlaylistStatus::Active]);
        }

        return new PlaylistResource($playlistModel->load(['genre', 'subgenres']));
    }
}
