<?php

namespace App\Modules\ExternalApi\Modules\Spotify\Enums;

enum SpotifySearchType: string
{
    case Track = 'track';
    case Artist = 'artist';
    case Album = 'album';
    case Playlist = 'playlist';
}
