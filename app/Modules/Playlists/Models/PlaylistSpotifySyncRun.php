<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'playlist_id',
    'playlist_spotify_snapshot_id',
    'trigger',
    'status',
    'error_message',
    'http_status',
    'started_at',
    'finished_at',
])]
class PlaylistSpotifySyncRun extends Model
{
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PlaylistSpotifySnapshot::class, 'playlist_spotify_snapshot_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
