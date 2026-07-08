<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

use App\Supports\Contracts\BadgeableEnum;
use App\Supports\Enums\Concerns\HasBadgePresenter;

enum BankStatementImportStatus: string implements BadgeableEnum
{
    use HasBadgePresenter;

    case Processed = 'processed';
    case Failed = 'failed';

    private const array PRESENTERS = [
        'processed' => ['label' => 'Processado', 'color' => 'emerald', 'icon' => 'CheckCircle2'],
        'failed' => ['label' => 'Falhou', 'color' => 'red', 'icon' => 'AlertCircle'],
    ];
}
