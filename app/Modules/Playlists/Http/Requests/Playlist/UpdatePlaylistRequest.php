<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\Playlist;

use App\Modules\Playlists\Models\Playlist;

class UpdatePlaylistRequest extends StorePlaylistRequest
{
    public function authorize(): bool
    {
        $playlist = $this->route('playlist');

        return $playlist instanceof Playlist
            && ($this->user()?->can('update', $playlist) ?? false);
    }
}
