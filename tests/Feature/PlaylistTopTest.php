<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaylistTopTest extends TestCase
{
    use RefreshDatabase;

    public function test_playlist_show_exposes_commercial_tops_area_data(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlist-tops.view']);
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create([
            'name' => 'Brasil Acústico',
            'status' => PlaylistStatus::Active,
        ]);
        $top = Top::factory()->create(['name' => 'TOP 10', 'slug' => 'top-10']);

        PlaylistTop::factory()
            ->for($playlist)
            ->for($top)
            ->create([
                'slots_quantity' => 10,
                'occupied_slots_count' => 3,
                'price' => 150000,
                'metrics' => ['estimated_streams' => 15000],
            ]);

        $this->actingAs($user)
            ->get(route('playlists.show', $playlist))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/playlists/show')
                ->has('playlistTops.data', 1)
                ->where('playlistTops.data.0.top.name', 'TOP 10')
                ->where('playlistTops.data.0.slots_quantity', 10)
                ->where('playlistTops.data.0.occupied_slots_count', 3)
                ->where('playlistTops.data.0.available_slots_count', 7)
                ->has('topOptions.data', 1)
                ->where('playlistTopPermissions.can_attach', false)
            );
    }

    public function test_authorized_user_can_attach_existing_top_to_playlist(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlist-tops.attach']);
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create();
        $top = Top::factory()->create([
            'name' => 'TOP 5',
            'slug' => 'top-5',
            'default_slots_quantity' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('playlists.tops.store', $playlist), [
                'top_id' => $top->id,
                'price' => '1299.90',
                'metrics' => [
                    'estimated_streams' => 12000,
                    'estimated_reach' => 25000,
                    'average_retention_days' => 14,
                    'commercial_score' => 85,
                ],
                'notes' => 'Prioridade comercial.',
                'is_active' => true,
            ])
            ->assertRedirect(route('playlists.show', $playlist));

        $playlistTop = PlaylistTop::query()->whereBelongsTo($playlist)->firstOrFail();

        $this->assertSame($top->id, $playlistTop->top_id);
        $this->assertSame(5, $playlistTop->slots_quantity);
        $this->assertSame(0, $playlistTop->occupied_slots_count);
        $this->assertSame(129990, $playlistTop->price);
        $this->assertSame('BRL', $playlistTop->currency);
        $this->assertSame(12000, $playlistTop->metrics['estimated_streams']);
        $this->assertSame('Prioridade comercial.', $playlistTop->notes);
    }

    public function test_playlist_cannot_attach_duplicate_active_top(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlist-tops.attach']);
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create();
        $top = Top::factory()->create(['is_active' => true]);

        PlaylistTop::factory()->for($playlist)->for($top)->create([
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('playlists.tops.store', $playlist), [
                'top_id' => $top->id,
            ])
            ->assertSessionHasErrors(['top_id']);

        $this->assertSame(1, PlaylistTop::query()->whereBelongsTo($playlist)->whereBelongsTo($top)->count());
    }

    public function test_authorized_user_can_configure_playlist_top(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlist-tops.configure']);
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create();
        $playlistTop = PlaylistTop::factory()
            ->for($playlist)
            ->for(Top::factory())
            ->create([
                'slots_quantity' => 5,
                'occupied_slots_count' => 2,
                'price' => 0,
                'is_active' => true,
            ]);

        $this->actingAs($user)
            ->patch(route('playlists.tops.update', [$playlist, $playlistTop]), [
                'slots_quantity' => 8,
                'occupied_slots_count' => 7,
                'price' => '899.50',
                'currency' => 'USD',
                'metrics' => [
                    'estimated_streams' => 22000,
                    'commercial_score' => 90,
                ],
                'notes' => 'Ajustado para campanha.',
                'is_active' => false,
            ])
            ->assertRedirect(route('playlists.show', $playlist));

        $playlistTop->refresh();

        $this->assertSame(8, $playlistTop->slots_quantity);
        $this->assertSame(2, $playlistTop->occupied_slots_count);
        $this->assertSame(89950, $playlistTop->price);
        $this->assertSame('BRL', $playlistTop->currency);
        $this->assertSame(90, $playlistTop->metrics['commercial_score']);
        $this->assertSame('Ajustado para campanha.', $playlistTop->notes);
        $this->assertFalse($playlistTop->is_active);
    }

    public function test_playlist_top_validation_rejects_capacity_below_occupancy(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlist-tops.configure']);
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create();
        $playlistTop = PlaylistTop::factory()
            ->for($playlist)
            ->for(Top::factory())
            ->create([
                'slots_quantity' => 5,
                'occupied_slots_count' => 3,
            ]);

        $this->actingAs($user)
            ->patch(route('playlists.tops.update', [$playlist, $playlistTop]), [
                'slots_quantity' => 2,
                'price' => 0,
            ])
            ->assertSessionHasErrors(['slots_quantity']);

        $this->assertSame(5, $playlistTop->refresh()->slots_quantity);
    }

    public function test_authorized_user_can_archive_playlist_top_without_deleting_history(): void
    {
        $user = $this->userWithPermissions(['playlists.view', 'playlist-tops.detach']);
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create();
        $playlistTop = PlaylistTop::factory()
            ->for($playlist)
            ->for(Top::factory())
            ->create();

        $this->actingAs($user)
            ->delete(route('playlists.tops.destroy', [$playlist, $playlistTop]))
            ->assertRedirect(route('playlists.show', $playlist));

        $this->assertSoftDeleted($playlistTop);
    }

    public function test_unauthorized_user_cannot_attach_configure_or_detach_playlist_tops(): void
    {
        $user = User::factory()->create();
        $genre = MusicGenre::factory()->create();
        $playlist = Playlist::factory()->for($genre, 'genre')->create();
        $top = Top::factory()->create(['is_active' => true]);
        $playlistTop = PlaylistTop::factory()
            ->for($playlist)
            ->for($top)
            ->create();

        $this->actingAs($user)
            ->post(route('playlists.tops.store', $playlist), ['top_id' => $top->id])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('playlists.tops.update', [$playlist, $playlistTop]), [
                'slots_quantity' => 2,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('playlists.tops.destroy', [$playlist, $playlistTop]))
            ->assertForbidden();

        $this->assertNull($playlistTop->refresh()->deleted_at);
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
