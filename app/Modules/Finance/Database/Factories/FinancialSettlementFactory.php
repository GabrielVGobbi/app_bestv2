<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialSettlementFactory extends Factory
{
    protected $model = FinancialSettlement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'financial_entry_id' => FinancialEntry::factory(),
            'direction' => FinancialDirection::Receivable,
            'settled_at' => now(),
            'financial_account_id' => null,
            'payment_method_id' => null,
            'gross_amount' => 10000,
            'discount_amount' => 0,
            'interest_amount' => 0,
            'penalty_amount' => 0,
            'net_amount' => 10000,
            'origin' => 'manual',
            'status' => FinancialSettlementStatus::Posted,
            'created_by_id' => null,
            'reversed_at' => null,
            'reversed_by_id' => null,
            'reverse_reason' => null,
            'metadata' => null,
        ];
    }
}
