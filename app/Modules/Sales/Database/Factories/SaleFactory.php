<?php

declare(strict_types=1);

namespace App\Modules\Sales\Database\Factories;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'seller_id' => User::factory(),
            'created_by_id' => null,
            'status' => SaleStatus::Draft,
            'order_number' => 'VEN-'.$this->faker->unique()->numerify('######'),
            'title' => $this->faker->optional()->sentence(3),
            'notes' => null,
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'currency' => 'BRL',
        ];
    }
}
