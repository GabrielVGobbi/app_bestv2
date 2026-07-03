<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialAmountComponentCode: string
{
    case Principal = 'principal';
    case Discount = 'discount';
    case Tax = 'tax';
    case Interest = 'interest';
    case Penalty = 'penalty';
    case Fee = 'fee';
    case Withholding = 'withholding';
    case Adjustment = 'adjustment';
    case Other = 'other';
}
