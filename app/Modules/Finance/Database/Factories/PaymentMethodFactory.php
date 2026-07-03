<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\PaymentMethodType;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'type' => PaymentMethodType::Other,
            'requires_reference' => false,
            'is_active' => true,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }
}
