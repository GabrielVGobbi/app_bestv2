<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Items;

use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use App\Modules\Playlists\Services\SpotifyPlaylistTracksLookupService;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SpotifyPlaylistPresenceStatus;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Validation\ValidationException;

class CheckSaleItemSpotifyPresence
{
    public function __construct(
        private readonly SpotifyPlaylistTracksLookupService $spotifyPlaylistTracks,
    ) {}

    public function handle(Sale $sale, SaleItem $saleItem): SaleItem
    {
        if ((int) $saleItem->sale_id !== (int) $sale->id) {
            abort(403);
        }

        if ($saleItem->type !== SaleItemType::PlaylistSlot) {
            throw ValidationException::withMessages([
                'sale_item' => __('A verificação Spotify só está disponível para vagas de playlist.'),
            ]);
        }

        $saleItem->loadMissing('playlistTop.playlist');
        $playlist = $saleItem->playlistTop?->playlist;
        $spotifyPlaylistId = $playlist?->spotify_playlist_id;

        if (! is_string($spotifyPlaylistId) || $spotifyPlaylistId === '') {
            $saleItem->forceFill([
                'spotify_playlist_check_status' => SpotifyPlaylistPresenceStatus::PlaylistMissingId,
                'spotify_playlist_checked_at' => now(),
                'spotify_playlist_id_checked' => null,
                'spotify_playlist_snapshot_id_checked' => null,
                'spotify_playlist_check_error' => __('A playlist não possui ID do Spotify configurado.'),
            ])->save();

            return $saleItem;
        }

        try {
            $playlistTracks = $this->spotifyPlaylistTracks->tracks($spotifyPlaylistId);
        } catch (SpotifyPlaylistLookupException $exception) {
            $saleItem->forceFill([
                'spotify_playlist_check_status' => SpotifyPlaylistPresenceStatus::SpotifyUnavailable,
                'spotify_playlist_checked_at' => now(),
                'spotify_playlist_id_checked' => $spotifyPlaylistId,
                'spotify_playlist_snapshot_id_checked' => $playlist?->spotify_snapshot_id,
                'spotify_playlist_check_error' => $exception->getMessage(),
            ])->save();

            return $saleItem;
        }

        $match = $this->findTrackMatch(
            $playlistTracks['items'],
            $saleItem->track_spotify_id,
            $saleItem->track_isrc,
        );

        $saleItem->forceFill([
            'spotify_playlist_check_status' => $match === null
                ? SpotifyPlaylistPresenceStatus::Missing
                : SpotifyPlaylistPresenceStatus::Present,
            'spotify_playlist_checked_at' => now(),
            'spotify_playlist_track_position' => $match['position'] ?? null,
            'spotify_playlist_match_type' => $match['match_type'] ?? null,
            'spotify_playlist_id_checked' => $spotifyPlaylistId,
            'spotify_playlist_snapshot_id_checked' => $playlistTracks['snapshot_id'] ?? $playlist?->spotify_snapshot_id,
            'spotify_playlist_check_error' => null,
        ])->save();

        return $saleItem;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{position: int, match_type: string}|null
     */
    private function findTrackMatch(array $items, ?string $spotifyTrackId, ?string $isrc): ?array
    {
        foreach ($items as $index => $item) {
            $track = is_array($item['track'] ?? null) ? $item['track'] : null;

            if ($track === null) {
                continue;
            }

            if (is_string($spotifyTrackId) && $spotifyTrackId !== '' && ($track['id'] ?? null) === $spotifyTrackId) {
                return [
                    'position' => $index + 1,
                    'match_type' => 'track_id',
                ];
            }
        }

        foreach ($items as $index => $item) {
            $track = is_array($item['track'] ?? null) ? $item['track'] : null;
            $trackIsrc = is_array($track) ? ($track['external_ids']['isrc'] ?? null) : null;

            if (is_string($isrc) && $isrc !== '' && $trackIsrc === $isrc) {
                return [
                    'position' => $index + 1,
                    'match_type' => 'isrc',
                ];
            }
        }

        return null;
    }
}
