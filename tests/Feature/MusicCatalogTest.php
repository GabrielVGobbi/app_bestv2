<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Database\Seeders\MusicCatalogSeeder;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MusicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_music_catalog_requires_playlist_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('playlists.music-catalog.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->postJson(route('playlists.music-genres.store'), [
                'name' => 'MPB',
            ])
            ->assertForbidden();
    }

    public function test_music_catalog_requires_playlist_update_permission_to_detach_subgenre(): void
    {
        $genre = MusicGenre::factory()->create();
        $subgenre = MusicSubgenre::factory()->create();
        $genre->subgenres()->attach($subgenre);

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('playlists.music-genres.subgenres.destroy', [$genre, $subgenre]))
            ->assertForbidden();

        $this->assertDatabaseHas('music_genre_music_subgenre', [
            'music_genre_id' => $genre->id,
            'music_subgenre_id' => $subgenre->id,
        ]);
    }

    public function test_authorized_user_can_view_music_catalog_tree(): void
    {
        $user = $this->userWithPermissions(['playlists.view']);
        $genre = MusicGenre::factory()->create(['name' => 'MPB', 'slug' => 'mpb']);
        $subgenre = MusicSubgenre::factory()->create([
            'name' => 'MPB Antigas',
            'slug' => 'mpb-antigas',
        ]);
        $genre->subgenres()->attach($subgenre);

        $this->actingAs($user)
            ->get(route('playlists.music-catalog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/playlists/music-catalog/index')
                ->where('permissions.can_create', false)
                ->where('permissions.can_update', false)
                ->has('genres.data', 1)
                ->where('genres.data.0.name', 'MPB')
                ->where('genres.data.0.subgenres.0.name', 'MPB Antigas')
            );
    }

    public function test_music_catalog_route_and_navigation_live_under_gestao(): void
    {
        $route = Route::getRoutes()->getByName('playlists.music-catalog.index');

        $this->assertNotNull($route);
        $this->assertSame('admin/music-catalog', $route->uri());

        $navigation = config('navigation.modules');

        $adminModule = collect($navigation)->firstWhere('slug', 'admin');
        $gestaoModule = collect($navigation)->firstWhere('slug', 'gestao');

        $this->assertContains('/admin/music-catalog', $gestaoModule['route_prefixes']);
        $this->assertNotContains('/admin/music-catalog', $adminModule['route_prefixes']);

        $cadastrosSection = collect($gestaoModule['items'])->firstWhere('slug', 'gestao_cadastros_section');

        $this->assertNotNull($cadastrosSection);
        $this->assertSame(
            ['route' => 'playlists.music-catalog.index'],
            collect($cadastrosSection['items'])->firstWhere('slug', 'gestao_music_catalog')['href'],
        );
    }

    public function test_authorized_user_can_create_genres_and_subgenres(): void
    {
        $user = $this->userWithPermissions(['playlists.create']);

        $this->actingAs($user)
            ->postJson(route('playlists.music-genres.store'), [
                'name' => 'Pop Nacional',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pop Nacional')
            ->assertJsonPath('data.slug', 'pop-nacional')
            ->assertJsonPath('data.is_active', true);

        $genre = MusicGenre::query()->where('slug', 'pop-nacional')->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('playlists.music-genres.subgenres.store', $genre), [
                'name' => 'Pop Leve',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pop Leve')
            ->assertJsonPath('data.slug', 'pop-leve')
            ->assertJsonPath('data.is_active', true);

        $subgenreId = MusicSubgenre::query()->where('slug', 'pop-leve')->value('id');

        $this->assertDatabaseHas('music_subgenres', [
            'slug' => 'pop-leve',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('music_genre_music_subgenre', [
            'music_genre_id' => $genre->id,
            'music_subgenre_id' => $subgenreId,
        ]);
    }

    public function test_authorized_user_can_attach_existing_subgenre_to_another_genre(): void
    {
        $user = $this->userWithPermissions(['playlists.create']);
        $pop = MusicGenre::factory()->create(['name' => 'Pop', 'slug' => 'pop']);
        $indie = MusicGenre::factory()->create(['name' => 'Indie', 'slug' => 'indie']);
        $indiePop = MusicSubgenre::factory()->create([
            'name' => 'Indie Pop',
            'slug' => 'indie-pop',
        ]);
        $pop->subgenres()->attach($indiePop);

        $this->actingAs($user)
            ->postJson(route('playlists.music-genres.subgenres.store', $indie), [
                'music_subgenre_id' => $indiePop->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Indie Pop')
            ->assertJsonPath('data.slug', 'indie-pop');

        $this->assertSame(1, MusicSubgenre::query()->where('slug', 'indie-pop')->count());
        $this->assertDatabaseHas('music_genre_music_subgenre', [
            'music_genre_id' => $pop->id,
            'music_subgenre_id' => $indiePop->id,
        ]);
        $this->assertDatabaseHas('music_genre_music_subgenre', [
            'music_genre_id' => $indie->id,
            'music_subgenre_id' => $indiePop->id,
        ]);
    }

    public function test_authorized_user_can_detach_subgenre_without_removing_global_record_or_other_links(): void
    {
        $user = $this->userWithPermissions(['playlists.update']);
        $pop = MusicGenre::factory()->create(['name' => 'Pop', 'slug' => 'pop']);
        $indie = MusicGenre::factory()->create(['name' => 'Indie', 'slug' => 'indie']);
        $indiePop = MusicSubgenre::factory()->create([
            'name' => 'Indie Pop',
            'slug' => 'indie-pop',
        ]);
        $pop->subgenres()->attach($indiePop);
        $indie->subgenres()->attach($indiePop);

        $this->actingAs($user)
            ->deleteJson(route('playlists.music-genres.subgenres.destroy', [$pop, $indiePop]))
            ->assertOk()
            ->assertJsonPath('data.uuid', $pop->uuid)
            ->assertJsonMissingPath('data.subgenres.0');

        $this->assertModelExists($indiePop);
        $this->assertDatabaseMissing('music_genre_music_subgenre', [
            'music_genre_id' => $pop->id,
            'music_subgenre_id' => $indiePop->id,
        ]);
        $this->assertDatabaseHas('music_genre_music_subgenre', [
            'music_genre_id' => $indie->id,
            'music_subgenre_id' => $indiePop->id,
        ]);
    }

    public function test_authorized_user_can_update_and_toggle_music_catalog_records(): void
    {
        $user = $this->userWithPermissions(['playlists.update']);
        $genre = MusicGenre::factory()->create(['name' => 'Rock', 'slug' => 'rock']);
        $subgenre = MusicSubgenre::factory()->create([
            'name' => 'Indie Rock',
            'slug' => 'indie-rock',
        ]);
        $genre->subgenres()->attach($subgenre);

        $this->actingAs($user)
            ->patchJson(route('playlists.music-genres.update', $genre), [
                'name' => 'Rock Alternativo',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rock Alternativo')
            ->assertJsonPath('data.slug', 'rock-alternativo');

        $this->actingAs($user)
            ->patchJson(route('playlists.music-genres.status', $genre), [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($user)
            ->patchJson(route('playlists.music-subgenres.update', $subgenre), [
                'name' => 'Rock Indie',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rock Indie')
            ->assertJsonPath('data.slug', 'rock-indie');

        $this->actingAs($user)
            ->patchJson(route('playlists.music-subgenres.status', $subgenre), [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_music_catalog_validation_blocks_duplicate_slugs_in_the_right_scope(): void
    {
        $user = $this->userWithPermissions(['playlists.create', 'playlists.update']);
        $mpb = MusicGenre::factory()->create(['name' => 'MPB', 'slug' => 'mpb']);
        $rock = MusicGenre::factory()->create(['name' => 'Rock', 'slug' => 'rock']);
        $subgenre = MusicSubgenre::factory()->create([
            'name' => 'Acústico',
            'slug' => 'acustico',
        ]);
        $mpb->subgenres()->attach($subgenre);

        $this->actingAs($user)
            ->postJson(route('playlists.music-genres.store'), [
                'name' => 'MPB',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($user)
            ->postJson(route('playlists.music-genres.subgenres.store', $mpb), [
                'music_subgenre_id' => $subgenre->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($user)
            ->postJson(route('playlists.music-genres.subgenres.store', $rock), [
                'name' => 'Acústico',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($user)
            ->postJson(route('playlists.music-genres.subgenres.store', $rock), [
                'music_subgenre_id' => $subgenre->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'acustico');
    }

    public function test_music_catalog_seeder_creates_main_genres_and_reuses_global_subgenres(): void
    {
        $this->seed(MusicCatalogSeeder::class);
        $this->seed(MusicCatalogSeeder::class);

        foreach (['Pop', 'Rock', 'Hip Hop', 'Funk', 'Sertanejo', 'Eletrônica', 'MPB'] as $genreName) {
            $this->assertDatabaseHas('music_genres', [
                'name' => $genreName,
                'is_active' => true,
            ]);
        }

        $indiePop = MusicSubgenre::query()->where('slug', 'indie-pop')->firstOrFail();
        $pop = MusicGenre::query()->where('slug', 'pop')->firstOrFail();
        $indie = MusicGenre::query()->where('slug', 'indie')->firstOrFail();

        $this->assertSame(1, MusicSubgenre::query()->where('slug', 'indie-pop')->count());
        $this->assertTrue($pop->subgenres()->whereKey($indiePop->id)->exists());
        $this->assertTrue($indie->subgenres()->whereKey($indiePop->id)->exists());
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
