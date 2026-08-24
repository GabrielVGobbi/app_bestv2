<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialInstallmentFactory extends Factory
{
    protected $model = FinancialInstallment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'financial_entry_id' => FinancialEntry::factory(),
            'status' => FinancialEntryStatus::Open,
            'direction' => FinancialDirection::Receivable,
            'installment_number' => 1,
            'due_date' => now()->addDays(7)->toDateString(),
            'competence_date' => now()->toDateString(),
            'original_amount' => 10000,
            'settled_amount' => 0,
            'balance_amount' => 10000,
            'discount_amount' => 0,
            'interest_amount' => 0,
            'penalty_amount' => 0,
            'currency' => 'BRL',
            'settled_at' => null,
            'metadata' => null,
        ];
    }
}
