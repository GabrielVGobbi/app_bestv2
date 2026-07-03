<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Resources;

use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlaylistDeliveryTask */
class PlaylistDeliveryTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'playlist_slot_id' => $this->playlist_slot_id,
            'sale_item_id' => $this->sale_item_id,
            'sale_id' => $this->sale_id,
            'playlist_top_id' => $this->playlist_top_id,
            'playlist_id' => $this->playlist_id,
            'type' => $this->type->value,
            'type_presenter' => $this->type->toPresenterArray(),
            'status' => $this->status->value,
            'status_presenter' => $this->status->toPresenterArray(),
            'sale_order_number' => $this->sale_order_number,
            'client_name' => $this->client_name,
            'playlist_name' => $this->playlist_name,
            'top_name' => $this->top_name,
            'from_track_spotify_id' => $this->from_track_spotify_id,
            'from_track_spotify_url' => $this->from_track_spotify_url,
            'from_track_name' => $this->from_track_name,
            'from_track_artists' => $this->from_track_artists,
            'from_track_album_name' => $this->from_track_album_name,
            'from_track_duration_ms' => $this->from_track_duration_ms,
            'from_track_isrc' => $this->from_track_isrc,
            'from_track_image_url' => $this->from_track_image_url,
            'to_track_spotify_id' => $this->to_track_spotify_id,
            'to_track_spotify_url' => $this->to_track_spotify_url,
            'to_track_name' => $this->to_track_name,
            'to_track_artists' => $this->to_track_artists,
            'to_track_album_name' => $this->to_track_album_name,
            'to_track_duration_ms' => $this->to_track_duration_ms,
            'to_track_isrc' => $this->to_track_isrc,
            'to_track_image_url' => $this->to_track_image_url,
            'track' => $this->operationalTrack(),
            'from_track' => [
                'spotify_id' => $this->from_track_spotify_id,
                'spotify_url' => $this->from_track_spotify_url,
                'name' => $this->from_track_name,
                'artists' => $this->from_track_artists,
                'album_name' => $this->from_track_album_name,
                'duration_ms' => $this->from_track_duration_ms,
                'isrc' => $this->from_track_isrc,
                'image_url' => $this->from_track_image_url,
            ],
            'to_track' => [
                'spotify_id' => $this->to_track_spotify_id,
                'spotify_url' => $this->to_track_spotify_url,
                'name' => $this->to_track_name,
                'artists' => $this->to_track_artists,
                'album_name' => $this->to_track_album_name,
                'duration_ms' => $this->to_track_duration_ms,
                'isrc' => $this->to_track_isrc,
                'image_url' => $this->to_track_image_url,
            ],
            'sale' => $this->whenLoaded('sale', fn (): ?array => $this->sale === null ? null : [
                'id' => $this->sale->id,
                'uuid' => $this->sale->uuid,
                'order_number' => $this->sale->order_number,
                'title' => $this->sale->title,
            ]),
            'playlist' => $this->whenLoaded('playlist', fn (): ?array => $this->playlist === null ? null : [
                'id' => $this->playlist->id,
                'uuid' => $this->playlist->uuid,
                'name' => $this->playlist->name,
                'spotify_url' => $this->playlist->spotify_url,
            ]),
            'playlist_top' => $this->whenLoaded('playlistTop', fn (): ?array => $this->playlistTop === null ? null : [
                'id' => $this->playlistTop->id,
                'uuid' => $this->playlistTop->uuid,
                'name' => $this->playlistTop->top?->name,
                'playlist_name' => $this->playlistTop->playlist?->name,
            ]),
            'completed_at' => $this->completed_at,
            'completed_by_id' => $this->completed_by_id,
            'canceled_at' => $this->canceled_at,
            'canceled_by_id' => $this->canceled_by_id,
            'cancellation_reason' => $this->cancellation_reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    private function operationalTrack(): array
    {
        if ($this->type->value === 'remove_track') {
            return [
                'spotify_id' => $this->from_track_spotify_id,
                'spotify_url' => $this->from_track_spotify_url,
                'name' => $this->from_track_name,
                'artists' => $this->from_track_artists,
                'album_name' => $this->from_track_album_name,
                'duration_ms' => $this->from_track_duration_ms,
                'isrc' => $this->from_track_isrc,
                'image_url' => $this->from_track_image_url,
            ];
        }

        return [
            'spotify_id' => $this->to_track_spotify_id,
            'spotify_url' => $this->to_track_spotify_url,
            'name' => $this->to_track_name,
            'artists' => $this->to_track_artists,
            'album_name' => $this->to_track_album_name,
            'duration_ms' => $this->to_track_duration_ms,
            'isrc' => $this->to_track_isrc,
            'image_url' => $this->to_track_image_url,
        ];
    }
}
