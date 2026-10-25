<?php

namespace Tests\Feature\ExternalApi;

use App\Models\User;
use App\Modules\ExternalApi\Models\ExternalApiConnection;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyAlbum;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyArtist;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyPlaylist;
use App\Modules\ExternalApi\Modules\Spotify\DTOs\SpotifyTrack;
use App\Modules\ExternalApi\Modules\Spotify\Mocks\SpotifyMockData;
use App\Modules\ExternalApi\Modules\Spotify\Services\SpotifyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_spotify_mock_catalog_services_do_not_call_real_http(): void
    {
        Http::preventStrayRequests();

        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
        ]);

        $spotify = app(SpotifyManager::class);

        $this->assertInstanceOf(SpotifyTrack::class, $spotify->tracks()->find('mock-track'));
        $this->assertInstanceOf(SpotifyArtist::class, $spotify->artists()->find('mock-artist'));
        $this->assertInstanceOf(SpotifyAlbum::class, $spotify->albums()->find('mock-album'));
        $this->assertInstanceOf(SpotifyPlaylist::class, $spotify->playlists()->find('mock-playlist'));
        $this->assertCount(1, $spotify->search()->tracks('mock')->items);
        $this->assertCount(1, $spotify->search()->artists('mock')->items);
        $this->assertCount(1, $spotify->search()->albums('mock')->items);
        $this->assertCount(1, $spotify->search()->playlists('mock')->items);
    }

    public function test_spotify_artists_view_renders_mock_results(): void
    {
        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('external-api.spotify.artists', ['q' => 'Daft Punk']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/external-api/spotify/artists')
                ->where('query', 'Daft Punk')
                ->where('isFake', true)
                ->has('artists', 1)
                ->where('artists.0.name', 'Mock Artist'));
    }

    public function test_spotify_playlists_view_renders_mock_results(): void
    {
        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('external-api.spotify.playlists', ['q' => 'Focus']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/external-api/spotify/playlists')
                ->where('query', 'Focus')
                ->where('isFake', true)
                ->has('playlists', 1)
                ->where('playlists.0.name', 'Mock Playlist'));
    }

    public function test_external_api_connection_migration_is_loaded_from_module(): void
    {
        $this->assertTrue(Schema::hasTable('external_api_connections'));
    }

    public function test_spotify_real_catalog_uses_client_credentials_and_faked_http(): void
    {
        config([
            'external-apis.fake.enabled' => false,
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => 'client-id',
            'external-apis.modules.spotify.client_secret' => 'client-secret',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response(SpotifyMockData::token()),
            'https://api.spotify.com/v1/search*' => Http::response(SpotifyMockData::search('track')),
        ]);

        $tracks = app(SpotifyManager::class)->search()->tracks('daft punk');

        $this->assertCount(1, $tracks->items);
        $this->assertInstanceOf(SpotifyTrack::class, $tracks->items[0]);

        Http::assertSentCount(2);
    }

    public function test_spotify_search_ignores_null_items_from_real_api(): void
    {
        config([
            'external-apis.fake.enabled' => false,
            'external-apis.modules.spotify.fake' => false,
            'external-apis.modules.spotify.client_id' => 'client-id',
            'external-apis.modules.spotify.client_secret' => 'client-secret',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response(SpotifyMockData::token()),
            'https://api.spotify.com/v1/search*' => Http::response([
                'playlists' => [
                    'items' => [
                        null,
                        SpotifyMockData::playlist('valid-playlist'),
                        null,
                    ],
                ],
            ]),
        ]);

        $playlists = app(SpotifyManager::class)->search()->playlists('Focus');

        $this->assertCount(1, $playlists->items);
        $this->assertInstanceOf(SpotifyPlaylist::class, $playlists->items[0]);
        $this->assertSame('valid-playlist', $playlists->items[0]->id);
    }

    public function test_spotify_oauth_callback_persists_and_disconnects_connection(): void
    {
        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
            'external-apis.modules.spotify.client_id' => 'client-id',
            'external-apis.modules.spotify.client_secret' => 'client-secret',
        ]);

        $user = User::factory()->create();
        $spotify = app(SpotifyManager::class);
        $authorizationUrl = $spotify->auth()->getAuthorizationUrl($user);
        parse_str(parse_url($authorizationUrl, PHP_URL_QUERY), $query);

        $connection = $spotify->auth()->handleCallback($user, 'oauth-code', $query['state']);

        $this->assertSame('spotify', $connection->provider);
        $this->assertSame($user->id, $connection->user_id);
        $this->assertSame('mock-user', $connection->provider_user_id);
        $this->assertDatabaseHas('external_api_connections', [
            'user_id' => $user->id,
            'provider' => 'spotify',
            'provider_user_id' => 'mock-user',
        ]);

        $spotify->auth()->disconnect($user);

        $this->assertDatabaseMissing('external_api_connections', [
            'user_id' => $user->id,
            'provider' => 'spotify',
        ]);
    }

    public function test_spotify_refreshes_expired_user_token(): void
    {
        config([
            'external-apis.fake.enabled' => true,
            'external-apis.modules.spotify.fake' => true,
        ]);

        $user = User::factory()->create();
        ExternalApiConnection::create([
            'user_id' => $user->id,
            'provider' => 'spotify',
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token',
            'token_type' => 'Bearer',
            'scopes' => ['user-read-email'],
            'expires_at' => now()->subMinute(),
            'metadata' => [],
        ]);

        $accessToken = app(SpotifyManager::class)->auth()->getUserAccessToken($user);

        $this->assertSame('mock_spotify_access_token', $accessToken);
        $this->assertTrue(
            ExternalApiConnection::where('user_id', $user->id)->first()->expires_at->isFuture()
        );
    }
}
