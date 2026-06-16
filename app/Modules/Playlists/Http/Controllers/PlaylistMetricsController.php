<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Playlists\Http\Resources\MusicGenreResource;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistChangeEvent;
use App\Modules\Playlists\Models\PlaylistSpotifySnapshot;
use App\Modules\Playlists\Models\PlaylistSpotifySyncRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistMetricsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Playlist::class);

        $periodDays = max(1, min(365, (int) $request->integer('period_days', 30)));
        $from = now()->subDays($periodDays)->startOfDay();
        $search = trim((string) $request->string('search'));
        $genreId = $request->integer('music_genre_id') ?: null;
        $status = trim((string) $request->string('status'));

        $playlistQuery = Playlist::query()
            ->with(['genre', 'spotifyCoverUpload', 'latestSpotifySnapshot'])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('spotify_name', 'like', "%{$search}%")
                    ->orWhere('partner_name', 'like', "%{$search}%");
            }))
            ->when($genreId !== null, fn (Builder $query): Builder => $query->where('music_genre_id', $genreId))
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status));

        $playlists = $playlistQuery->get();
        $playlistIds = $playlists->pluck('id');

        $latestSnapshots = PlaylistSpotifySnapshot::query()
            ->whereIn('playlist_id', $playlistIds)
            ->where('captured_at', '>=', $from)
            ->latest('captured_at')
            ->get()
            ->unique('playlist_id')
            ->values();

        $series = PlaylistSpotifySnapshot::query()
            ->whereIn('playlist_id', $playlistIds)
            ->where('captured_at', '>=', $from)
            ->orderBy('captured_at')
            ->get()
            ->groupBy(fn (PlaylistSpotifySnapshot $snapshot): string => $snapshot->captured_at->format('Y-m-d'))
            ->map(fn ($items, string $date): array => [
                'date' => $date,
                'followers' => (int) $items->sum('followers_count'),
                'delta' => (int) $items->sum('followers_delta'),
            ])
            ->values()
            ->all();

        $rows = $playlists
            ->map(function (Playlist $playlist) use ($latestSnapshots): array {
                $snapshot = $latestSnapshots->firstWhere('playlist_id', $playlist->id);

                return [
                    'playlist_uuid' => $playlist->uuid,
                    'playlist_name' => $playlist->name,
                    'spotify_name' => $playlist->spotify_name,
                    'status' => $playlist->status->value,
                    'status_label' => $playlist->status->label(),
                    'genre' => $playlist->genre?->name,
                    'followers_count' => $playlist->followers_count ?? 0,
                    'followers_delta' => $snapshot?->followers_delta ?? 0,
                    'followers_delta_percent' => $snapshot?->followers_delta_percent,
                    'spotify_tracks_total' => $playlist->spotify_tracks_total,
                    'spotify_last_synced_at' => $playlist->spotify_last_synced_at?->toIso8601String(),
                    'spotify_sync_priority' => $playlist->spotify_sync_priority,
                    'next_spotify_sync_at' => $playlist->next_spotify_sync_at?->toIso8601String(),
                    'spotify_sync_failures_count' => $playlist->spotify_sync_failures_count,
                    'cover_url' => $playlist->spotifyCoverUpload
                        ? route('uploads.preview', ['upload' => $playlist->spotifyCoverUpload->id])
                        : $playlist->spotify_image_url,
                ];
            })
            ->sortByDesc('followers_count')
            ->values();

        $rising = $rows->filter(fn (array $row): bool => $row['followers_delta'] > 0)
            ->sortByDesc('followers_delta')
            ->take(10)
            ->values()
            ->all();
        $falling = $rows->filter(fn (array $row): bool => $row['followers_delta'] < 0)
            ->sortBy('followers_delta')
            ->take(10)
            ->values()
            ->all();

        $events = PlaylistChangeEvent::query()
            ->with('playlist:id,uuid,name')
            ->whereIn('playlist_id', $playlistIds)
            ->where('occurred_at', '>=', $from)
            ->latest('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (PlaylistChangeEvent $event): array => [
                'id' => $event->id,
                'playlist_uuid' => $event->playlist?->uuid,
                'playlist_name' => $event->playlist?->name,
                'field' => $event->field,
                'label' => $event->label,
                'old_value' => $event->old_value,
                'new_value' => $event->new_value,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])
            ->all();

        $failedSyncCount = PlaylistSpotifySyncRun::query()
            ->whereIn('playlist_id', $playlistIds)
            ->where('status', 'failed')
            ->where('started_at', '>=', $from)
            ->count();
        $dueSyncCount = $playlists
            ->filter(fn (Playlist $playlist): bool => is_string($playlist->spotify_playlist_id)
                && $playlist->spotify_playlist_id !== ''
                && $playlist->spotify_sync_paused_at === null
                && $playlist->spotify_sync_priority !== 'paused'
                && ($playlist->next_spotify_sync_at === null || $playlist->next_spotify_sync_at->isPast()))
            ->count();

        return Inertia::render('app/playlists/metrics/index', [
            'dashboard' => [
                'summary' => [
                    'total_followers' => (int) $playlists->sum('followers_count'),
                    'net_growth' => (int) $latestSnapshots->sum('followers_delta'),
                    'rising_count' => count($rising),
                    'falling_count' => count($falling),
                    'failed_sync_count' => $failedSyncCount,
                    'due_sync_count' => $dueSyncCount,
                ],
                'series' => $series,
                'rankings' => [
                    'rising' => $rising,
                    'falling' => $falling,
                ],
                'rows' => $rows->all(),
                'events' => $events,
            ],
            'filters' => [
                'period_days' => $periodDays,
                'search' => $search,
                'music_genre_id' => $genreId,
                'status' => $status,
                'genres' => MusicGenreResource::collection(
                    MusicGenre::query()->where('is_active', true)->orderBy('name')->get()
                )->resolve(),
            ],
        ]);
    }
}
