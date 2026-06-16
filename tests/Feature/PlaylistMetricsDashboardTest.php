<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaylistMetricsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_dashboard_requires_playlist_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('playlists.metrics.index'))
            ->assertForbidden();
    }

    public function test_metrics_dashboard_returns_summary_rankings_series_and_events(): void
    {
        $genre = MusicGenre::factory()->create(['name' => 'MPB', 'slug' => 'mpb']);
        $rising = Playlist::factory()->for($genre, 'genre')->create([
            'name' => 'Playlist em alta',
            'followers_count' => 1300,
            'spotify_last_synced_at' => now(),
        ]);
        $falling = Playlist::factory()->for($genre, 'genre')->create([
            'name' => 'Playlist em queda',
            'followers_count' => 700,
            'spotify_last_synced_at' => now(),
        ]);

        $rising->spotifySnapshots()->create([
            'followers_count' => 1000,
            'followers_delta' => 0,
            'followers_delta_percent' => null,
            'spotify_name' => 'Playlist em alta',
            'captured_at' => now()->subDay(),
        ]);
        $rising->spotifySnapshots()->create([
            'followers_count' => 1300,
            'followers_delta' => 300,
            'followers_delta_percent' => 30,
            'spotify_name' => 'Playlist em alta',
            'captured_at' => now(),
        ]);
        $falling->spotifySnapshots()->create([
            'followers_count' => 900,
            'followers_delta' => -200,
            'followers_delta_percent' => -22.2222,
            'spotify_name' => 'Playlist em queda',
            'captured_at' => now(),
        ]);
        $falling->changeEvents()->create([
            'field' => 'followers_count',
            'label' => 'Seguidores',
            'old_value' => '900',
            'new_value' => '700',
            'source' => 'spotify',
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->userWithPermissions(['playlists.view']))
            ->get(route('playlists.metrics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/playlists/metrics/index')
                ->where('dashboard.summary.total_followers', 2000)
                ->where('dashboard.summary.net_growth', 100)
                ->where('dashboard.summary.rising_count', 1)
                ->where('dashboard.summary.falling_count', 1)
                ->has('dashboard.series', 2)
                ->has('dashboard.rankings.rising', 1)
                ->where('dashboard.rankings.rising.0.playlist_name', 'Playlist em alta')
                ->has('dashboard.rankings.falling', 1)
                ->where('dashboard.rankings.falling.0.playlist_name', 'Playlist em queda')
                ->has('dashboard.rows', 2)
                ->has('dashboard.events', 1)
                ->has('filters.genres', 1)
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
