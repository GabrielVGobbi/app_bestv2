<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialMovementDirection: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';
}
