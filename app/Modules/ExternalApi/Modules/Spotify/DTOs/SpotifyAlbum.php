<?php

namespace App\Modules\ExternalApi\Modules\Spotify\DTOs;

use App\Modules\ExternalApi\Core\Support\ArrayData;

class SpotifyAlbum
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $uri,
        public readonly string $albumType,
        public readonly ?string $releaseDate,
        public readonly int $totalTracks,
        public readonly array $artists,
        public readonly array $images,
        public readonly ?string $spotifyUrl,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: ArrayData::string($data, 'id'),
            name: ArrayData::string($data, 'name'),
            uri: ArrayData::string($data, 'uri'),
            albumType: ArrayData::string($data, 'album_type'),
            releaseDate: ArrayData::nullableString($data, 'release_date'),
            totalTracks: ArrayData::int($data, 'total_tracks'),
            artists: array_map(fn (array $artist): SpotifyArtist => SpotifyArtist::fromArray($artist), ArrayData::listOfArrays($data, 'artists')),
            images: ArrayData::listOfArrays($data, 'images'),
            spotifyUrl: ArrayData::externalUrl($data),
        );
    }

    public function toArray(): array
    {
        return [
            ...get_object_vars($this),
            'artists' => array_map(fn (SpotifyArtist $artist): array => $artist->toArray(), $this->artists),
        ];
    }
}
