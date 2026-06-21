<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Playlists\Http\Resources\PlaylistSlotResource;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaleItem */
class SaleItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'sale_id' => $this->sale_id,
            'type' => $this->type->value,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status->value,
            'status_presenter' => $this->status->toPresenterArray(),
            'service_id' => $this->service_id,
            'playlist_top_id' => $this->playlist_top_id,
            'sellable_type' => $this->sellable_type,
            'sellable_id' => $this->sellable_id,
            'track_spotify_id' => $this->track_spotify_id,
            'track_spotify_url' => $this->track_spotify_url,
            'track_name' => $this->track_name,
            'track_artists' => $this->track_artists,
            'track_album_name' => $this->track_album_name,
            'track_duration_ms' => $this->track_duration_ms,
            'track_isrc' => $this->track_isrc,
            'track_image_url' => is_array($this->track_metadata) ? ($this->track_metadata['image_url'] ?? null) : null,
            'spotify_playlist_presence' => [
                'status' => $this->spotify_playlist_check_status->value,
                'status_presenter' => $this->spotify_playlist_check_status->toPresenterArray(),
                'checked_at' => $this->spotify_playlist_checked_at?->toISOString(),
                'track_position' => $this->spotify_playlist_track_position,
                'match_type' => $this->spotify_playlist_match_type,
                'playlist_id_checked' => $this->spotify_playlist_id_checked,
                'snapshot_id_checked' => $this->spotify_playlist_snapshot_id_checked,
                'error' => $this->spotify_playlist_check_error,
            ],
            'service' => $this->whenLoaded('service', fn (): ?array => $this->service === null ? null : [
                'id' => $this->service->id,
                'uuid' => $this->service->uuid,
                'name' => $this->service->name,
            ]),
            'playlist_top' => $this->whenLoaded('playlistTop', fn (): ?array => $this->playlistTop === null ? null : [
                'id' => $this->playlistTop->id,
                'uuid' => $this->playlistTop->uuid,
                'name' => $this->playlistTop->top?->name,
                'playlist_name' => $this->playlistTop->playlist?->name,
            ]),
            'sellable' => $this->whenLoaded('sellable', fn (): mixed => $this->sellable instanceof PlaylistSlot
                ? (new PlaylistSlotResource($this->sellable->loadMissing('playlistTop.playlist', 'playlistTop.top')))->resolve($request)
                : $this->sellable),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
