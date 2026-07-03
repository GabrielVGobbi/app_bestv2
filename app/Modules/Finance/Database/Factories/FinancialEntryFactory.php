<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialEntryFactory extends Factory
{
    protected $model = FinancialEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'created_by_id' => null,
            'direction' => FinancialDirection::Receivable,
            'status' => FinancialEntryStatus::Open,
            'counterparty_type' => null,
            'counterparty_id' => null,
            'originable_type' => null,
            'originable_id' => null,
            'origin_reference' => null,
            'category_id' => null,
            'description' => $this->faker->sentence(4),
            'issue_date' => now()->toDateString(),
            'competence_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'gross_amount' => 10000,
            'net_amount' => 10000,
            'settled_amount' => 0,
            'balance_amount' => 10000,
            'currency' => 'BRL',
            'metadata' => null,
        ];
    }
}
