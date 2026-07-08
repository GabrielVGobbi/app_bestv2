<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum BankStatementLineStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case Pending = 'pending';
    case Reconciled = 'reconciled';
    case Ignored = 'ignored';

    private const array PRESENTERS = [
        'pending' => ['label' => 'Pendente', 'color' => 'amber', 'icon' => 'Clock3'],
        'reconciled' => ['label' => 'Conciliado', 'color' => 'emerald', 'icon' => 'CheckCircle2'],
        'ignored' => ['label' => 'Ignorado', 'color' => 'zinc', 'icon' => 'CircleSlash'],
    ];
}
