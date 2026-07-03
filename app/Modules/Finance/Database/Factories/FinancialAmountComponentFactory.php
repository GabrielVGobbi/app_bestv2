<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Models\FinancialAmountComponent;
use App\Modules\Finance\Models\FinancialEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialAmountComponentFactory extends Factory
{
    protected $model = FinancialAmountComponent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'amountable_type' => FinancialEntry::class,
            'amountable_id' => FinancialEntry::factory(),
            'code' => FinancialAmountComponentCode::Principal,
            'label' => 'Principal',
            'operation' => FinancialAmountOperation::Add,
            'amount' => 10000,
            'position' => 0,
            'metadata' => null,
        ];
    }
}
