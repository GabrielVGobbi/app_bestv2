<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Exceptions\SpotifyTrackLookupException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SpotifyTrackLookupService
{
    /** @return array<string, mixed> */
    public function preview(string $spotifyTrackId): array
    {
        $token = $this->accessToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.spotify.timeout', 10))
                ->connectTimeout((int) config('services.spotify.connect_timeout', 5))
                ->get($this->baseUrl().'/tracks/'.$spotifyTrackId, [
                    'market' => 'BR',
                ]);
        } catch (ConnectionException) {
            throw new SpotifyTrackLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
        }

        if ($response->status() === 404 || $response->status() === 403) {
            throw new SpotifyTrackLookupException('O Spotify não encontrou essa música. Você ainda pode preencher os dados manualmente.');
        }

        if (! $response->successful()) {
            if ($response->status() === 429) {
                throw new SpotifyTrackLookupException(
                    'O Spotify limitou temporariamente as consultas. Tente novamente depois.',
                    429,
                    $this->retryAfterSeconds($response->header('Retry-After')),
                );
            }

            throw new SpotifyTrackLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['id'] ?? null) !== $spotifyTrackId) {
            throw new SpotifyTrackLookupException('O Spotify retornou dados inválidos para essa música.');
        }

        $artistNames = collect($payload['artists'] ?? [])
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();
        $trackName = is_string($payload['name'] ?? null) ? $payload['name'] : null;

        return [
            'spotify_track_id' => $payload['id'],
            'spotify_url' => $payload['external_urls']['spotify'] ?? 'https://open.spotify.com/track/'.$payload['id'],
            'name' => $trackName,
            'display_name' => $this->displayName($trackName, $artistNames),
            'artist_names' => $artistNames,
            'album_name' => $payload['album']['name'] ?? null,
            'image_url' => is_array($payload['album']['images'] ?? null) ? ($payload['album']['images'][0]['url'] ?? null) : null,
            'duration_ms' => $payload['duration_ms'] ?? null,
            'isrc' => $payload['external_ids']['isrc'] ?? null,
            'source' => 'web_api',
        ];
    }

    private function accessToken(): string
    {
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new SpotifyTrackLookupException('Configure as credenciais do Spotify antes de buscar a música.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout((int) config('services.spotify.timeout', 10))
                ->connectTimeout((int) config('services.spotify.connect_timeout', 5))
                ->retry([100, 300], throw: false)
                ->post($this->authUrl().'/api/token', [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException) {
            throw new SpotifyTrackLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
        }

        if (! $response->successful()) {
            throw new SpotifyTrackLookupException('Não foi possível autenticar no Spotify agora.');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new SpotifyTrackLookupException('O Spotify não retornou um token de acesso válido.');
        }

        return $token;
    }

    /** @param  array<int, string>  $artistNames */
    private function displayName(?string $trackName, array $artistNames): ?string
    {
        if ($trackName === null || $trackName === '') {
            return null;
        }

        if ($artistNames === []) {
            return $trackName;
        }

        return $trackName.' - '.implode(', ', $artistNames);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.spotify.base_url', 'https://api.spotify.com/v1'), '/');
    }

    private function authUrl(): string
    {
        return rtrim((string) config('services.spotify.auth_url', 'https://accounts.spotify.com'), '/');
    }

    private function retryAfterSeconds(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(1, (int) $value);
    }
}
