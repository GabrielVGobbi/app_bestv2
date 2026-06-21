<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Sales\Support\SpotifyTrackInputParser;
use PHPUnit\Framework\TestCase;

class SpotifyTrackInputParserTest extends TestCase
{
    public function test_it_accepts_supported_track_inputs(): void
    {
        $parser = new SpotifyTrackInputParser;

        $this->assertSame('4kwbbhuemSkRWvtN5dkWmb', $parser->parse('4kwbbhuemSkRWvtN5dkWmb'));
        $this->assertSame('4kwbbhuemSkRWvtN5dkWmb', $parser->parse('spotify:track:4kwbbhuemSkRWvtN5dkWmb'));
        $this->assertSame('4kwbbhuemSkRWvtN5dkWmb', $parser->parse('https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb'));
        $this->assertSame('4kwbbhuemSkRWvtN5dkWmb', $parser->parse('https://open.spotify.com/intl-pt/track/4kwbbhuemSkRWvtN5dkWmb?si=abc'));
    }

    public function test_it_rejects_non_track_inputs(): void
    {
        $parser = new SpotifyTrackInputParser;

        $this->assertNull($parser->parse(''));
        $this->assertNull($parser->parse('spotify:playlist:4kwbbhuemSkRWvtN5dkWmb'));
        $this->assertNull($parser->parse('https://open.spotify.com/playlist/4kwbbhuemSkRWvtN5dkWmb'));
        $this->assertNull($parser->parse('not a spotify track'));
    }

    public function test_it_normalizes_canonical_track_url(): void
    {
        $parser = new SpotifyTrackInputParser;

        $this->assertSame(
            'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            $parser->canonicalUrl('spotify:track:4kwbbhuemSkRWvtN5dkWmb'),
        );
    }
}
