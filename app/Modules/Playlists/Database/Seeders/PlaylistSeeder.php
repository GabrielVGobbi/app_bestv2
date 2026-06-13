<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Seeders;

use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Database\Seeder;

class PlaylistSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MusicCatalogSeeder::class);

        $mpb = MusicGenre::query()->updateOrCreate(
            ['slug' => 'mpb'],
            [
                'name' => 'MPB',
                'is_active' => true,
            ],
        );

        $mpbAntigas = MusicSubgenre::query()->updateOrCreate(
            [
                'slug' => 'mpb-antigas',
            ],
            [
                'name' => 'MPB Antigas',
                'is_active' => true,
            ],
        );
        $mpb->subgenres()->syncWithoutDetaching([$mpbAntigas->id]);

        $playlist = Playlist::query()
            ->withTrashed()
            ->updateOrCreate(
                ['slug' => 'mpb-antigas-bestplay'],
                [
                    'music_genre_id' => $mpb->id,
                    'name' => 'MPB Antigas BestPlay',
                    'status' => PlaylistStatus::Active,
                    'partner_name' => 'Curadoria BestPlay',
                    'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
                    'spotify_url' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W',
                    'youtube_url' => null,
                    'followers_count' => 0,
                    'estimated_reach' => 0,
                    'external_metadata' => [
                        'legacy_spotify_id' => '19',
                        'legacy_spotify_key' => '1',
                        'source' => 'old_bestplay',
                    ],
                    'notes' => 'Playlist inicial criada a partir da referência comercial antiga.',
                ],
            );

        if ($playlist->trashed()) {
            $playlist->restore();
        }

        $playlist->subgenres()->sync([$mpbAntigas->id]);
    }
}
