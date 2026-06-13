<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Seeders;

use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MusicCatalogSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $catalog = [
        'Pop' => [
            'Pop Nacional',
            'Pop Internacional',
            'Dance Pop',
            'Electropop',
            'Indie Pop',
            'K-Pop',
            'Pop Rock',
            'Synth Pop',
        ],
        'Rock' => [
            'Rock Nacional',
            'Rock Internacional',
            'Classic Rock',
            'Hard Rock',
            'Indie Rock',
            'Pop Rock',
            'Punk Rock',
            'Rock Alternativo',
        ],
        'Hip Hop' => [
            'Rap Nacional',
            'Rap Internacional',
            'Trap',
            'Boom Bap',
            'Drill',
            'R&B',
            'Lo-Fi Hip Hop',
            'Funk Melody',
        ],
        'Funk' => [
            'Funk Carioca',
            'Funk Paulista',
            'Funk Melody',
            'Funk Ostentação',
            'Funk 150 BPM',
            'Funk Consciente',
        ],
        'Sertanejo' => [
            'Sertanejo Universitário',
            'Sertanejo Raiz',
            'Sertanejo Romântico',
            'Modão',
            'Arrocha',
            'Piseiro',
        ],
        'Eletrônica' => [
            'House',
            'Deep House',
            'Tech House',
            'Techno',
            'Trance',
            'Drum and Bass',
            'EDM',
            'Electropop',
        ],
        'MPB' => [
            'MPB Antigas',
            'Nova MPB',
            'Bossa Nova',
            'Samba',
            'Pagode',
            'Forró',
            'Axé',
        ],
        'Indie' => [
            'Indie Pop',
            'Indie Rock',
            'Alternative',
            'Dream Pop',
            'Shoegaze',
            'Lo-Fi',
        ],
        'Reggae' => [
            'Reggae Roots',
            'Reggae Nacional',
            'Dancehall',
            'Dub',
            'Ska',
        ],
        'Gospel' => [
            'Gospel Nacional',
            'Worship',
            'Louvor',
            'Gospel Pop',
            'Gospel Rap',
        ],
        'Jazz e Blues' => [
            'Jazz',
            'Blues',
            'Soul',
            'Bossa Nova',
            'R&B',
            'Smooth Jazz',
        ],
        'Latina' => [
            'Reggaeton',
            'Latin Pop',
            'Salsa',
            'Bachata',
            'Cumbia',
        ],
    ];

    public function run(): void
    {
        foreach ($this->catalog as $genreName => $subgenreNames) {
            $genre = MusicGenre::query()->updateOrCreate(
                ['slug' => Str::slug($genreName)],
                [
                    'name' => $genreName,
                    'is_active' => true,
                ],
            );

            $subgenreIds = collect($subgenreNames)
                ->map(function (string $subgenreName): int {
                    return MusicSubgenre::query()->updateOrCreate(
                        ['slug' => Str::slug($subgenreName)],
                        [
                            'name' => $subgenreName,
                            'is_active' => true,
                        ],
                    )->id;
                })
                ->all();

            $genre->subgenres()->syncWithoutDetaching($subgenreIds);
        }
    }
}
