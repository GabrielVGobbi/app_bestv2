<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum FinancialSettlementStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case Posted = 'posted';
    case Reversed = 'reversed';

    /** @var array<string, array{label: string, color: string, icon: string}> */
    private const array PRESENTERS = [
        'posted' => ['label' => 'Baixada', 'color' => 'emerald', 'icon' => 'CheckCircle2'],
        'reversed' => ['label' => 'Estornada', 'color' => 'violet', 'icon' => 'RotateCcw'],
    ];
}
