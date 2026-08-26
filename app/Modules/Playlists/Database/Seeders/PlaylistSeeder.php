<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Seeders;

use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use App\Modules\Uploads\Models\Uploaded;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PlaylistSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MusicCatalogSeeder::class,
            TopSeeder::class,
        ]);

        foreach ($this->playlists() as $playlistData) {
            $genre = MusicGenre::query()->where('slug', $playlistData['music_genre_slug'])->firstOrFail();

            $subgenreIds = MusicSubgenre::query()
                ->whereIn('slug', $playlistData['subgenre_slugs'])
                ->pluck('id')
                ->all();

            $genre->subgenres()->syncWithoutDetaching($subgenreIds);

            $coverUploadData = $playlistData['spotify_cover_upload'];
            $coverAsset = $playlistData['spotify_cover_asset'] ?? null;
            unset(
                $playlistData['music_genre_slug'],
                $playlistData['subgenre_slugs'],
                $playlistData['spotify_cover_upload'],
                $playlistData['spotify_cover_asset'],
            );

            $playlist = Playlist::query()
                ->withTrashed()
                ->updateOrCreate(
                    ['slug' => $playlistData['slug']],
                    [
                        ...$playlistData,
                        'music_genre_id' => $genre->id,
                    ],
                );

            if ($playlist->trashed()) {
                $playlist->restore();
            }

            $playlist->subgenres()->sync($subgenreIds);

            if (is_string($coverAsset) && $coverAsset !== '') {
                $coverUpload = $this->seedSpotifyCoverFromAsset($playlist, $coverAsset);
                $playlist->forceFill(['spotify_cover_upload_id' => $coverUpload->id])->save();
            } elseif (is_array($coverUploadData)) {
                $coverUpload = $this->seedSpotifyCoverUpload($playlist, $coverUploadData);
                $playlist->forceFill(['spotify_cover_upload_id' => $coverUpload->id])->save();
            }

            $this->seedPlaylistTops($playlist);
        }
    }

    private function seedPlaylistTops(Playlist $playlist): void
    {
        $playlistTopData = $this->playlistTops();

        Top::query()
            ->whereIn('slug', array_keys($playlistTopData))
            ->get()
            ->each(function (Top $top) use ($playlist, $playlistTopData): void {
                $data = $playlistTopData[$top->slug];

                $playlistTop = PlaylistTop::query()
                    ->withTrashed()
                    ->updateOrCreate(
                        [
                            'playlist_id' => $playlist->id,
                            'top_id' => $top->id,
                        ],
                        [
                            'slots_quantity' => $data['slots_quantity'],
                            'occupied_slots_count' => 0,
                            'price' => $data['price'],
                            'currency' => 'BRL',
                            'metrics' => $data['metrics'],
                            'notes' => $data['notes'],
                            'is_active' => true,
                        ],
                    );

                if ($playlistTop->trashed()) {
                    $playlistTop->restore();
                }
            });
    }

    /**
     * @param  array<string, mixed>  $coverUploadData
     */
    private function seedSpotifyCoverUpload(Playlist $playlist, array $coverUploadData): Uploaded
    {
        $coverUpload = Uploaded::query()
            ->withTrashed()
            ->updateOrCreate(
                ['path' => $coverUploadData['path']],
                [
                    ...$coverUploadData,
                    'parentable_type' => Playlist::class,
                    'parentable_id' => $playlist->id,
                ],
            );

        if ($coverUpload->trashed()) {
            $coverUpload->restore();
        }

        return $coverUpload;
    }

    private function seedSpotifyCoverFromAsset(Playlist $playlist, string $assetFileName): Uploaded
    {
        $sourcePath = database_path('seeders/assets/playlist-covers/'.$assetFileName);

        if (! is_file($sourcePath)) {
            throw new RuntimeException("Arquivo de capa ausente: {$assetFileName}");
        }

        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw new RuntimeException("Não foi possível ler a capa: {$assetFileName}");
        }

        $extension = pathinfo($assetFileName, PATHINFO_EXTENSION) ?: 'jpg';
        $disk = (string) config('uploads.disk', 'local');
        $path = sprintf('uploads/playlist/%s/spotify-covers/seed/%s', $playlist->uuid, $assetFileName);

        Storage::disk($disk)->put($path, $contents);

        $coverUpload = Uploaded::query()
            ->withTrashed()
            ->updateOrCreate(
                ['path' => $path],
                [
                    'user_id' => null,
                    'uploader_id' => null,
                    'parentable_type' => Playlist::class,
                    'parentable_id' => $playlist->id,
                    'name' => 'Capa Spotify',
                    'file_name' => $assetFileName,
                    'original_name' => 'spotify-cover.'.$extension,
                    'mime_type' => 'image/jpeg',
                    'extension' => $extension,
                    'disk' => $disk,
                    'file_hash' => hash('sha256', $contents),
                    'size' => strlen($contents),
                    'tag' => 'spotify-cover',
                    'collection' => 'images',
                    'is_cover' => true,
                    'is_favorite' => false,
                    'source' => 'spotify',
                    'uploaded_images' => null,
                ],
            );

        if ($coverUpload->trashed()) {
            $coverUpload->restore();
        }

        return $coverUpload;
    }

    /**
     * @return array<int, array{
     *     uuid: string,
     *     music_genre_slug: string,
     *     subgenre_slugs: array<int, string>,
     *     name: string,
     *     slug: string,
     *     status: PlaylistStatus,
     *     partner_name: ?string,
     *     spotify_playlist_id: string,
     *     spotify_url: string,
     *     spotify_name: ?string,
     *     spotify_description: ?string,
     *     spotify_public: ?bool,
     *     spotify_image_url: ?string,
     *     spotify_cover_upload_id: ?int,
     *     spotify_snapshot_id: ?string,
     *     spotify_owner_id: ?string,
     *     spotify_owner_name: ?string,
     *     spotify_tracks_total: ?int,
     *     youtube_url: ?string,
     *     followers_count: ?int,
     *     estimated_reach: ?int,
     *     external_metadata: ?array<string, string>,
     *     notes: ?string,
     *     spotify_cover_upload: ?array<string, mixed>,
     *     spotify_cover_asset: ?string
     * }>
     */
    private function playlists(): array
    {
        return [
            [
                'uuid' => '54603fb2-17a3-4f6b-9f7b-00e174c7d55c',
                'music_genre_slug' => 'mpb',
                'subgenre_slugs' => ['mpb-antigas'],
                'name' => 'MPB Antigas BestPlay',
                'slug' => 'mpb-antigas-bestplay',
                'status' => PlaylistStatus::Active,
                'partner_name' => 'Curadoria BestPlay',
                'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
                'spotify_url' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W',
                'spotify_name' => 'Top Brasil',
                'spotify_description' => null,
                'spotify_public' => null,
                'spotify_image_url' => null,
                'spotify_cover_upload_id' => null,
                'spotify_snapshot_id' => null,
                'spotify_owner_id' => null,
                'spotify_owner_name' => null,
                'spotify_tracks_total' => null,
                'youtube_url' => null,
                'followers_count' => 0,
                'estimated_reach' => 0,
                'external_metadata' => [
                    'legacy_spotify_id' => '19',
                    'legacy_spotify_key' => '1',
                    'source' => 'old_bestplay',
                ],
                'notes' => 'Playlist inicial criada a partir da referência comercial antiga.',
                'spotify_cover_upload' => null,
            ],
            [
                'uuid' => '43ffa979-0734-451b-abbf-3a9533a2321a',
                'music_genre_slug' => 'eletronica',
                'subgenre_slugs' => ['deep-house'],
                'name' => 'Playlist Corida - para bater os 10km',
                'slug' => 'playlist-corida-para-bater-os-10km',
                'status' => PlaylistStatus::Active,
                'partner_name' => null,
                'spotify_playlist_id' => '5bNqE1YOKGpbylAsxAiNtC',
                'spotify_url' => 'https://open.spotify.com/playlist/5bNqE1YOKGpbylAsxAiNtC',
                'spotify_name' => 'Playlist Corida - para bater os 10km',
                'spotify_description' => 'hehe',
                'spotify_public' => true,
                'spotify_image_url' => 'https://image-cdn-fa.spotifycdn.com/image/ab67706c0000da847d39801ed0f93a931e8523eb',
                'spotify_cover_upload_id' => null,
                'spotify_snapshot_id' => 'AAAAI9b/qonsfbvAQJtcwLKxj7vv1C0Q',
                'spotify_owner_id' => 'gabrielvgobbi',
                'spotify_owner_name' => 'gabrielvgobbi',
                'spotify_tracks_total' => 67,
                'youtube_url' => null,
                'followers_count' => 1,
                'estimated_reach' => null,
                'external_metadata' => null,
                'notes' => null,
                'spotify_cover_upload' => [
                    'user_id' => null,
                    'uploader_id' => null,
                    'name' => 'Capa Spotify',
                    'file_name' => '5c57529f-86e5-4233-bc1f-e9f701027a29.jpg',
                    'original_name' => 'spotify-cover.jpg',
                    'mime_type' => 'image/jpeg',
                    'path' => 'uploads/playlist/43ffa979-0734-451b-abbf-3a9533a2321a/spotify-covers/2026/06/5c57529f-86e5-4233-bc1f-e9f701027a29.jpg',
                    'extension' => 'jpg',
                    'disk' => 'local',
                    'file_hash' => '477e3e14ca237096ccfd41686a11dffa1f64f5f6b0b40f741c4c886813ee325b',
                    'size' => 36740,
                    'tag' => 'spotify-cover',
                    'collection' => 'images',
                    'is_cover' => true,
                    'is_favorite' => false,
                    'source' => 'spotify',
                    'uploaded_images' => null,
                ],
            ],
            [
                'uuid' => 'c8f3a1d4-7e2b-4c91-9a55-2d8f6b1e4c70',
                'music_genre_slug' => 'indie',
                'subgenre_slugs' => ['lo-fi'],
                'name' => 'Estudando / Trabalhando',
                'slug' => 'estudando-trabalhando',
                'status' => PlaylistStatus::Active,
                'partner_name' => 'Curadoria BestPlay',
                'spotify_playlist_id' => '6TDC4Xu4LxuAVS3OvfQSkw',
                'spotify_url' => 'https://open.spotify.com/playlist/6TDC4Xu4LxuAVS3OvfQSkw',
                'spotify_name' => 'Estudando / Trabalhando',
                'spotify_description' => null,
                'spotify_public' => true,
                'spotify_image_url' => 'https://mosaic.scdn.co/640/ab67616d00001e0224ec4c21ab7fafcd54036691ab67616d00001e0229b94c1a0668cc1cb6d61b95ab67616d00001e02966f02ecbe9cc4f203ad3c55ab67616d00001e02af634982d9b15de3c77f7dd9',
                'spotify_cover_upload_id' => null,
                'spotify_snapshot_id' => 'AAAAB93T3n+WPIY6QHNvuv8+mszfETCp',
                'spotify_owner_id' => 'gabrielvgobbi',
                'spotify_owner_name' => 'gabrielvgobbi',
                'spotify_tracks_total' => 5,
                'youtube_url' => null,
                'followers_count' => 0,
                'estimated_reach' => null,
                'external_metadata' => null,
                'notes' => null,
                'spotify_cover_upload' => null,
                'spotify_cover_asset' => '6TDC4Xu4LxuAVS3OvfQSkw.jpg',
            ],
            [
                'uuid' => 'a7e9b2c1-5d44-4f08-8b33-1c6a9e0f2d81',
                'music_genre_slug' => 'pop',
                'subgenre_slugs' => ['pop-internacional'],
                'name' => 'Playlist compartilhada',
                'slug' => 'playlist-compartilhada',
                'status' => PlaylistStatus::Active,
                'partner_name' => 'Curadoria BestPlay',
                'spotify_playlist_id' => '1ezfKhGgDGFzXZLTwRWUHc',
                'spotify_url' => 'https://open.spotify.com/playlist/1ezfKhGgDGFzXZLTwRWUHc',
                'spotify_name' => null,
                'spotify_description' => null,
                'spotify_public' => null,
                'spotify_image_url' => null,
                'spotify_cover_upload_id' => null,
                'spotify_snapshot_id' => null,
                'spotify_owner_id' => null,
                'spotify_owner_name' => null,
                'spotify_tracks_total' => null,
                'youtube_url' => null,
                'followers_count' => 0,
                'estimated_reach' => null,
                'external_metadata' => null,
                'notes' => 'Cadastrada pelo link compartilhado. O Spotify não liberou metadados públicos para esta credencial.',
                'spotify_cover_upload' => null,
                'spotify_cover_asset' => null,
            ],
            [
                'uuid' => 'b4d1c8e7-3a90-4e12-9f66-0e5b7d2a8c93',
                'music_genre_slug' => 'eletronica',
                'subgenre_slugs' => ['edm'],
                'name' => 'No céu tem supino',
                'slug' => 'no-ceu-tem-supino',
                'status' => PlaylistStatus::Active,
                'partner_name' => 'Curadoria BestPlay',
                'spotify_playlist_id' => '4gPDsOsNtnfzlJNCzt6qLX',
                'spotify_url' => 'https://open.spotify.com/playlist/4gPDsOsNtnfzlJNCzt6qLX',
                'spotify_name' => 'No céu tem supino 🏅🏋️🙏',
                'spotify_description' => null,
                'spotify_public' => true,
                'spotify_image_url' => 'https://mosaic.scdn.co/640/ab67616d00001e020f4e6125fb1be367bee6c9f5ab67616d00001e0214bd9a6b6aff4fbf9b166986ab67616d00001e022803df6496300a18cbadc589ab67616d00001e02a43273c6be8d0667d3732391',
                'spotify_cover_upload_id' => null,
                'spotify_snapshot_id' => 'AAAACuwBEOr6GKKYT/1ARyop33fnlpQd',
                'spotify_owner_id' => 'gabrielvgobbi',
                'spotify_owner_name' => 'gabrielvgobbi',
                'spotify_tracks_total' => 9,
                'youtube_url' => null,
                'followers_count' => 0,
                'estimated_reach' => null,
                'external_metadata' => null,
                'notes' => null,
                'spotify_cover_upload' => null,
                'spotify_cover_asset' => '4gPDsOsNtnfzlJNCzt6qLX.jpg',
            ],
            [
                'uuid' => 'd2f6e0a9-8c17-4b25-a144-3f9c1e7b5d04',
                'music_genre_slug' => 'pop',
                'subgenre_slugs' => ['pop-nacional'],
                'name' => 'Para ouvir no carro',
                'slug' => 'para-ouvir-no-carro',
                'status' => PlaylistStatus::Active,
                'partner_name' => 'Curadoria BestPlay',
                'spotify_playlist_id' => '0oBDLzQcjnIvZcOXT2YTAk',
                'spotify_url' => 'https://open.spotify.com/playlist/0oBDLzQcjnIvZcOXT2YTAk',
                'spotify_name' => 'Para ouvir no carro',
                'spotify_description' => null,
                'spotify_public' => true,
                'spotify_image_url' => 'https://mosaic.scdn.co/640/ab67616d00001e02003f81b1f4246362638e682dab67616d00001e028265a736a1eb838ad5a0b921ab67616d00001e029a7e719f37e9264656b95997ab67616d00001e02f36a4e2e1687e678f29328cb',
                'spotify_cover_upload_id' => null,
                'spotify_snapshot_id' => 'AAAADF7sKfu/vtfJrQFVf01nW1TMDWvL',
                'spotify_owner_id' => 'gabrielvgobbi',
                'spotify_owner_name' => 'gabrielvgobbi',
                'spotify_tracks_total' => 8,
                'youtube_url' => null,
                'followers_count' => 0,
                'estimated_reach' => null,
                'external_metadata' => null,
                'notes' => null,
                'spotify_cover_upload' => null,
                'spotify_cover_asset' => '0oBDLzQcjnIvZcOXT2YTAk.jpg',
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     slots_quantity: int,
     *     price: int,
     *     metrics: array{
     *         estimated_streams: int,
     *         estimated_reach: int,
     *         average_retention_days: int,
     *         commercial_score: int
     *     },
     *     notes: string
     * }>
     */
    private function playlistTops(): array
    {
        return [
            'top-1' => $this->playlistTop(1, 249900, 25000, 45000, 30, 95, 'TOP 1 premium para campanha prioritária.'),
            'top-5' => $this->playlistTop(5, 159900, 18000, 32000, 21, 88, 'TOP 5 comercial para alta exposição.'),
            'top-10' => $this->playlistTop(10, 99900, 12000, 24000, 14, 80, 'TOP 10 padrão para entrada comercial.'),
            'top-20' => $this->playlistTop(20, 59900, 7000, 16000, 14, 70, 'TOP 20 para campanhas de alcance inicial.'),
            'destaque' => $this->playlistTop(1, 79900, 9000, 18000, 7, 76, 'Destaque editorial da playlist.'),
        ];
    }

    /**
     * @return array{
     *     slots_quantity: int,
     *     price: int,
     *     metrics: array{
     *         estimated_streams: int,
     *         estimated_reach: int,
     *         average_retention_days: int,
     *         commercial_score: int
     *     },
     *     notes: string
     * }
     */
    private function playlistTop(
        int $slotsQuantity,
        int $price,
        int $estimatedStreams,
        int $estimatedReach,
        int $averageRetentionDays,
        int $commercialScore,
        string $notes,
    ): array {
        return [
            'slots_quantity' => $slotsQuantity,
            'price' => $price,
            'metrics' => [
                'estimated_streams' => $estimatedStreams,
                'estimated_reach' => $estimatedReach,
                'average_retention_days' => $averageRetentionDays,
                'commercial_score' => $commercialScore,
            ],
            'notes' => $notes,
        ];
    }
}
