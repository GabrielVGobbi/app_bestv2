<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Factories;

use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlaylistTop>
 */
class PlaylistTopFactory extends Factory
{
    protected $model = PlaylistTop::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $slotsQuantity = fake()->numberBetween(1, 20);

        return [
            'uuid' => (string) Str::uuid(),
            'playlist_id' => Playlist::factory(),
            'top_id' => Top::factory(),
            'slots_quantity' => $slotsQuantity,
            'occupied_slots_count' => fake()->numberBetween(0, $slotsQuantity),
            'price' => fake()->numberBetween(0, 500000),
            'currency' => 'BRL',
            'metrics' => [
                'estimated_streams' => fake()->numberBetween(1000, 50000),
                'estimated_reach' => fake()->numberBetween(1000, 100000),
                'average_retention_days' => fake()->numberBetween(1, 60),
                'commercial_score' => fake()->numberBetween(1, 100),
            ],
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
