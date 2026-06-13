<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Factories;

use App\Modules\Playlists\Models\Top;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Top>
 */
class TopFactory extends Factory
{
    protected $model = Top::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = 'TOP '.fake()->unique()->numberBetween(1, 1000);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'default_slots_quantity' => fake()->numberBetween(1, 20),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
