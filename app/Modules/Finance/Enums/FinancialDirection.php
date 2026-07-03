<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialDirection: string
{
    case Receivable = 'receivable';
    case Payable = 'payable';
}
