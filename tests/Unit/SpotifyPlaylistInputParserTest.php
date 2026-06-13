<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Playlists\Support\SpotifyPlaylistInputParser;
use PHPUnit\Framework\TestCase;

class SpotifyPlaylistInputParserTest extends TestCase
{
    public function test_it_accepts_supported_playlist_inputs(): void
    {
        $parser = new SpotifyPlaylistInputParser;

        $this->assertSame('37i9dQZF1DX0FOF1IUWK1W', $parser->parse('37i9dQZF1DX0FOF1IUWK1W'));
        $this->assertSame('37i9dQZF1DX0FOF1IUWK1W', $parser->parse('https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W'));
        $this->assertSame('37i9dQZF1DX0FOF1IUWK1W', $parser->parse('https://open.spotify.com/intl-pt/playlist/37i9dQZF1DX0FOF1IUWK1W'));
        $this->assertSame('37i9dQZF1DX0FOF1IUWK1W', $parser->parse('https://open.spotify.com/playlist/37i9dQZF1DX0FOF1IUWK1W?si=abc123'));
        $this->assertSame('37i9dQZF1DX0FOF1IUWK1W', $parser->parse('spotify:playlist:37i9dQZF1DX0FOF1IUWK1W'));
    }

    public function test_it_rejects_unsupported_playlist_inputs(): void
    {
        $parser = new SpotifyPlaylistInputParser;

        $this->assertNull($parser->parse(''));
        $this->assertNull($parser->parse('https://open.spotify.com/track/37i9dQZF1DX0FOF1IUWK1W'));
        $this->assertNull($parser->parse('https://example.com/playlist/37i9dQZF1DX0FOF1IUWK1W'));
        $this->assertNull($parser->parse('invalid playlist id'));
        $this->assertNull($parser->parse('37i9dQZF1DX0FOF1IUWK1W!'));
    }

    public function test_it_detects_spotify_resource_type_from_urls_and_uris(): void
    {
        $parser = new SpotifyPlaylistInputParser;

        $this->assertSame('album', $parser->resourceType('https://open.spotify.com/intl-pt/album/4R6FV9NSzhPihHR0h4pI93'));
        $this->assertSame('track', $parser->resourceType('https://open.spotify.com/track/37i9dQZF1DX0FOF1IUWK1W'));
        $this->assertSame('playlist', $parser->resourceType('spotify:playlist:37i9dQZF1DX0FOF1IUWK1W'));
        $this->assertNull($parser->resourceType('https://example.com/playlist/37i9dQZF1DX0FOF1IUWK1W'));
    }
}
