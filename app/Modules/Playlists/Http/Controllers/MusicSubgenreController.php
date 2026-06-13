<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Http\Requests\MusicSubgenre\UpdateMusicSubgenreRequest;
use App\Modules\Playlists\Http\Resources\MusicSubgenreResource;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MusicSubgenreController extends Controller
{
    public function update(UpdateMusicSubgenreRequest $request, MusicSubgenre $musicSubgenre): MusicSubgenreResource
    {
        $musicSubgenre->update([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
        ]);

        return new MusicSubgenreResource($musicSubgenre);
    }

    public function updateStatus(Request $request, MusicSubgenre $musicSubgenre): MusicSubgenreResource
    {
        Gate::authorize('update', new Playlist);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $musicSubgenre->update([
            'is_active' => $validated['is_active'],
        ]);

        return new MusicSubgenreResource($musicSubgenre);
    }
}
