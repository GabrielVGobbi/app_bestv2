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
    case Informational = 'informational';
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
            self::Informational => 'Informativo',
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
            self::Informational => FinancialAmountOperation::None,
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

    /** @return list<FinancialAmountNature> */
    public function allowedNatures(): array
    {
        return match ($this) {
            self::Principal => [FinancialAmountNature::Principal],
            self::Discount, self::Withholding => [FinancialAmountNature::Deduction],
            self::Interest, self::Penalty, self::Fee => [FinancialAmountNature::Addition],
            self::Tax => [FinancialAmountNature::Addition, FinancialAmountNature::Informational],
            self::Informational => [FinancialAmountNature::Informational],
            self::Adjustment, self::Other => [
                FinancialAmountNature::Addition,
                FinancialAmountNature::Deduction,
                FinancialAmountNature::Informational,
            ],
        };
    }

    public function defaultNature(?FinancialAmountOperation $operation = null): FinancialAmountNature
    {
        $allowedNatures = $this->allowedNatures();

        if (count($allowedNatures) === 1) {
            return $allowedNatures[0];
        }

        if ($operation === FinancialAmountOperation::None) {
            return FinancialAmountNature::Informational;
        }

        return $operation === FinancialAmountOperation::Subtract
            ? FinancialAmountNature::Deduction
            : FinancialAmountNature::Addition;
    }
}
