<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountNature;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Finance\Models\FinancialSettlementComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialSettlementComponent>
 */
class FinancialSettlementComponentFactory extends Factory
{
    protected $model = FinancialSettlementComponent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'settlement_id' => FinancialSettlement::factory(),
            'code' => FinancialAmountComponentCode::Principal,
            'nature' => FinancialAmountNature::Principal,
            'label' => 'Principal',
            'operation' => FinancialAmountOperation::Add,
            'affects_net' => true,
            'amount' => 10000,
            'origin' => 'factory',
            'position' => 0,
            'metadata' => null,
        ];
    }
}
