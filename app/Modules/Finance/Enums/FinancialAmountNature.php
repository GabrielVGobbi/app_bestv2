<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialAmountNature: string
{
    case Principal = 'principal';
    case Addition = 'addition';
    case Deduction = 'deduction';
    case Informational = 'informational';

    public function operation(): FinancialAmountOperation
    {
        return match ($this) {
            self::Principal, self::Addition => FinancialAmountOperation::Add,
            self::Deduction => FinancialAmountOperation::Subtract,
            self::Informational => FinancialAmountOperation::None,
        };
    }

    public function affectsNet(): bool
    {
        return $this !== self::Informational;
    }
}
