<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum SpotifyPlaylistPresenceStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case Unchecked = 'unchecked';
    case Present = 'present';
    case Missing = 'missing';
    case SpotifyUnavailable = 'spotify_unavailable';
    case PlaylistMissingId = 'playlist_missing_id';

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'unchecked' => ['label' => 'Não verificado', 'color' => 'zinc', 'icon' => 'CircleHelp'],
        'present' => ['label' => 'Na playlist', 'color' => 'emerald', 'icon' => 'CheckCircle2'],
        'missing' => ['label' => 'Não encontrado', 'color' => 'amber', 'icon' => 'SearchX'],
        'spotify_unavailable' => ['label' => 'Spotify indisponível', 'color' => 'red', 'icon' => 'BadgeAlert'],
        'playlist_missing_id' => ['label' => 'Playlist sem ID', 'color' => 'zinc', 'icon' => 'Link2Off'],
    ];
}
