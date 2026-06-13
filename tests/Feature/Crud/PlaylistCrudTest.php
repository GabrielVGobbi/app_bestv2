<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Database\Seeders\PlaylistSeeder;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaylistCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_playlist_seeder_creates_initial_catalog_and_playlist_idempotently(): void
    {
        $this->seed(PlaylistSeeder::class);
        $this->seed(PlaylistSeeder::class);

        $genre = MusicGenre::query()->where('slug', 'mpb')->firstOrFail();
        $subgenre = MusicSubgenre::query()->where('slug', 'mpb-antigas')->firstOrFail();

        $this->assertSame(12, MusicGenre::query()->count());
        $this->assertGreaterThanOrEqual(60, MusicSubgenre::query()->count());
        $this->assertSame(1, Playlist::query()->count());
        $this->assertDatabaseHas('music_genres', ['slug' => 'pop']);
        $this->assertDatabaseHas('music_genres', ['slug' => 'rock']);
        $this->assertDatabaseHas('music_subgenres', ['slug' => 'indie-pop']);
        $this->assertSame('MPB', $genre->name);
        $this->assertTrue($genre->subgenres()->whereKey($subgenre->id)->exists());
        $this->assertSame('MPB Antigas', $subgenre->name);
        $this->assertTrue($genre->is_active);
        $this->assertTrue($subgenre->is_active);

        $playlist = Playlist::query()
            ->with(['genre', 'subgenres'])
            ->where('slug', 'mpb-antigas-bestplay')
            ->firstOrFail();

        $this->assertSame('MPB Antigas BestPlay', $playlist->name);
        $this->assertSame(PlaylistStatus::Active, $playlist->status);
        $this->assertSame($genre->id, $playlist->music_genre_id);
        $this->assertSame('Curadoria BestPlay', $playlist->partner_name);
        $this->assertSame('37i9dQZF1DX0FOF1IUWK1W', $playlist->spotify_playlist_id);
        $this->assertSame('https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W', $playlist->spotify_url);
        $this->assertSame(0, $playlist->followers_count);
        $this->assertSame(0, $playlist->estimated_reach);
        $this->assertSame('old_bestplay', $playlist->external_metadata['source']);
        $this->assertTrue($playlist->subgenres->contains($subgenre));
    }

    public function test_authorized_user_can_view_playlist_pages(): void
    {
        $user = $this->userWithPermissions(['playlists.view']);
        $genre = MusicGenre::factory()->create(['name' => 'MPB', 'slug' => 'mpb']);
        $subgenre = MusicSubgenre::factory()->create([
            'name' => 'MPB Antigas',
            'slug' => 'mpb-antigas',
        ]);
        $genre->subgenres()->attach($subgenre);
        $playlist = Playlist::factory()->for($genre, 'genre')->create([
            'name' => 'Brasil Acústico',
            'slug' => 'brasil-acustico',
            'status' => PlaylistStatus::Active,
        ]);
        $playlist->subgenres()->attach($subgenre);

        $this->actingAs($user)
            ->get(route('playlists.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/playlists/index')
                ->where('permissions.can_create', false)
                ->has('filters.genres', 1)
                ->has('filters.genres.0.subgenres', 1)
                ->where('filters.genres.0.subgenres.0.name', 'MPB Antigas')
            );

        $this->actingAs($user)
            ->get(route('playlists.show', $playlist))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/playlists/show')
                ->where('playlistData.data.uuid', $playlist->uuid)
                ->where('playlistData.data.genre.name', 'MPB')
                ->where('playlistData.data.subgenres.0.name', 'MPB Antigas')
            );
    }

    public function test_authorized_user_can_create_update_delete_and_restore_playlist(): void
    {
        $user = $this->userWithPermissions([
            'playlists.view',
            'playlists.create',
            'playlists.update',
            'playlists.delete',
            'playlists.restore',
        ]);
        $genre = MusicGenre::factory()->create(['name' => 'MPB', 'slug' => 'mpb']);
        $subgenre = MusicSubgenre::factory()->create([
            'name' => 'MPB Antigas',
            'slug' => 'mpb-antigas',
        ]);
        $genre->subgenres()->attach($subgenre);

        $this->actingAs($user)
            ->post(route('playlists.store'), [
                'name' => 'Brasil Acústico',
                'music_genre_id' => $genre->id,
                'subgenre_ids' => [$subgenre->id],
                'partner_name' => 'Curadoria BestPlay',
                'spotify_url' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W?si=abc123',
                'spotify_snapshot_id' => 'snapshot-123',
                'spotify_owner_id' => 'bestplay',
                'spotify_owner_name' => 'BestPlay Curadoria',
                'spotify_tracks_total' => '83',
                'spotify_last_synced_at' => '2026-06-12 10:30:00',
                'youtube_url' => 'https://www.youtube.com/@bestplay',
                'followers_count' => '12000',
                'estimated_reach' => '18000',
                'notes' => 'Playlist comercial prioritária.',
                'external_metadata' => [
                    'legacy_spotify_id' => '19',
                    'legacy_spotify_key' => '1',
                    'source' => 'old_bestplay',
                ],
            ])
            ->assertRedirect();

        $playlist = Playlist::query()->where('slug', 'brasil-acustico')->firstOrFail();

        $this->assertSame('37i9dQZF1DX0FOF1IUWK1W', $playlist->spotify_playlist_id);
        $this->assertSame('snapshot-123', $playlist->spotify_snapshot_id);
        $this->assertSame('bestplay', $playlist->spotify_owner_id);
        $this->assertSame('BestPlay Curadoria', $playlist->spotify_owner_name);
        $this->assertSame(83, $playlist->spotify_tracks_total);
        $this->assertSame('2026-06-12 10:30:00', $playlist->spotify_last_synced_at?->format('Y-m-d H:i:s'));
        $this->assertSame('old_bestplay', $playlist->external_metadata['source']);
        $this->assertTrue($playlist->subgenres()->whereKey($subgenre->id)->exists());

        $this->actingAs($user)
            ->put(route('playlists.update', $playlist), [
                'name' => 'Brasil Acústico Premium',
                'music_genre_id' => $genre->id,
                'subgenre_ids' => [],
                'partner_name' => null,
                'spotify_playlist_id' => 'manual-playlist-id',
                'spotify_url' => null,
                'spotify_snapshot_id' => null,
                'spotify_owner_id' => null,
                'spotify_owner_name' => null,
                'spotify_tracks_total' => null,
                'spotify_last_synced_at' => null,
                'youtube_url' => null,
                'followers_count' => 15000,
                'estimated_reach' => 22000,
                'notes' => null,
                'external_metadata' => null,
            ])
            ->assertRedirect(route('playlists.show', $playlist));

        $playlist->refresh();

        $this->assertSame('Brasil Acústico Premium', $playlist->name);
        $this->assertSame('brasil-acustico', $playlist->slug);
        $this->assertSame(PlaylistStatus::Active, $playlist->status);
        $this->assertSame('manual-playlist-id', $playlist->spotify_playlist_id);
        $this->assertNull($playlist->spotify_snapshot_id);
        $this->assertNull($playlist->spotify_owner_id);
        $this->assertNull($playlist->spotify_owner_name);
        $this->assertNull($playlist->spotify_tracks_total);
        $this->assertNull($playlist->spotify_last_synced_at);
        $this->assertSame(0, $playlist->subgenres()->count());

        $this->actingAs($user)
            ->delete(route('playlists.destroy', $playlist))
            ->assertRedirect(route('playlists.index'));

        $this->assertSoftDeleted($playlist);

        $this->actingAs($user)
            ->patch(route('playlists.restore', $playlist->uuid))
            ->assertRedirect(route('playlists.show', $playlist->uuid));

        $this->assertFalse($playlist->refresh()->trashed());
    }

    public function test_unauthorized_user_cannot_mutate_playlists(): void
    {
        $user = User::factory()->create();
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create();

        $this->actingAs($user)->get(route('playlists.index'))->assertForbidden();
        $this->actingAs($user)->get(route('playlists.create'))->assertForbidden();
        $this->actingAs($user)->post(route('playlists.store'), [
            'name' => 'Playlist bloqueada',
            'status' => 'active',
            'music_genre_id' => $genre->id,
        ])->assertForbidden();
        $this->actingAs($user)->put(route('playlists.update', $playlist), [
            'name' => 'Playlist atualizada',
            'status' => 'active',
            'music_genre_id' => $genre->id,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('playlists.destroy', $playlist))->assertForbidden();

        $playlist->refresh();

        $this->assertNull($playlist->deleted_at);
        $this->assertDatabaseMissing('playlists', ['slug' => 'playlist-bloqueada']);
    }

    public function test_playlist_validation_rejects_invalid_payloads(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlists.create']);
        $genre = MusicGenre::factory()->create();
        $otherGenre = MusicGenre::factory()->create();
        $foreignSubgenre = MusicSubgenre::factory()->create();
        $otherGenre->subgenres()->attach($foreignSubgenre);

        $this->actingAs($user)
            ->post(route('playlists.store'), [
                'name' => '',
                'status' => 'published',
                'music_genre_id' => $genre->id,
                'subgenre_ids' => [$foreignSubgenre->id],
                'spotify_url' => 'not-a-url',
                'youtube_url' => 'not-a-url',
                'followers_count' => -1,
                'estimated_reach' => -1,
            ])
            ->assertSessionHasErrors([
                'name',
                'status',
                'subgenre_ids',
                'spotify_url',
                'youtube_url',
                'followers_count',
                'estimated_reach',
            ]);
    }

    public function test_authorized_user_can_update_playlist_status_from_show_context(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlists.update']);
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create([
            'status' => PlaylistStatus::Active,
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.status', $playlist), [
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertSame(PlaylistStatus::Inactive, $playlist->refresh()->status);

        $this->actingAs($user)
            ->patch(route('playlists.status', $playlist), [
                'status' => 'published',
            ])
            ->assertSessionHasErrors(['status']);
    }

    public function test_playlist_update_without_status_preserves_current_status(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlists.update']);
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create([
            'name' => 'Playlist pausada',
            'status' => PlaylistStatus::Inactive,
        ]);

        $this->actingAs($user)
            ->put(route('playlists.update', $playlist), [
                'name' => 'Playlist pausada editada',
                'music_genre_id' => $genre->id,
            ])
            ->assertRedirect(route('playlists.show', $playlist));

        $this->assertSame(PlaylistStatus::Inactive, $playlist->refresh()->status);
    }

    public function test_playlist_validation_rejects_duplicate_spotify_playlist_id(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlists.create', 'playlists.update']);
        $genre = MusicGenre::factory()->create();

        Playlist::factory()->for($genre, 'genre')->create([
            'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
        ]);

        $playlist = Playlist::factory()->for($genre, 'genre')->create([
            'spotify_playlist_id' => '3cEYpjA9oz9GiPac4AsH4n',
        ]);

        $this->actingAs($user)
            ->post(route('playlists.store'), [
                'name' => 'Playlist duplicada',
                'status' => 'active',
                'music_genre_id' => $genre->id,
                'spotify_url' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W',
            ])
            ->assertSessionHasErrors(['spotify_playlist_id']);

        $this->actingAs($user)
            ->put(route('playlists.update', $playlist), [
                'name' => 'Playlist atualizada',
                'status' => 'active',
                'music_genre_id' => $genre->id,
                'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
            ])
            ->assertSessionHasErrors(['spotify_playlist_id']);
    }

    public function test_playlist_validation_messages_are_localized_to_portuguese(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlists.create']);
        $genre = MusicGenre::factory()->create();

        Playlist::factory()->for($genre, 'genre')->create([
            'spotify_playlist_id' => '37i9dQZF1DX0FOF1IUWK1W',
        ]);

        $this->actingAs($user)
            ->post(route('playlists.store'), [
                'name' => 'Playlist duplicada',
                'spotify_url' => 'https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W',
            ])
            ->assertSessionHasErrors([
                'music_genre_id' => 'Selecione um gênero musical.',
                'spotify_playlist_id' => 'Este ID público do Spotify já está cadastrado.',
            ]);
    }

    public function test_api_returns_playlist_records_and_can_filter_them(): void
    {
        $user = $this->userWithPermissions(['playlists.view']);
        $mpb = MusicGenre::factory()->create(['name' => 'MPB', 'slug' => 'mpb']);
        $rock = MusicGenre::factory()->create(['name' => 'Rock', 'slug' => 'rock']);
        $subgenre = MusicSubgenre::factory()->create([
            'name' => 'MPB Antigas',
            'slug' => 'mpb-antigas',
        ]);
        $mpb->subgenres()->attach($subgenre);

        $playlist = Playlist::factory()->for($mpb, 'genre')->create([
            'name' => 'Brasil Acústico',
            'partner_name' => 'Curadoria BestPlay',
            'status' => PlaylistStatus::Active,
        ]);
        $playlist->subgenres()->attach($subgenre);

        Playlist::factory()->for($rock, 'genre')->create([
            'name' => 'Rock Pausado',
            'partner_name' => 'Outro Parceiro',
            'status' => PlaylistStatus::Inactive,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/playlists')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Rock Pausado');

        $this->actingAs($user)
            ->getJson('/api/v1/playlists?'.http_build_query([
                'filter' => [
                    'status' => [
                        'operator' => 'equals',
                        'value' => 'active',
                    ],
                    'music_genre_id' => [
                        'operator' => 'equals',
                        'value' => $mpb->id,
                    ],
                    'subgenres.id' => [
                        'operator' => 'equals',
                        'value' => $subgenre->id,
                    ],
                    'partner_name' => [
                        'operator' => 'contains',
                        'value' => 'BestPlay',
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Brasil Acústico')
            ->assertJsonPath('data.0.genre.name', 'MPB')
            ->assertJsonPath('data.0.subgenres.0.name', 'MPB Antigas');
    }

    public function test_playlist_api_route_uses_the_stateful_api_group(): void
    {
        $route = Route::getRoutes()->getByName('api.playlists.index');

        $this->assertNotNull($route);
        $this->assertSame('api/v1/playlists', $route->uri());
        $this->assertContains('api', $route->gatherMiddleware());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    public function test_playlists_navigation_lives_under_gestao_cadastros(): void
    {
        $navigation = config('navigation.modules');

        $gestaoModule = collect($navigation)->firstWhere('slug', 'gestao');

        $this->assertContains('/playlists', $gestaoModule['route_prefixes']);

        $cadastrosSection = collect($gestaoModule['items'])->firstWhere('slug', 'gestao_cadastros_section');

        $this->assertNotNull($cadastrosSection);
        $this->assertSame(
            ['route' => 'playlists.index'],
            collect($cadastrosSection['items'])->firstWhere('slug', 'gestao_playlists')['href'],
        );
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
