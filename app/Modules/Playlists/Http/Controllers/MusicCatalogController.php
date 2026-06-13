<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Http\Resources\MusicGenreResource;
use App\Modules\Playlists\Http\Resources\MusicSubgenreResource;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MusicCatalogController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Playlist::class);

        return Inertia::render('app/playlists/music-catalog/index', [
            'genres' => MusicGenreResource::collection(
                MusicGenre::query()
                    ->with(['subgenres' => fn ($query) => $query->orderBy('name')])
                    ->orderBy('name')
                    ->get()
            ),
            'subgenres' => MusicSubgenreResource::collection(
                MusicSubgenre::query()
                    ->with(['genres:id'])
                    ->orderBy('name')
                    ->get()
            ),
            'permissions' => [
                'can_create' => auth()->user()?->can('create', Playlist::class) ?? false,
                'can_update' => auth()->user()?->can('update', new Playlist) ?? false,
            ],
        ]);
    }
}
