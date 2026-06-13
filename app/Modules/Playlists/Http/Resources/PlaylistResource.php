<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Resources;

use App\Modules\Playlists\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Playlist */
class PlaylistResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'music_genre_id' => $this->music_genre_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'partner_name' => $this->partner_name,
            'spotify_playlist_id' => $this->spotify_playlist_id,
            'spotify_url' => $this->spotify_url,
            'spotify_snapshot_id' => $this->spotify_snapshot_id,
            'spotify_owner_id' => $this->spotify_owner_id,
            'spotify_owner_name' => $this->spotify_owner_name,
            'spotify_tracks_total' => $this->spotify_tracks_total,
            'spotify_last_synced_at' => $this->spotify_last_synced_at,
            'youtube_url' => $this->youtube_url,
            'followers_count' => $this->followers_count,
            'estimated_reach' => $this->estimated_reach,
            'external_metadata' => $this->external_metadata,
            'notes' => $this->notes,
            'genre' => $this->whenLoaded(
                'genre',
                fn () => new MusicGenreResource($this->genre),
                null,
            ),
            'subgenres' => $this->whenLoaded(
                'subgenres',
                fn () => MusicSubgenreResource::collection($this->subgenres)->resolve($request),
                [],
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
