<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum PlaylistSlotStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case PendingSchedule = 'pending_schedule';
    case Reserved = 'reserved';
    case Active = 'active';
    case Finished = 'finished';
    case Canceled = 'canceled';

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'pending_schedule' => ['label' => 'Pendente de agenda', 'color' => 'amber', 'icon' => 'CalendarClock'],
        'reserved' => ['label' => 'Reservado', 'color' => 'blue', 'icon' => 'CalendarDays'],
        'active' => ['label' => 'Ativo', 'color' => 'emerald', 'icon' => 'PlayCircle'],
        'finished' => ['label' => 'Finalizado', 'color' => 'sky', 'icon' => 'CheckCircle2'],
        'canceled' => ['label' => 'Cancelado', 'color' => 'red', 'icon' => 'XCircle'],
    ];

    public function occupiesCapacity(): bool
    {
        return in_array($this, [self::Reserved, self::Active], true);
    }
}
