<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialAmountOperation: string
{
    case Add = 'add';
    case Subtract = 'subtract';
}
