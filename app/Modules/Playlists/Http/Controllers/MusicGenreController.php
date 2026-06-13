<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Http\Requests\MusicGenre\StoreMusicGenreRequest;
use App\Modules\Playlists\Http\Requests\MusicGenre\UpdateMusicGenreRequest;
use App\Modules\Playlists\Http\Requests\MusicSubgenre\StoreMusicSubgenreRequest;
use App\Modules\Playlists\Http\Resources\MusicGenreResource;
use App\Modules\Playlists\Http\Resources\MusicSubgenreResource;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MusicGenreController extends Controller
{
    public function store(StoreMusicGenreRequest $request): JsonResponse
    {
        $genre = MusicGenre::create([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
            'is_active' => true,
        ]);

        return response()->json([
            'data' => (new MusicGenreResource($genre->load('subgenres')))->resolve($request),
        ], 201);
    }

    public function update(UpdateMusicGenreRequest $request, MusicGenre $musicGenre): MusicGenreResource
    {
        $musicGenre->update([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
        ]);

        return new MusicGenreResource($musicGenre->load(['subgenres' => fn ($query) => $query->orderBy('name')]));
    }

    public function updateStatus(Request $request, MusicGenre $musicGenre): MusicGenreResource
    {
        Gate::authorize('update', new Playlist);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $musicGenre->update([
            'is_active' => $validated['is_active'],
        ]);

        return new MusicGenreResource($musicGenre->load(['subgenres' => fn ($query) => $query->orderBy('name')]));
    }

    public function storeSubgenre(StoreMusicSubgenreRequest $request, MusicGenre $musicGenre): JsonResponse
    {
        if (! $musicGenre->is_active) {
            throw ValidationException::withMessages([
                'music_genre_id' => 'Ative o gênero antes de criar subgêneros.',
            ]);
        }

        $subgenreId = $request->integer('music_subgenre_id') ?: null;

        $subgenre = $subgenreId !== null
            ? MusicSubgenre::query()->findOrFail($subgenreId)
            : MusicSubgenre::query()->create([
                'name' => $request->string('name')->toString(),
                'slug' => $request->string('slug')->toString(),
                'is_active' => true,
            ]);

        $musicGenre->subgenres()->syncWithoutDetaching([$subgenre->id]);

        return response()->json([
            'data' => (new MusicSubgenreResource($subgenre->load('genres')))->resolve($request),
        ], 201);
    }

    public function destroySubgenre(MusicGenre $musicGenre, MusicSubgenre $musicSubgenre): MusicGenreResource
    {
        Gate::authorize('update', new Playlist);

        $musicGenre->subgenres()->detach($musicSubgenre->id);

        return new MusicGenreResource($musicGenre->load(['subgenres' => fn ($query) => $query->orderBy('name')]));
    }
}
