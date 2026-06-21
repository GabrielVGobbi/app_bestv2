<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum SaleItemStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case Draft = 'draft';
    case Reserved = 'reserved';
    case Active = 'active';
    case Finished = 'finished';
    case Canceled = 'canceled';

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'draft' => ['label' => 'Rascunho', 'color' => 'zinc', 'icon' => 'FilePenLine'],
        'reserved' => ['label' => 'Reservado', 'color' => 'amber', 'icon' => 'CalendarClock'],
        'active' => ['label' => 'Ativo', 'color' => 'emerald', 'icon' => 'PlayCircle'],
        'finished' => ['label' => 'Finalizado', 'color' => 'sky', 'icon' => 'CheckCircle2'],
        'canceled' => ['label' => 'Cancelado', 'color' => 'red', 'icon' => 'XCircle'],
    ];
}
