<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Database\Factories;

use App\Modules\Addresses\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'zipcode' => $this->faker->numerify('########'),
            'street' => $this->faker->streetName(),
            'number' => (string) $this->faker->numberBetween(1, 2500),
            'complement' => $this->faker->optional(0.3)->randomElement(['Apto 12', 'Sala 301', 'Bloco B']),
            'district' => $this->faker->city(),
            'neighborhood' => $this->faker->city(),
            'city' => $this->faker->city(),
            'state' => $this->faker->randomElement(['SP', 'RJ', 'MG', 'PR', 'RS', 'DF', 'BA']),
            'country' => (string) config('addresses.default_country', 'Brasil'),
            'is_default' => false,
        ];
    }
}
