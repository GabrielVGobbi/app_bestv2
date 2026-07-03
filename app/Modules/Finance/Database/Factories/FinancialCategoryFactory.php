<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Modules\Finance\Models\FinancialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FinancialCategoryFactory extends Factory
{
    protected $model = FinancialCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'code' => strtoupper($this->faker->bothify('CAT-###')),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'type' => FinancialCategoryType::Expense,
            'color' => '#64748b',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function income(): self
    {
        return $this->state(fn (): array => [
            'type' => FinancialCategoryType::Income,
        ]);
    }

    public function expense(): self
    {
        return $this->state(fn (): array => [
            'type' => FinancialCategoryType::Expense,
        ]);
    }

    public function transfer(): self
    {
        return $this->state(fn (): array => [
            'type' => FinancialCategoryType::Transfer,
        ]);
    }
}
