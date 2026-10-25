<?php

namespace App\Modules\ExternalApi\Modules\Spotify\DTOs;

use App\Modules\ExternalApi\Core\Support\ArrayData;

class SpotifyArtist
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $uri,
        public readonly array $genres,
        public readonly int $popularity,
        public readonly array $images,
        public readonly ?string $spotifyUrl,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: ArrayData::string($data, 'id'),
            name: ArrayData::string($data, 'name'),
            uri: ArrayData::string($data, 'uri'),
            genres: array_values(array_filter(ArrayData::array($data, 'genres'), fn (mixed $genre): bool => is_string($genre))),
            popularity: ArrayData::int($data, 'popularity'),
            images: ArrayData::listOfArrays($data, 'images'),
            spotifyUrl: ArrayData::externalUrl($data),
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
