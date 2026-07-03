<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->company(),
            'company_name' => $this->faker->company(),
            'document' => $this->faker->numerify('##############'),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'notes' => null,
            'status' => 'active',
            'metadata' => null,
        ];
    }
}
