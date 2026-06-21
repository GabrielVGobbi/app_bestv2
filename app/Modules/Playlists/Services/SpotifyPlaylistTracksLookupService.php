<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Services;

use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SpotifyPlaylistTracksLookupService
{
    /**
     * @return array{items: array<int, array<string, mixed>>, total: int|null, snapshot_id: string|null}
     */
    public function tracks(string $spotifyPlaylistId): array
    {
        $token = $this->accessToken();
        $items = [];
        $offset = 0;
        $total = null;
        $snapshotId = null;

        do {
            $payload = $this->fetchPage($token, $spotifyPlaylistId, $offset);
            $pageItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $total = is_numeric($payload['total'] ?? null) ? (int) $payload['total'] : $total;
            $snapshotId = is_string($payload['snapshot_id'] ?? null) ? $payload['snapshot_id'] : $snapshotId;

            foreach ($pageItems as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            $next = is_string($payload['next'] ?? null) && $payload['next'] !== '';
            $offset += 50;
        } while ($next && ($total === null || $offset < $total));

        return [
            'items' => $items,
            'total' => $total,
            'snapshot_id' => $snapshotId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPage(string $token, string $spotifyPlaylistId, int $offset): array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.spotify.timeout', 10))
                ->connectTimeout((int) config('services.spotify.connect_timeout', 5))
                ->get($this->baseUrl().'/playlists/'.$spotifyPlaylistId.'/tracks', [
                    'market' => 'BR',
                    'limit' => 50,
                    'offset' => $offset,
                    'fields' => 'items(track(id,name,external_urls,external_ids,artists(name),duration_ms)),next,total,limit,offset,snapshot_id',
                ]);
        } catch (ConnectionException) {
            throw new SpotifyPlaylistLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
        }

        if ($response->status() === 404 || $response->status() === 403) {
            throw new SpotifyPlaylistLookupException(
                'A API do Spotify não liberou as faixas desta playlist para esta credencial.',
                $response->status(),
            );
        }

        if (! $response->successful()) {
            if ($response->status() === 429) {
                throw new SpotifyPlaylistLookupException(
                    'O Spotify limitou temporariamente as consultas. Tente novamente depois.',
                    429,
                    $this->retryAfterSeconds($response->header('Retry-After')),
                );
            }

            throw new SpotifyPlaylistLookupException('Não foi possível consultar o Spotify agora. Tente novamente em instantes.', $response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SpotifyPlaylistLookupException('O Spotify retornou dados inválidos para esta playlist.');
        }

        return $payload;
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

    private function retryAfterSeconds(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(1, (int) $value);
    }
}
