<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Mocks;

class SpotifyMockData
{
    public static function response(string $method, string $endpoint, array $options = []): array
    {
        $path = trim(parse_url($endpoint, PHP_URL_PATH) ?: $endpoint, '/');

        return match (true) {
            $path === 'search' => self::search($options['query']['type'] ?? 'track'),
            str_starts_with($path, 'tracks/') => self::track(basename($path)),
            str_starts_with($path, 'artists/') => self::artist(basename($path)),
            str_starts_with($path, 'albums/') => self::album(basename($path)),
            str_starts_with($path, 'playlists/') => self::playlist(basename($path)),
            $path === 'me' => self::me(),
            default => [],
        };
    }

    public static function token(): array
    {
        return [
            'access_token' => 'mock_spotify_access_token',
            'refresh_token' => 'mock_spotify_refresh_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'user-read-email playlist-read-private playlist-read-collaborative',
        ];
    }

    public static function me(): array
    {
        return [
            'id' => 'mock-user',
            'display_name' => 'Mock Spotify User',
            'email' => 'mock.spotify@example.com',
        ];
    }

    public static function search(string $type): array
    {
        return match ($type) {
            'artist' => ['artists' => ['items' => [self::artist('mock-artist')]]],
            'album' => ['albums' => ['items' => [self::album('mock-album')]]],
            'playlist' => ['playlists' => ['items' => [self::playlist('mock-playlist')]]],
            default => ['tracks' => ['items' => [self::track('mock-track')]]],
        };
    }

    public static function track(string $id = 'mock-track'): array
    {
        return [
            'id' => $id,
            'name' => 'Mock Track',
            'uri' => "spotify:track:{$id}",
            'duration_ms' => 210000,
            'explicit' => false,
            'preview_url' => 'https://p.scdn.co/mp3-preview/mock',
            'external_urls' => ['spotify' => "https://open.spotify.com/track/{$id}"],
            'artists' => [self::artist('mock-artist')],
            'album' => self::album('mock-album'),
        ];
    }

    public static function artist(string $id = 'mock-artist'): array
    {
        return [
            'id' => $id,
            'name' => 'Mock Artist',
            'uri' => "spotify:artist:{$id}",
            'genres' => ['pop', 'indie'],
            'popularity' => 75,
            'external_urls' => ['spotify' => "https://open.spotify.com/artist/{$id}"],
            'images' => [['url' => 'https://i.scdn.co/image/mock-artist', 'width' => 640, 'height' => 640]],
        ];
    }

    public static function album(string $id = 'mock-album'): array
    {
        return [
            'id' => $id,
            'name' => 'Mock Album',
            'uri' => "spotify:album:{$id}",
            'album_type' => 'album',
            'release_date' => '2026-01-01',
            'total_tracks' => 12,
            'external_urls' => ['spotify' => "https://open.spotify.com/album/{$id}"],
            'images' => [['url' => 'https://i.scdn.co/image/mock-album', 'width' => 640, 'height' => 640]],
            'artists' => [self::artist('mock-artist')],
        ];
    }

    public static function playlist(string $id = 'mock-playlist'): array
    {
        return [
            'id' => $id,
            'name' => 'Mock Playlist',
            'uri' => "spotify:playlist:{$id}",
            'description' => 'A realistic mocked Spotify playlist.',
            'public' => true,
            'collaborative' => false,
            'external_urls' => ['spotify' => "https://open.spotify.com/playlist/{$id}"],
            'images' => [['url' => 'https://i.scdn.co/image/mock-playlist', 'width' => 640, 'height' => 640]],
            'owner' => ['id' => 'mock-user', 'display_name' => 'Mock Spotify User'],
            'tracks' => ['total' => 24],
        ];
    }
}
