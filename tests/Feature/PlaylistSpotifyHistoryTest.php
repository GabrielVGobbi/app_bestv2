<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Uploads\Models\Uploaded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlaylistSpotifyHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_sync_records_snapshot_events_and_keeps_local_playlist_name(): void
    {
        config([
            'services.spotify.client_id' => 'client-id',
            'services.spotify.client_secret' => 'client-secret',
        ]);

        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake([
            'accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'fake-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'api.spotify.com/v1/playlists/37i9dQZF1DX0FOF1IUWK1W*' => Http::response([
                'id' => '37i9dQZF1DX0FOF1IUWK1W',
                'name' => 'Nome oficial Spotify',
                'description' => 'Descrição oficial sincronizada.',
                'external_urls' => [
                    'spotify' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W',
                ],
                'followers' => ['total' => 1500],
                'owner' => ['id' => 'bestplay', 'display_name' => 'BestPlay Curadoria'],
                'tracks' => ['total' => 82],
                'snapshot_id' => 'snapshot-a',
                'public' => true,
                'images' => [
                    [
                        'url' => 'https://i.scdn.co/image/cover-a',
                        'width' => 640,
                        'height' => 640,
                    ],
                ],
            ]),
            'i.scdn.co/image/cover-a' => Http::response('fake-image-content', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $playlist = Playlist::factory()->for(MusicGenre::factory(), 'genre')->create([
            'name' => 'Nome comercial local',
            'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
            'followers_count' => 1000,
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.update']))
            ->patchJson(route('playlists.spotify.sync', $playlist))
            ->assertOk()
            ->assertJsonPath('message', 'Dados do Spotify sincronizados.')
            ->assertJsonPath('data.name', 'Nome comercial local')
            ->assertJsonPath('data.spotify_name', 'Nome oficial Spotify')
            ->assertJsonPath('data.spotify_description', 'Descrição oficial sincronizada.')
            ->assertJsonPath('data.followers_count', 1500)
            ->assertJsonPath('snapshot.followers_delta', 500)
            ->assertJsonCount(4, 'events');

        $playlist->refresh();

        $this->assertSame('Nome comercial local', $playlist->name);
        $this->assertSame('Nome oficial Spotify', $playlist->spotify_name);
        $this->assertSame('Descrição oficial sincronizada.', $playlist->spotify_description);
        $this->assertTrue($playlist->spotify_public);
        $this->assertSame(1500, $playlist->followers_count);
        $this->assertNotNull($playlist->spotify_cover_upload_id);

        $this->assertDatabaseHas('playlist_spotify_sync_runs', [
            'playlist_id' => $playlist->id,
            'trigger' => 'manual',
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('playlist_spotify_snapshots', [
            'playlist_id' => $playlist->id,
            'followers_count' => 1500,
            'followers_delta' => 500,
            'spotify_name' => 'Nome oficial Spotify',
            'spotify_description' => 'Descrição oficial sincronizada.',
            'spotify_image_url' => 'https://i.scdn.co/image/cover-a',
        ]);

        $this->assertDatabaseHas('playlist_change_events', [
            'playlist_id' => $playlist->id,
            'field' => 'followers_count',
            'old_value' => '1000',
            'new_value' => '1500',
            'source' => 'spotify',
        ]);

        $upload = Uploaded::query()->whereKey($playlist->spotify_cover_upload_id)->firstOrFail();

        $this->assertSame(Playlist::class, $upload->parentable_type);
        $this->assertSame($playlist->id, $upload->parentable_id);
        $this->assertSame('spotify-cover', $upload->tag);
        $this->assertSame('images', $upload->collection);
        $this->assertTrue($upload->is_cover);
        Storage::disk($upload->disk)->assertExists($upload->path);
    }

    public function test_second_sync_records_negative_delta_and_command_skips_fresh_playlists(): void
    {
        config([
            'services.spotify.client_id' => 'client-id',
            'services.spotify.client_secret' => 'client-secret',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'fake-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'api.spotify.com/v1/playlists/37i9dQZF1DX0FOF1IUWK1W*' => Http::response([
                'id' => '37i9dQZF1DX0FOF1IUWK1W',
                'name' => 'Playlist Spotify',
                'description' => null,
                'external_urls' => ['spotify' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W'],
                'followers' => ['total' => 900],
                'owner' => ['id' => 'bestplay', 'display_name' => 'BestPlay Curadoria'],
                'tracks' => ['total' => 80],
                'snapshot_id' => 'snapshot-b',
                'public' => true,
                'images' => [],
            ]),
        ]);

        $playlist = Playlist::factory()->for(MusicGenre::factory(), 'genre')->create([
            'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
            'followers_count' => 1000,
            'spotify_last_synced_at' => now()->subDays(2),
        ]);

        $this->artisan('playlists:sync-spotify', [
            '--playlist' => $playlist->uuid,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('playlist_spotify_snapshots', [
            'playlist_id' => $playlist->id,
            'followers_count' => 900,
            'followers_delta' => -100,
        ]);

        $playlist->refresh();

        Http::fake([
            'accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'fake-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'api.spotify.com/v1/playlists/*' => Http::response([], 500),
        ]);

        $this->artisan('playlists:sync-spotify', [
            '--playlist' => $playlist->uuid,
            '--stale-hours' => 24,
        ])->assertExitCode(0);

        $this->assertSame(1, $playlist->spotifySnapshots()->count());
    }

    public function test_scheduled_sync_uses_priority_queue_and_updates_next_sync_after_success(): void
    {
        config([
            'services.spotify.client_id' => 'client-id',
            'services.spotify.client_secret' => 'client-secret',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'fake-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'api.spotify.com/v1/playlists/playlist-due-high*' => Http::response([
                'id' => 'playlist-due-high',
                'name' => 'Playlist prioritária',
                'description' => null,
                'external_urls' => ['spotify' => 'https://open.spotify.com/playlist/playlist-due-high'],
                'followers' => ['total' => 1200],
                'owner' => ['id' => 'bestplay', 'display_name' => 'BestPlay Curadoria'],
                'tracks' => ['total' => 80],
                'snapshot_id' => 'snapshot-priority',
                'public' => true,
                'images' => [],
            ]),
            'api.spotify.com/v1/playlists/playlist-not-due*' => Http::response([], 500),
        ]);

        $due = Playlist::factory()->for(MusicGenre::factory(), 'genre')->create([
            'name' => 'Playlist due high',
            'spotify_playlist_id' => 'playlist-due-high',
            'spotify_sync_priority' => 'high',
            'spotify_sync_interval_hours' => 6,
            'next_spotify_sync_at' => now()->subMinute(),
            'spotify_sync_failures_count' => 2,
        ]);
        $notDue = Playlist::factory()->for(MusicGenre::factory(), 'genre')->create([
            'name' => 'Playlist not due',
            'spotify_playlist_id' => 'playlist-not-due',
            'spotify_sync_priority' => 'high',
            'spotify_sync_interval_hours' => 6,
            'next_spotify_sync_at' => now()->addHour(),
        ]);

        $this->artisan('playlists:sync-spotify', [
            '--priority' => 'high',
            '--limit' => 10,
        ])->assertExitCode(0);

        $due->refresh();
        $notDue->refresh();

        $this->assertSame(0, $due->spotify_sync_failures_count);
        $this->assertNotNull($due->next_spotify_sync_at);
        $this->assertTrue($due->next_spotify_sync_at->between(now()->addHours(5)->addMinutes(55), now()->addHours(6)->addMinutes(5)));
        $this->assertSame(1, $due->spotifySnapshots()->count());
        $this->assertSame(0, $notDue->spotifySnapshots()->count());
    }

    public function test_rate_limited_spotify_response_sets_backoff_from_retry_after(): void
    {
        config([
            'services.spotify.client_id' => 'client-id',
            'services.spotify.client_secret' => 'client-secret',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'fake-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'api.spotify.com/v1/playlists/playlist-rate-limited*' => Http::response([], 429, [
                'Retry-After' => '600',
            ]),
        ]);

        $playlist = Playlist::factory()->for(MusicGenre::factory(), 'genre')->create([
            'spotify_playlist_id' => 'playlist-rate-limited',
            'spotify_sync_priority' => 'normal',
            'spotify_sync_interval_hours' => 24,
            'next_spotify_sync_at' => now()->subMinute(),
            'spotify_sync_failures_count' => 0,
        ]);

        $this->artisan('playlists:sync-spotify', [
            '--playlist' => $playlist->uuid,
            '--force' => true,
        ])->assertExitCode(0);

        $playlist->refresh();

        $this->assertSame(1, $playlist->spotify_sync_failures_count);
        $this->assertNotNull($playlist->next_spotify_sync_at);
        $this->assertTrue($playlist->next_spotify_sync_at->between(now()->addMinutes(9), now()->addMinutes(11)));
        $this->assertDatabaseHas('playlist_spotify_sync_runs', [
            'playlist_id' => $playlist->id,
            'status' => 'failed',
            'http_status' => 429,
        ]);
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create();

        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $slug): int => Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace('.', ' ')->title()->toString(),
                    'group' => str($slug)->before('.')->toString(),
                ],
            )->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        $user->load(['roles', 'permissions']);

        return $user;
    }
}
