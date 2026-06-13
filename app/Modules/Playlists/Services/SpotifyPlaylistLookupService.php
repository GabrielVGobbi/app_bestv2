<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Services;

use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SpotifyPlaylistLookupService
{
    public function __construct(
        private readonly SpotifyPlaylistOEmbedLookupService $oEmbedLookup,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $spotifyPlaylistId): array
    {
        $token = $this->accessToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.spotify.timeout', 10))
                ->connectTimeout((int) config('services.spotify.connect_timeout', 5))
                ->get($this->baseUrl().'/playlists/'.$spotifyPlaylistId, [
                    'fields' => 'id,name,external_urls,followers(total),owner(id,display_name),tracks(total),snapshot_id,public',
                ]);
        } catch (ConnectionException) {
            throw new SpotifyPlaylistLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
        }

        if ($response->status() === 404 || $response->status() === 403) {
            $fallbackPreview = $this->oEmbedLookup->preview($spotifyPlaylistId);

            if ($fallbackPreview !== null) {
                return $fallbackPreview;
            }

            throw new SpotifyPlaylistLookupException('A API do Spotify não liberou os dados desta playlist para esta credencial. Você ainda pode cadastrar manualmente pelo link e ID.');
        }

        if (! $response->successful()) {
            throw new SpotifyPlaylistLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['id'] ?? null) !== $spotifyPlaylistId) {
            throw new SpotifyPlaylistLookupException('O Spotify retornou dados inválidos para essa playlist.');
        }

        return [
            'spotify_playlist_id' => $payload['id'],
            'spotify_url' => $payload['external_urls']['spotify'] ?? 'https://open.spotify.com/playlist/'.$payload['id'],
            'name' => $payload['name'] ?? null,
            'followers_count' => $payload['followers']['total'] ?? null,
            'spotify_owner_id' => $payload['owner']['id'] ?? null,
            'spotify_owner_name' => $payload['owner']['display_name'] ?? null,
            'spotify_tracks_total' => $payload['tracks']['total'] ?? null,
            'spotify_snapshot_id' => $payload['snapshot_id'] ?? null,
            'public' => $payload['public'] ?? null,
            'source' => 'web_api',
            'is_partial' => false,
        ];
    }

    private function accessToken(): string
    {
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new SpotifyPlaylistLookupException('Configure as credenciais do Spotify antes de buscar a playlist.');
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
            throw new SpotifyPlaylistLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
        }

        if (! $response->successful()) {
            throw new SpotifyPlaylistLookupException('Não foi possível autenticar no Spotify agora.');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new SpotifyPlaylistLookupException('O Spotify não retornou um token de acesso válido.');
        }

        return $token;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.spotify.base_url', 'https://api.spotify.com/v1'), '/');
    }

    private function authUrl(): string
    {
        return rtrim((string) config('services.spotify.auth_url', 'https://accounts.spotify.com'), '/');
    }
}
