<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum ReconciliationMatchStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Reversed = 'reversed';

    private const array PRESENTERS = [
        'suggested' => ['label' => 'Sugerido', 'color' => 'blue', 'icon' => 'Sparkles'],
        'confirmed' => ['label' => 'Confirmado', 'color' => 'emerald', 'icon' => 'CheckCircle2'],
        'rejected' => ['label' => 'Rejeitado', 'color' => 'zinc', 'icon' => 'XCircle'],
        'reversed' => ['label' => 'Desfeito', 'color' => 'violet', 'icon' => 'RotateCcw'],
    ];
}
