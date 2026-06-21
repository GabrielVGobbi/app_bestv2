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
            unset($playlistData['music_genre_slug'], $playlistData['subgenre_slugs'], $playlistData['spotify_cover_upload']);

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

            if (is_array($coverUploadData)) {
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
     *     spotify_cover_upload: ?array<string, mixed>
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
