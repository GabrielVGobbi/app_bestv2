<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions;

use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistChangeEvent;
use App\Modules\Playlists\Models\PlaylistSpotifySnapshot;
use App\Modules\Playlists\Models\PlaylistSpotifySyncRun;
use App\Modules\Playlists\Services\SpotifyPlaylistLookupService;
use App\Modules\Uploads\Models\Uploaded;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncPlaylistFromSpotifyAction
{
    public function __construct(
        private readonly SpotifyPlaylistLookupService $lookup,
    ) {}

    /**
     * @return array{playlist: Playlist, snapshot: PlaylistSpotifySnapshot, events: array<int, PlaylistChangeEvent>, run: PlaylistSpotifySyncRun}
     */
    public function execute(Playlist $playlist, string $trigger = 'manual'): array
    {
        $run = PlaylistSpotifySyncRun::query()->create([
            'playlist_id' => $playlist->id,
            'trigger' => $trigger,
            'status' => 'running',
            'started_at' => now(),
        ]);

        if (! is_string($playlist->spotify_playlist_id) || $playlist->spotify_playlist_id === '') {
            return $this->markFailure($run, $playlist, 'Vincule um ID público do Spotify antes de sincronizar.');
        }

        try {
            $preview = $this->lookup->preview($playlist->spotify_playlist_id);
        } catch (SpotifyPlaylistLookupException $exception) {
            return $this->markFailure($run, $playlist, $exception->getMessage(), $exception);
        }

        return DB::transaction(function () use ($playlist, $preview, $run): array {
            $playlist->refresh();

            $previousFollowers = $playlist->followers_count;
            $imageUrl = $this->stringOrNull($preview['image_url'] ?? null);
            $coverUpload = $this->downloadCover($playlist, $imageUrl);

            $payload = [
                'spotify_url' => $this->stringOrNull($preview['spotify_url'] ?? null) ?? $playlist->spotify_url,
                'spotify_name' => $this->stringOrNull($preview['name'] ?? null),
                'spotify_description' => $this->stringOrNull($preview['description'] ?? null),
                'spotify_public' => is_bool($preview['public'] ?? null) ? $preview['public'] : null,
                'spotify_image_url' => $imageUrl,
                'spotify_cover_upload_id' => $coverUpload?->id ?? $playlist->spotify_cover_upload_id,
                'spotify_snapshot_id' => $this->stringOrNull($preview['spotify_snapshot_id'] ?? null),
                'spotify_owner_id' => $this->stringOrNull($preview['spotify_owner_id'] ?? null),
                'spotify_owner_name' => $this->stringOrNull($preview['spotify_owner_name'] ?? null),
                'spotify_tracks_total' => $this->integerOrNull($preview['spotify_tracks_total'] ?? null),
                'spotify_last_synced_at' => now(),
                'spotify_sync_interval_hours' => $this->syncIntervalHours($playlist),
                'next_spotify_sync_at' => $this->nextSyncAtAfterSuccess($playlist),
                'spotify_sync_failures_count' => 0,
                'followers_count' => $this->integerOrNull($preview['followers_count'] ?? null) ?? $playlist->followers_count,
            ];

            $snapshot = $playlist->spotifySnapshots()->create([
                'spotify_cover_upload_id' => $payload['spotify_cover_upload_id'],
                'followers_count' => $payload['followers_count'],
                'followers_delta' => $this->delta($previousFollowers, $payload['followers_count']),
                'followers_delta_percent' => $this->deltaPercent($previousFollowers, $payload['followers_count']),
                'spotify_name' => $payload['spotify_name'],
                'spotify_description' => $payload['spotify_description'],
                'spotify_owner_id' => $payload['spotify_owner_id'],
                'spotify_owner_name' => $payload['spotify_owner_name'],
                'spotify_tracks_total' => $payload['spotify_tracks_total'],
                'spotify_snapshot_id' => $payload['spotify_snapshot_id'],
                'spotify_public' => $payload['spotify_public'],
                'spotify_image_url' => $payload['spotify_image_url'],
                'spotify_images' => is_array($preview['images'] ?? null) ? $preview['images'] : [],
                'payload' => [
                    'source' => $preview['source'] ?? 'spotify',
                    'is_partial' => $preview['is_partial'] ?? false,
                ],
                'captured_at' => now(),
            ]);

            $events = $this->recordEvents($playlist, $snapshot, $payload);

            $playlist->update($payload);

            $run->update([
                'playlist_spotify_snapshot_id' => $snapshot->id,
                'status' => 'success',
                'finished_at' => now(),
            ]);

            return [
                'playlist' => $playlist->fresh(['genre', 'subgenres', 'spotifyCoverUpload']),
                'snapshot' => $snapshot->fresh() ?? $snapshot,
                'events' => $events,
                'run' => $run->fresh() ?? $run,
            ];
        });
    }

    /**
     * @return never
     */
    private function markFailure(
        PlaylistSpotifySyncRun $run,
        Playlist $playlist,
        string $message,
        ?SpotifyPlaylistLookupException $exception = null,
    ): array {
        $playlist->refresh();
        $failuresCount = ((int) $playlist->spotify_sync_failures_count) + 1;

        $playlist->forceFill([
            'spotify_sync_failures_count' => $failuresCount,
            'next_spotify_sync_at' => $this->nextSyncAtAfterFailure($playlist, $failuresCount, $exception?->retryAfterSeconds()),
        ])->save();

        $run->update([
            'status' => 'failed',
            'error_message' => $message,
            'http_status' => $exception?->httpStatus(),
            'finished_at' => now(),
        ]);

        throw new SpotifyPlaylistLookupException($message, $exception?->httpStatus(), $exception?->retryAfterSeconds());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, PlaylistChangeEvent>
     */
    private function recordEvents(Playlist $playlist, PlaylistSpotifySnapshot $snapshot, array $payload): array
    {
        $tracked = [
            'followers_count' => 'Seguidores',
            'spotify_name' => 'Nome no Spotify',
            'spotify_description' => 'Descrição no Spotify',
            'spotify_cover_upload_id' => 'Capa do Spotify',
        ];

        $events = [];

        foreach ($tracked as $field => $label) {
            $oldValue = $playlist->{$field};
            $newValue = $payload[$field] ?? null;

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $events[] = $playlist->changeEvents()->create([
                'playlist_spotify_snapshot_id' => $snapshot->id,
                'field' => $field === 'spotify_cover_upload_id' ? 'spotify_cover' : $field,
                'label' => $label,
                'old_value' => $oldValue === null ? null : (string) $oldValue,
                'new_value' => $newValue === null ? null : (string) $newValue,
                'source' => 'spotify',
                'occurred_at' => now(),
            ]);
        }

        return $events;
    }

    private function downloadCover(Playlist $playlist, ?string $imageUrl): ?Uploaded
    {
        if ($imageUrl === null) {
            return null;
        }

        if ($playlist->spotify_image_url === $imageUrl && $playlist->spotify_cover_upload_id !== null) {
            /** @var Uploaded|null $upload */
            $upload = Uploaded::query()->find($playlist->spotify_cover_upload_id);

            return $upload;
        }

        try {
            $response = Http::timeout(10)->connectTimeout(5)->get($imageUrl);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $disk = (string) config('uploads.disk', 'local');
        $extension = str_contains((string) $response->header('Content-Type'), 'png') ? 'png' : 'jpg';
        $fileName = (string) Str::uuid().'.'.$extension;
        $folder = sprintf('uploads/playlist/%s/spotify-covers/%s', $playlist->uuid, now()->format('Y/m'));
        $path = $folder.'/'.$fileName;

        Storage::disk($disk)->put($path, $response->body());

        Uploaded::query()
            ->where('parentable_type', Playlist::class)
            ->where('parentable_id', $playlist->id)
            ->where('is_cover', true)
            ->update(['is_cover' => false]);

        return Uploaded::query()->create([
            'user_id' => auth()->id(),
            'uploader_id' => auth()->id(),
            'parentable_type' => Playlist::class,
            'parentable_id' => $playlist->id,
            'name' => 'Capa Spotify',
            'file_name' => $fileName,
            'original_name' => 'spotify-cover.'.$extension,
            'mime_type' => $response->header('Content-Type') ?: 'image/jpeg',
            'path' => $path,
            'extension' => $extension,
            'disk' => $disk,
            'file_hash' => hash('sha256', $response->body()),
            'size' => strlen($response->body()),
            'tag' => 'spotify-cover',
            'collection' => 'images',
            'is_cover' => true,
            'source' => 'spotify',
        ]);
    }

    private function delta(?int $previous, ?int $current): ?int
    {
        if ($previous === null || $current === null) {
            return null;
        }

        return $current - $previous;
    }

    private function deltaPercent(?int $previous, ?int $current): ?float
    {
        if ($previous === null || $previous === 0 || $current === null) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    private function syncIntervalHours(Playlist $playlist): int
    {
        $configured = (int) $playlist->spotify_sync_interval_hours;

        if ($configured > 0) {
            return $configured;
        }

        return match ($playlist->spotify_sync_priority) {
            'high' => 6,
            'low' => 168,
            default => 24,
        };
    }

    private function nextSyncAtAfterSuccess(Playlist $playlist): ?CarbonInterface
    {
        if ($this->isAutomaticSyncPaused($playlist)) {
            return null;
        }

        return now()->addHours($this->syncIntervalHours($playlist));
    }

    private function nextSyncAtAfterFailure(Playlist $playlist, int $failuresCount, ?int $retryAfterSeconds): ?CarbonInterface
    {
        if ($this->isAutomaticSyncPaused($playlist)) {
            return null;
        }

        if ($retryAfterSeconds !== null) {
            return now()->addSeconds($retryAfterSeconds);
        }

        $backoffHours = min(24, max(1, 2 ** min($failuresCount - 1, 5)));

        return now()->addHours($backoffHours);
    }

    private function isAutomaticSyncPaused(Playlist $playlist): bool
    {
        return $playlist->spotify_sync_priority === 'paused' || $playlist->spotify_sync_paused_at !== null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
