<?php

namespace App\Modules\ExternalApi\Modules\Spotify\DTOs;

use App\Modules\ExternalApi\Core\Support\ArrayData;

class SpotifyTrack
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $uri,
        public readonly int $durationMs,
        public readonly bool $explicit,
        public readonly array $artists,
        public readonly ?SpotifyAlbum $album,
        public readonly ?string $previewUrl,
        public readonly ?string $spotifyUrl,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: ArrayData::string($data, 'id'),
            name: ArrayData::string($data, 'name'),
            uri: ArrayData::string($data, 'uri'),
            durationMs: ArrayData::int($data, 'duration_ms'),
            explicit: ArrayData::bool($data, 'explicit'),
            artists: array_map(fn (array $artist): SpotifyArtist => SpotifyArtist::fromArray($artist), ArrayData::listOfArrays($data, 'artists')),
            album: is_array($data['album'] ?? null) ? SpotifyAlbum::fromArray($data['album']) : null,
            previewUrl: ArrayData::nullableString($data, 'preview_url'),
            spotifyUrl: ArrayData::externalUrl($data),
        );
    }

    public function toArray(): array
    {
        return [
            ...get_object_vars($this),
            'artists' => array_map(fn (SpotifyArtist $artist): array => $artist->toArray(), $this->artists),
            'album' => $this->album?->toArray(),
        ];
    }
}
