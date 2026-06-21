<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Resources;

use App\Modules\Playlists\Models\PlaylistSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlaylistSlot */
class PlaylistSlotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'playlist_top_id' => $this->playlist_top_id,
            'sale_item_id' => $this->sale_item_id,
            'status' => $this->status->value,
            'status_presenter' => $this->status->toPresenterArray(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'quantity' => $this->quantity,
            'reserved_until' => $this->reserved_until,
            'track_spotify_id' => $this->track_spotify_id,
            'track_spotify_url' => $this->track_spotify_url,
            'track_name' => $this->track_name,
            'track_artists' => $this->track_artists,
            'track_album_name' => $this->track_album_name,
            'notes' => $this->notes,
            'canceled_at' => $this->canceled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'playlist_top' => $this->whenLoaded('playlistTop', fn (): ?array => $this->playlistTop === null ? null : [
                'id' => $this->playlistTop->id,
                'uuid' => $this->playlistTop->uuid,
                'name' => $this->playlistTop->top?->name,
                'playlist_name' => $this->playlistTop->playlist?->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
