<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum PlaylistDeliveryTaskType: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case AddTrack = 'add_track';
    case RemoveTrack = 'remove_track';
    case ReplaceTrack = 'replace_track';

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'add_track' => ['label' => 'Adicionar', 'color' => 'emerald', 'icon' => 'ListPlus'],
        'remove_track' => ['label' => 'Remover', 'color' => 'red', 'icon' => 'ListMinus'],
        'replace_track' => ['label' => 'Editar', 'color' => 'blue', 'icon' => 'Replace'],
    ];
}
