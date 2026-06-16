<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Playlists\Actions\SyncPlaylistFromSpotifyAction;
use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SyncSpotifyPlaylistsCommand extends Command
{
    protected $signature = 'playlists:sync-spotify
        {--playlist= : UUID da playlist específica}
        {--limit=100 : Quantidade máxima de playlists no lote}
        {--stale-hours=24 : Compatibilidade: sincroniza playlists sem next sync e com última sync antiga}
        {--priority= : Filtra por prioridade: high, normal, low ou paused}
        {--force : Ignora filtros de vencimento e pausa}';

    protected $description = 'Sincroniza dados públicos do Spotify para playlists com controle de custo e histórico.';

    public function handle(SyncPlaylistFromSpotifyAction $sync): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $staleHours = max(1, (int) $this->option('stale-hours'));
        $playlistUuid = $this->option('playlist');
        $priority = $this->option('priority');
        $force = (bool) $this->option('force');

        if (is_string($priority) && $priority !== '' && ! in_array($priority, ['high', 'normal', 'low', 'paused'], true)) {
            $this->error('Prioridade inválida. Use high, normal, low ou paused.');

            return self::FAILURE;
        }

        $query = Playlist::query()
            ->whereNotNull('spotify_playlist_id')
            ->where('spotify_playlist_id', '!=', '')
            ->when(is_string($playlistUuid) && $playlistUuid !== '', fn (Builder $query): Builder => $query->where('uuid', $playlistUuid))
            ->when(is_string($priority) && $priority !== '', fn (Builder $query): Builder => $query->where('spotify_sync_priority', $priority))
            ->when(! $force, function (Builder $query) use ($staleHours): Builder {
                return $query
                    ->whereNull('spotify_sync_paused_at')
                    ->where('spotify_sync_priority', '!=', 'paused')
                    ->where(function (Builder $query) use ($staleHours): void {
                        $query->whereNull('next_spotify_sync_at')
                            ->orWhere('next_spotify_sync_at', '<=', now())
                            ->orWhere(function (Builder $query) use ($staleHours): void {
                                $query->whereNull('next_spotify_sync_at')
                                    ->where(function (Builder $query) use ($staleHours): void {
                                        $query->whereNull('spotify_last_synced_at')
                                            ->orWhere('spotify_last_synced_at', '<=', now()->subHours($staleHours));
                                    });
                            });
                    });
            })
            ->orderByRaw("case spotify_sync_priority when 'high' then 0 when 'normal' then 1 when 'low' then 2 else 3 end")
            ->orderByRaw('next_spotify_sync_at is null desc')
            ->orderBy('next_spotify_sync_at')
            ->orderBy('spotify_last_synced_at')
            ->limit($limit);

        $processed = 0;
        $failed = 0;

        $query->get()->each(function (Playlist $playlist) use ($sync, &$processed, &$failed): void {
            try {
                $sync->execute($playlist, 'scheduled');
                $processed++;
                $this->line("Sincronizada: {$playlist->name}");
            } catch (SpotifyPlaylistLookupException $exception) {
                $failed++;
                $this->warn("Falha em {$playlist->name}: {$exception->getMessage()}");
            }
        });

        $this->info("Playlists sincronizadas: {$processed}. Falhas: {$failed}.");

        return self::SUCCESS;
    }
}
