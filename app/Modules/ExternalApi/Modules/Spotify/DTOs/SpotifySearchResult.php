<?php

namespace App\Modules\ExternalApi\Modules\Spotify\DTOs;

class SpotifySearchResult
{
    public function __construct(
        public readonly array $items,
        public readonly string $type,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'items' => array_map(
                fn (object $item): array => method_exists($item, 'toArray') ? $item->toArray() : get_object_vars($item),
                $this->items
            ),
        ];
    }
}
