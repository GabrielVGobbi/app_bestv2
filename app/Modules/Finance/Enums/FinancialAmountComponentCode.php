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

    public function label(): string
    {
        return match ($this) {
            self::Principal => 'Principal',
            self::Discount => 'Desconto',
            self::Tax => 'Imposto',
            self::Interest => 'Juros',
            self::Penalty => 'Multa',
            self::Fee => 'Taxa',
            self::Withholding => 'Retenção',
            self::Adjustment => 'Ajuste',
            self::Other => 'Outro',
        };
    }

    public function fixedOperation(): ?FinancialAmountOperation
    {
        return match ($this) {
            self::Principal,
            self::Tax,
            self::Interest,
            self::Penalty,
            self::Fee => FinancialAmountOperation::Add,
            self::Discount,
            self::Withholding => FinancialAmountOperation::Subtract,
            self::Adjustment,
            self::Other => null,
        };
    }

    public function requiresDescription(): bool
    {
        return $this === self::Other;
    }

    public function isAvailableAsManualAdjustment(): bool
    {
        return ! in_array($this, [self::Principal, self::Adjustment], true);
    }
}
