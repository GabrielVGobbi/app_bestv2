<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Factories;

use App\Modules\Playlists\Models\MusicGenre;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MusicGenre>
 */
class MusicGenreFactory extends Factory
{
    protected $model = MusicGenre::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
