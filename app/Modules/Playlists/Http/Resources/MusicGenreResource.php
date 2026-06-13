<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Resources;

use App\Modules\Playlists\Models\MusicGenre;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MusicGenre */
class MusicGenreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'subgenres' => $this->whenLoaded(
                'subgenres',
                fn () => MusicSubgenreResource::collection($this->subgenres)->resolve($request),
                [],
            ),
        ];
    }
}
