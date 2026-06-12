<?php

declare(strict_types=1);

namespace App\Modules\Companies\Database\Factories;

use App\Modules\Companies\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cnpj = fake()->unique()->numerify('##############');

        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'nickname' => fake()->companySuffix().' '.fake()->unique()->word(),
            'cnpj' => $cnpj,
            'fiscal_metadata' => [
                'cpfCnpj' => $cnpj,
                'nomeFantasia' => fake()->company(),
            ],
        ];
    }
}
