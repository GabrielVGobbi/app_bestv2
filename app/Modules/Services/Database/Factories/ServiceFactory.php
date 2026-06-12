<?php

declare(strict_types=1);

namespace App\Modules\Services\Database\Factories;

use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'base_price' => fake()->numberBetween(0, 500000),
            'currency' => 'BRL',
            'is_active' => true,
            'metadata' => [
                'requires_period' => fake()->boolean(),
                'requires_asset_reference' => fake()->boolean(),
                'delivery_mode' => fake()->randomElement(['manual', 'digital', 'operational', 'external']),
                'default_notes' => fake()->optional()->sentence(),
            ],
        ];
    }
}
