<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum PlaylistDeliveryTaskStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case Pending = 'pending';
    case Completed = 'completed';
    case Canceled = 'canceled';

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'pending' => ['label' => 'Pendente', 'color' => 'amber', 'icon' => 'CircleDashed'],
        'completed' => ['label' => 'Concluída', 'color' => 'emerald', 'icon' => 'CheckCircle2'],
        'canceled' => ['label' => 'Cancelada', 'color' => 'red', 'icon' => 'XCircle'],
    ];
}
