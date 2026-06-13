<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaylistSpotifyPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_requires_playlist_create_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('playlists.spotify.preview'), [
                'spotify_input' => '37i9dQZF1DX0FOF1IUWK1W',
            ])
            ->assertForbidden();
    }

    public function test_preview_returns_normalized_spotify_payload(): void
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
                'name' => 'MPB Antigas BestPlay',
                'external_urls' => [
                    'spotify' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W',
                ],
                'followers' => [
                    'total' => 12500,
                ],
                'owner' => [
                    'id' => 'bestplay',
                    'display_name' => 'BestPlay Curadoria',
                ],
                'tracks' => [
                    'total' => 83,
                ],
                'snapshot_id' => 'snapshot-123',
                'public' => true,
            ]),
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.create']))
            ->postJson(route('playlists.spotify.preview'), [
                'spotify_input' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W?si=abc123',
            ])
            ->assertOk()
            ->assertJsonPath('data.spotify_playlist_id', '37i9dQZF1DX0FOF1IUWK1W')
            ->assertJsonPath('data.spotify_url', 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W')
            ->assertJsonPath('data.name', 'MPB Antigas BestPlay')
            ->assertJsonPath('data.followers_count', 12500)
            ->assertJsonPath('data.spotify_owner_id', 'bestplay')
            ->assertJsonPath('data.spotify_owner_name', 'BestPlay Curadoria')
            ->assertJsonPath('data.spotify_tracks_total', 83)
            ->assertJsonPath('data.spotify_snapshot_id', 'snapshot-123')
            ->assertJsonPath('data.public', true)
            ->assertJsonPath('data.source', 'web_api')
            ->assertJsonPath('data.is_partial', false);
    }

    public function test_preview_returns_duplicate_playlist_error_before_spotify_lookup(): void
    {
        Http::preventStrayRequests();

        $genre = MusicGenre::factory()->create();

        Playlist::factory()->for($genre, 'genre')->create([
            'name' => 'Playlist Corrida',
            'spotify_playlist_id' => '5bNqE1YOKGpbylAsxAiNtC',
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.create']))
            ->postJson(route('playlists.spotify.preview'), [
                'spotify_input' => 'https://open.spotify.com/playlist/5bNqE1YOKGpbylAsxAiNtC',
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'Esta playlist já está cadastrada.')
            ->assertJsonPath('errors.spotify_input.0', 'Esta playlist já está cadastrada.')
            ->assertJsonPath('duplicate.name', 'Playlist Corrida')
            ->assertJsonPath('duplicate.spotify_playlist_id', '5bNqE1YOKGpbylAsxAiNtC');
    }

    public function test_preview_returns_validation_error_for_invalid_input(): void
    {
        $this->actingAs($this->userWithPermissions(['playlists.create']))
            ->postJson(route('playlists.spotify.preview'), [
                'spotify_input' => 'https://open.spotify.com/track/37i9dQZF1DX0FOF1IUWK1W',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['spotify_input']);
    }

    public function test_preview_returns_specific_validation_error_for_album_link(): void
    {
        $this->actingAs($this->userWithPermissions(['playlists.create']))
            ->postJson(route('playlists.spotify.preview'), [
                'spotify_input' => 'https://open.spotify.com/intl-pt/album/4R6FV9NSzhPihHR0h4pI93',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.spotify_input.0', 'Este campo aceita apenas playlists do Spotify. O link informado é de álbum.');
    }

    public function test_preview_returns_controlled_error_when_credentials_are_missing(): void
    {
        config([
            'services.spotify.client_id' => null,
            'services.spotify.client_secret' => null,
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.create']))
            ->postJson(route('playlists.spotify.preview'), [
                'spotify_input' => '37i9dQZF1DX0FOF1IUWK1W',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Configure as credenciais do Spotify antes de buscar a playlist.');
    }

    public function test_preview_returns_controlled_error_for_missing_spotify_playlist(): void
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
            'api.spotify.com/v1/playlists/37i9dQZF1DX0FOF1IUWK1W*' => Http::response([], 404),
            'open.spotify.com/oembed*' => Http::response([], 404),
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.create']))
            ->postJson(route('playlists.spotify.preview'), [
                'spotify_input' => '37i9dQZF1DX0FOF1IUWK1W',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A API do Spotify não liberou os dados desta playlist para esta credencial. Você ainda pode cadastrar manualmente pelo link e ID.');
    }

    public function test_preview_returns_controlled_error_for_spotify_connection_failure(): void
    {
        config([
            'services.spotify.client_id' => 'client-id',
            'services.spotify.client_secret' => 'client-secret',
        ]);

        Http::preventStrayRequests();

        Http::fake([
            'accounts.spotify.com/api/token' => Http::failedConnection(),
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.create']))
            ->postJson(route('playlists.spotify.preview'), [
                'spotify_input' => '37i9dQZF1DX0FOF1IUWK1W',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não foi possível consultar o Spotify agora. Tente novamente em instantes.');
    }

    public function test_sync_requires_playlist_update_permission(): void
    {
        $playlist = Playlist::factory()->for(MusicGenre::factory(), 'genre')->create([
            'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.view']))
            ->patchJson(route('playlists.spotify.sync', $playlist))
            ->assertForbidden();
    }

    public function test_sync_requires_spotify_playlist_id(): void
    {
        $playlist = Playlist::factory()->for(MusicGenre::factory(), 'genre')->create([
            'spotify_playlist_id' => null,
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.update']))
            ->patchJson(route('playlists.spotify.sync', $playlist))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['spotify_playlist_id'])
            ->assertJsonPath('message', 'Vincule um ID público do Spotify antes de sincronizar.');
    }

    public function test_sync_updates_spotify_metadata_and_playlist_name(): void
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
                'name' => 'Nome vindo do Spotify',
                'external_urls' => [
                    'spotify' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W',
                ],
                'followers' => [
                    'total' => 2222,
                ],
                'owner' => [
                    'id' => 'bestplay',
                    'display_name' => 'BestPlay Curadoria',
                ],
                'tracks' => [
                    'total' => 77,
                ],
                'snapshot_id' => 'snapshot-sync',
                'public' => true,
            ]),
        ]);

        $playlist = Playlist::factory()->for(MusicGenre::factory(), 'genre')->create([
            'name' => 'Nome comercial local',
            'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
            'spotify_url' => null,
            'followers_count' => 10,
            'spotify_tracks_total' => null,
            'spotify_snapshot_id' => null,
            'spotify_last_synced_at' => null,
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.update']))
            ->patchJson(route('playlists.spotify.sync', $playlist))
            ->assertOk()
            ->assertJsonPath('message', 'Dados do Spotify sincronizados.')
            ->assertJsonPath('data.name', 'Nome vindo do Spotify')
            ->assertJsonPath('data.spotify_url', 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W')
            ->assertJsonPath('data.followers_count', 2222)
            ->assertJsonPath('data.spotify_owner_name', 'BestPlay Curadoria')
            ->assertJsonPath('data.spotify_tracks_total', 77)
            ->assertJsonPath('data.spotify_snapshot_id', 'snapshot-sync');

        $playlist->refresh();

        $this->assertSame('Nome vindo do Spotify', $playlist->name);
        $this->assertNotNull($playlist->spotify_last_synced_at);
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
