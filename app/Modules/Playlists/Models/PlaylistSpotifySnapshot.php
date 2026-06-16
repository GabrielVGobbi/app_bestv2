<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Models;

use App\Modules\Uploads\Models\Uploaded;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'playlist_id',
    'spotify_cover_upload_id',
    'followers_count',
    'followers_delta',
    'followers_delta_percent',
    'spotify_name',
    'spotify_description',
    'spotify_owner_id',
    'spotify_owner_name',
    'spotify_tracks_total',
    'spotify_snapshot_id',
    'spotify_public',
    'spotify_image_url',
    'spotify_images',
    'payload',
    'captured_at',
])]
class PlaylistSpotifySnapshot extends Model
{
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function coverUpload(): BelongsTo
    {
        return $this->belongsTo(Uploaded::class, 'spotify_cover_upload_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'followers_count' => 'integer',
            'followers_delta' => 'integer',
            'followers_delta_percent' => 'decimal:4',
            'spotify_tracks_total' => 'integer',
            'spotify_public' => 'boolean',
            'spotify_images' => 'array',
            'payload' => 'array',
            'captured_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
