<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Resources;

use App\Modules\Playlists\Models\PlaylistTop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlaylistTop */
class PlaylistTopResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'playlist_id' => $this->playlist_id,
            'top_id' => $this->top_id,
            'top' => $this->whenLoaded(
                'top',
                fn () => new TopResource($this->top),
                null,
            ),
            'slots_quantity' => $this->slots_quantity,
            'occupied_slots_count' => $this->occupied_slots_count,
            'available_slots_count' => $this->availableSlotsCount(),
            'price' => $this->price,
            'currency' => $this->currency,
            'metrics' => $this->metrics,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
