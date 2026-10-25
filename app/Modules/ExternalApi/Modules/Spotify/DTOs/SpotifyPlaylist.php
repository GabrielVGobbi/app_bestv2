<?php

namespace App\Modules\ExternalApi\Modules\Spotify\DTOs;

use App\Modules\ExternalApi\Core\Support\ArrayData;

class SpotifyPlaylist
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $uri,
        public readonly ?string $description,
        public readonly bool $public,
        public readonly bool $collaborative,
        public readonly array $images,
        public readonly array $owner,
        public readonly int $tracksTotal,
        public readonly ?string $spotifyUrl,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: ArrayData::string($data, 'id'),
            name: ArrayData::string($data, 'name'),
            uri: ArrayData::string($data, 'uri'),
            description: ArrayData::nullableString($data, 'description'),
            public: ArrayData::bool($data, 'public'),
            collaborative: ArrayData::bool($data, 'collaborative'),
            images: ArrayData::listOfArrays($data, 'images'),
            owner: ArrayData::array($data, 'owner'),
            tracksTotal: ArrayData::int($data, 'tracks.total'),
            spotifyUrl: ArrayData::externalUrl($data),
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
