<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaleItemSpotifyPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_marks_playlist_item_as_present_by_spotify_track_id(): void
    {
        $this->fakeSpotifyPlaylistTracks([
            $this->trackPayload('4kwbbhuemSkRWvtN5dkWmb', 'US39N2303532'),
        ], snapshotId: 'snapshot-a');

        [$sale, $item] = $this->saleWithPlaylistItem([
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_isrc' => 'US39N2303532',
        ]);

        $this->actingAs($this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]))
            ->post(route('sales.items.spotify-presence.sync', [$sale, $item]))
            ->assertRedirect(route('sales.show', $sale));

        $item->refresh();

        $this->assertSame('present', $item->spotify_playlist_check_status->value);
        $this->assertSame(1, $item->spotify_playlist_track_position);
        $this->assertSame('track_id', $item->spotify_playlist_match_type);
        $this->assertSame('playlist-spotify-id', $item->spotify_playlist_id_checked);
        $this->assertSame('snapshot-a', $item->spotify_playlist_snapshot_id_checked);
        $this->assertNull($item->spotify_playlist_check_error);
    }

    public function test_sync_marks_playlist_item_as_present_by_isrc_when_spotify_id_differs(): void
    {
        $this->fakeSpotifyPlaylistTracks([
            $this->trackPayload('different-track-id', 'US39N2303532'),
        ]);

        [$sale, $item] = $this->saleWithPlaylistItem([
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_isrc' => 'US39N2303532',
        ]);

        $this->actingAs($this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]))
            ->post(route('sales.items.spotify-presence.sync', [$sale, $item]))
            ->assertRedirect(route('sales.show', $sale));

        $item->refresh();

        $this->assertSame('present', $item->spotify_playlist_check_status->value);
        $this->assertSame(1, $item->spotify_playlist_track_position);
        $this->assertSame('isrc', $item->spotify_playlist_match_type);
    }

    public function test_sync_marks_playlist_item_as_missing_when_track_is_not_in_playlist(): void
    {
        $this->fakeSpotifyPlaylistTracks([
            $this->trackPayload('other-track-id', 'BR0000000001'),
        ]);

        [$sale, $item] = $this->saleWithPlaylistItem([
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_isrc' => 'US39N2303532',
        ]);

        $this->actingAs($this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]))
            ->post(route('sales.items.spotify-presence.sync', [$sale, $item]))
            ->assertRedirect(route('sales.show', $sale));

        $item->refresh();

        $this->assertSame('missing', $item->spotify_playlist_check_status->value);
        $this->assertNull($item->spotify_playlist_track_position);
        $this->assertNull($item->spotify_playlist_match_type);
    }

    public function test_sync_marks_playlist_without_spotify_id_without_calling_spotify(): void
    {
        Http::preventStrayRequests();

        [$sale, $item] = $this->saleWithPlaylistItem([], ['spotify_playlist_id' => null]);

        $this->actingAs($this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]))
            ->post(route('sales.items.spotify-presence.sync', [$sale, $item]))
            ->assertRedirect(route('sales.show', $sale));

        $item->refresh();

        $this->assertSame('playlist_missing_id', $item->spotify_playlist_check_status->value);
        $this->assertSame('A playlist não possui ID do Spotify configurado.', $item->spotify_playlist_check_error);
    }

    public function test_sync_preserves_previous_result_when_spotify_is_rate_limited(): void
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
            'api.spotify.com/v1/playlists/playlist-spotify-id/tracks*' => Http::response([], 429, [
                'Retry-After' => '600',
            ]),
        ]);

        [$sale, $item] = $this->saleWithPlaylistItem([
            'spotify_playlist_check_status' => 'present',
            'spotify_playlist_track_position' => 7,
            'spotify_playlist_match_type' => 'track_id',
        ]);

        $this->actingAs($this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]))
            ->post(route('sales.items.spotify-presence.sync', [$sale, $item]))
            ->assertRedirect(route('sales.show', $sale));

        $item->refresh();

        $this->assertSame('spotify_unavailable', $item->spotify_playlist_check_status->value);
        $this->assertSame(7, $item->spotify_playlist_track_position);
        $this->assertSame('track_id', $item->spotify_playlist_match_type);
        $this->assertSame('O Spotify limitou temporariamente as consultas. Tente novamente depois.', $item->spotify_playlist_check_error);
    }

    public function test_sync_treats_private_or_missing_playlist_as_spotify_unavailable(): void
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
            'api.spotify.com/v1/playlists/playlist-spotify-id/tracks*' => Http::response([], 403),
        ]);

        [$sale, $item] = $this->saleWithPlaylistItem();

        $this->actingAs($this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]))
            ->post(route('sales.items.spotify-presence.sync', [$sale, $item]))
            ->assertRedirect(route('sales.show', $sale));

        $item->refresh();

        $this->assertSame('spotify_unavailable', $item->spotify_playlist_check_status->value);
        $this->assertSame('A API do Spotify não liberou as faixas desta playlist para esta credencial.', $item->spotify_playlist_check_error);
    }

    public function test_sync_treats_connection_failure_as_spotify_unavailable(): void
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
            'api.spotify.com/v1/playlists/playlist-spotify-id/tracks*' => fn (): never => throw new ConnectionException('timeout'),
        ]);

        [$sale, $item] = $this->saleWithPlaylistItem();

        $this->actingAs($this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]))
            ->post(route('sales.items.spotify-presence.sync', [$sale, $item]))
            ->assertRedirect(route('sales.show', $sale));

        $item->refresh();

        $this->assertSame('spotify_unavailable', $item->spotify_playlist_check_status->value);
        $this->assertSame('Não foi possível consultar o Spotify agora. Tente novamente em instantes.', $item->spotify_playlist_check_error);
    }

    public function test_sync_blocks_sale_item_that_does_not_belong_to_sale(): void
    {
        $user = $this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]);
        [$sale] = $this->saleWithPlaylistItem();
        [, $otherItem] = $this->saleWithPlaylistItem([], ['spotify_playlist_id' => 'other-playlist-spotify-id']);

        $this->actingAs($user)
            ->post(route('sales.items.spotify-presence.sync', [$sale, $otherItem]))
            ->assertForbidden();
    }

    public function test_sync_requires_spotify_playlist_verify_permission(): void
    {
        [$sale, $item] = $this->saleWithPlaylistItem();

        $this->actingAs($this->userWithPermissions(['sales.view']))
            ->post(route('sales.items.spotify-presence.sync', [$sale, $item]))
            ->assertForbidden();
    }

    public function test_sale_resource_exposes_spotify_playlist_presence_result(): void
    {
        [$sale, $item] = $this->saleWithPlaylistItem([
            'spotify_playlist_check_status' => 'present',
            'spotify_playlist_checked_at' => now(),
            'spotify_playlist_track_position' => 4,
            'spotify_playlist_match_type' => 'isrc',
            'spotify_playlist_id_checked' => 'playlist-spotify-id',
            'spotify_playlist_snapshot_id_checked' => 'snapshot-b',
        ]);

        $this->actingAs($this->userWithPermissions(['sales.view']))
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('saleData.data.sale_items.0.id', $item->id)
                ->where('saleData.data.sale_items.0.spotify_playlist_presence.status', 'present')
                ->where('saleData.data.sale_items.0.spotify_playlist_presence.status_presenter.label', 'Na playlist')
                ->where('saleData.data.sale_items.0.spotify_playlist_presence.track_position', 4)
                ->where('saleData.data.sale_items.0.spotify_playlist_presence.match_type', 'isrc')
                ->where('saleData.data.sale_items.0.spotify_playlist_presence.playlist_id_checked', 'playlist-spotify-id')
                ->where('permissions.can_verify_spotify_playlist', false));
    }

    public function test_sale_show_exposes_playlist_cover_on_playlist_slot_items(): void
    {
        [$sale] = $this->saleWithPlaylistItem([], [
            'spotify_image_url' => 'https://i.scdn.co/image/playlist-cover',
        ]);

        $this->actingAs($this->userWithPermissions(['sales.view']))
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('app/sales/show')
                ->where('saleData.data.sale_items.0.playlist_top.playlist_name', 'Corrida 10km')
                ->where('saleData.data.sale_items.0.playlist_top.cover_image_url', 'https://i.scdn.co/image/playlist-cover')
                ->where('saleData.data.sale_items.0.track_image_url', null));
    }

    public function test_sync_all_checks_every_playlist_item_in_sale(): void
    {
        $this->fakeSpotifyPlaylistTracks([
            $this->trackPayload('4kwbbhuemSkRWvtN5dkWmb', 'US39N2303532'),
        ]);

        [$sale, $item] = $this->saleWithPlaylistItem([
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
        ]);
        SaleItem::factory()->for($sale)->create([
            'type' => SaleItemType::Service,
            'service_id' => null,
            'playlist_top_id' => null,
        ]);

        $this->actingAs($this->userWithPermissions([
            'sales.view',
            'sales.items.spotify-playlist.verify',
        ]))
            ->post(route('sales.spotify-presence.sync', $sale))
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame('present', $item->refresh()->spotify_playlist_check_status->value);
    }

    /**
     * @param  array<string, mixed>  $itemAttributes
     * @param  array<string, mixed>  $playlistAttributes
     * @return array{0: Sale, 1: SaleItem}
     */
    private function saleWithPlaylistItem(array $itemAttributes = [], array $playlistAttributes = []): array
    {
        $playlist = Playlist::factory()->create([
            'name' => 'Corrida 10km',
            'spotify_playlist_id' => 'playlist-spotify-id',
            ...$playlistAttributes,
        ]);
        $top = Top::factory()->create(['name' => 'TOP 20']);
        $playlistTop = PlaylistTop::factory()->for($playlist)->for($top)->create();
        $sale = Sale::factory()->create();
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'description' => 'Corrida 10km - TOP 20',
            'track_name' => 'Friends',
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'track_isrc' => 'US39N2303532',
            ...$itemAttributes,
        ]);

        return [$sale, $item];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tracks
     */
    private function fakeSpotifyPlaylistTracks(array $tracks, ?string $snapshotId = null): void
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
            'api.spotify.com/v1/playlists/playlist-spotify-id/tracks*' => Http::response([
                'href' => 'https://api.spotify.com/v1/playlists/playlist-spotify-id/tracks',
                'items' => collect($tracks)
                    ->map(fn (array $track): array => ['track' => $track])
                    ->all(),
                'limit' => 50,
                'next' => null,
                'offset' => 0,
                'total' => count($tracks),
                'snapshot_id' => $snapshotId,
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function trackPayload(string $spotifyTrackId, string $isrc): array
    {
        return [
            'id' => $spotifyTrackId,
            'name' => 'Friends',
            'external_urls' => [
                'spotify' => 'https://open.spotify.com/track/'.$spotifyTrackId,
            ],
            'external_ids' => [
                'isrc' => $isrc,
            ],
            'artists' => [
                ['name' => 'MEDUZA'],
            ],
            'duration_ms' => 158000,
        ];
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
