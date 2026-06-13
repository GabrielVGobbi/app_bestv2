<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Playlists\Exceptions\SpotifyPlaylistLookupException;
use App\Modules\Playlists\Services\SpotifyPlaylistLookupService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpotifyPlaylistLookupServiceTest extends TestCase
{
    public function test_it_returns_partial_preview_from_oembed_when_web_api_cannot_expose_playlist(): void
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
            'open.spotify.com/oembed*' => Http::response([
                'title' => 'Top Brasil',
                'provider_name' => 'Spotify',
            ]),
        ]);

        $preview = app(SpotifyPlaylistLookupService::class)
            ->preview('37i9dQZF1DX0FOF1IUWK1W');

        $this->assertSame('37i9dQZF1DX0FOF1IUWK1W', $preview['spotify_playlist_id']);
        $this->assertSame('https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W', $preview['spotify_url']);
        $this->assertSame('Top Brasil', $preview['name']);
        $this->assertSame('oembed', $preview['source']);
        $this->assertTrue($preview['is_partial']);
        $this->assertNull($preview['followers_count']);
        $this->assertNull($preview['spotify_tracks_total']);

        Http::assertSentCount(3);
    }

    public function test_it_does_not_retry_for_forbidden_or_missing_web_api_playlist(): void
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
            'api.spotify.com/v1/playlists/37i9dQZF1DX0FOF1IUWK1W*' => Http::response([], 403),
            'open.spotify.com/oembed*' => Http::response([], 404),
        ]);

        $this->expectException(SpotifyPlaylistLookupException::class);
        $this->expectExceptionMessage('A API do Spotify não liberou os dados desta playlist para esta credencial. Você ainda pode cadastrar manualmente pelo link e ID.');

        try {
            app(SpotifyPlaylistLookupService::class)
                ->preview('37i9dQZF1DX0FOF1IUWK1W');
        } finally {
            Http::assertSentCount(3);
        }
    }
}
