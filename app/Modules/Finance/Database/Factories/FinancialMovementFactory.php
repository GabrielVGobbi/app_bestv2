<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Models\FinancialMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialMovementFactory extends Factory
{
    protected $model = FinancialMovement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'financial_account_id' => null,
            'direction' => FinancialMovementDirection::Inflow,
            'amount' => 10000,
            'occurred_at' => now(),
            'source_type' => null,
            'source_id' => null,
            'reconciled_at' => null,
            'metadata' => null,
        ];
    }
}
