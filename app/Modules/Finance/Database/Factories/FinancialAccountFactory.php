<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'type' => FinancialAccountType::BankAccount,
            'institution_name' => $this->faker->company(),
            'agency_number' => (string) $this->faker->numberBetween(1000, 9999),
            'account_number' => (string) $this->faker->numberBetween(10000, 999999),
            'account_digit' => (string) $this->faker->numberBetween(0, 9),
            'document_number' => null,
            'opening_balance_amount' => 0,
            'current_balance_amount' => 0,
            'balance_date' => now()->toDateString(),
            'currency' => 'BRL',
            'active' => true,
            'allows_negative_balance' => false,
            'metadata' => null,
        ];
    }
}
