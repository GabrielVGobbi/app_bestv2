<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpotifyTrackLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_requires_spotify_track_lookup_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('sales.items.spotify-track.lookup'), [
                'spotify_input' => 'https://open.spotify.com/intl-pt/track/4kwbbhuemSkRWvtN5dkWmb',
            ])
            ->assertForbidden();
    }

    public function test_lookup_returns_normalized_spotify_track_payload(): void
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
            'api.spotify.com/v1/tracks/4kwbbhuemSkRWvtN5dkWmb*' => Http::response([
                'id' => '4kwbbhuemSkRWvtN5dkWmb',
                'name' => 'Velha Infância',
                'duration_ms' => 254000,
                'external_urls' => [
                    'spotify' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
                ],
                'artists' => [
                    ['name' => 'Tribalistas'],
                    ['name' => 'Marisa Monte'],
                ],
                'album' => [
                    'name' => 'Tribalistas',
                    'images' => [
                        ['url' => 'https://i.scdn.co/image/track-cover'],
                    ],
                ],
                'external_ids' => [
                    'isrc' => 'BR1234567890',
                ],
            ]),
        ]);

        $this->actingAs($this->userWithPermissions(['sales.items.spotify-track.lookup']))
            ->postJson(route('sales.items.spotify-track.lookup'), [
                'spotify_input' => 'https://open.spotify.com/intl-pt/track/4kwbbhuemSkRWvtN5dkWmb?si=abc123',
            ])
            ->assertOk()
            ->assertJsonPath('data.spotify_track_id', '4kwbbhuemSkRWvtN5dkWmb')
            ->assertJsonPath('data.spotify_url', 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb')
            ->assertJsonPath('data.name', 'Velha Infância')
            ->assertJsonPath('data.display_name', 'Velha Infância - Tribalistas, Marisa Monte')
            ->assertJsonPath('data.artist_names.0', 'Tribalistas')
            ->assertJsonPath('data.album_name', 'Tribalistas')
            ->assertJsonPath('data.image_url', 'https://i.scdn.co/image/track-cover')
            ->assertJsonPath('data.duration_ms', 254000)
            ->assertJsonPath('data.isrc', 'BR1234567890')
            ->assertJsonPath('data.source', 'web_api');
    }

    public function test_lookup_returns_specific_validation_error_for_playlist_link(): void
    {
        $this->actingAs($this->userWithPermissions(['sales.items.spotify-track.lookup']))
            ->postJson(route('sales.items.spotify-track.lookup'), [
                'spotify_input' => 'https://open.spotify.com/playlist/5bNqE1YOKGpbylAsxAiNtC',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.spotify_input.0', 'Este campo aceita apenas músicas do Spotify. O link informado é de playlist.');
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
