<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Services;

use App\Modules\ExternalApi\Core\Abstracts\AbstractApiService;
use App\Modules\ExternalApi\Core\Contracts\ConfigurationInterface;
use App\Modules\ExternalApi\Core\Contracts\HttpClientInterface;
use App\Modules\ExternalApi\Modules\Spotify\Configuration\SpotifyConfiguration;
use App\Modules\ExternalApi\Modules\Spotify\Http\SpotifyHttpClient;

class SpotifyManager extends AbstractApiService
{
    private ?AuthService $authService = null;

    private ?SearchService $searchService = null;

    private ?TrackService $trackService = null;

    private ?ArtistService $artistService = null;

    private ?AlbumService $albumService = null;

    private ?PlaylistService $playlistService = null;

    public function __construct(
        ConfigurationInterface $configuration,
        HttpClientInterface $httpClient
    ) {
        parent::__construct($configuration, $httpClient);
    }

    public function getName(): string
    {
        return 'Spotify';
    }

    public function getVersion(): string
    {
        return 'v1';
    }

    public function auth(): AuthService
    {
        return $this->authService ??= new AuthService($this->configuration(), $this->client());
    }

    public function search(): SearchService
    {
        return $this->searchService ??= new SearchService($this->client(), $this->auth());
    }

    public function tracks(): TrackService
    {
        return $this->trackService ??= new TrackService($this->client(), $this->auth());
    }

    public function artists(): ArtistService
    {
        return $this->artistService ??= new ArtistService($this->client(), $this->auth());
    }

    public function albums(): AlbumService
    {
        return $this->albumService ??= new AlbumService($this->client(), $this->auth());
    }

    public function playlists(): PlaylistService
    {
        return $this->playlistService ??= new PlaylistService($this->client(), $this->auth());
    }

    private function configuration(): SpotifyConfiguration
    {
        /** @var SpotifyConfiguration $configuration */
        $configuration = $this->configuration;

        return $configuration;
    }

    private function client(): SpotifyHttpClient
    {
        /** @var SpotifyHttpClient $httpClient */
        $httpClient = $this->httpClient;

        return $httpClient;
    }
}
