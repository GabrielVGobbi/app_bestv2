<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Support;

class SpotifyPlaylistInputParser
{
    public function parse(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }

        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if ($this->isSpotifyId($input)) {
            return $input;
        }

        if (preg_match('/^spotify:playlist:([A-Za-z0-9]{22})$/', $input, $matches) === 1) {
            return $matches[1];
        }

        $segments = $this->urlSegments($input);

        if ($segments === null) {
            return null;
        }

        if (($segments[0] ?? null) !== 'playlist' || ! isset($segments[1])) {
            return null;
        }

        return $this->isSpotifyId($segments[1]) ? $segments[1] : null;
    }

    public function resourceType(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }

        $input = trim($input);

        if (preg_match('/^spotify:([a-z]+):[A-Za-z0-9]{22}$/', $input, $matches) === 1) {
            return $matches[1];
        }

        $segments = $this->urlSegments($input);

        return $segments[0] ?? null;
    }

    /** @return array<int, string>|null */
    private function urlSegments(string $input): ?array
    {
        $parts = parse_url($input);

        if (($parts['host'] ?? null) !== 'open.spotify.com') {
            return null;
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        $segments = explode('/', $path);

        if (preg_match('/^intl-[a-z]{2}$/', $segments[0] ?? '') === 1) {
            array_shift($segments);
        }

        return ($segments[0] ?? '') !== '' ? $segments : null;
    }

    private function isSpotifyId(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9]{22}$/', $value) === 1;
    }
}
