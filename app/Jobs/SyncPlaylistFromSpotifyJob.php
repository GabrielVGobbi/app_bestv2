<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Playlists\Actions\SyncPlaylistFromSpotifyAction;
use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class SyncPlaylistFromSpotifyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $playlistId,
        public readonly string $trigger = 'scheduled',
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new RateLimited('spotify-playlist-sync'))->releaseAfter(60)];
    }

    public function handle(SyncPlaylistFromSpotifyAction $sync): void
    {
        $playlist = Playlist::query()->find($this->playlistId);

        if (! $playlist instanceof Playlist) {
            return;
        }

        try {
            $sync->execute($playlist, $this->trigger);
        } catch (SpotifyPlaylistLookupException) {
            return;
        }
    }
}
