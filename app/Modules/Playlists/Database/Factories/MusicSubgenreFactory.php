<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Factories;

use App\Modules\Playlists\Models\MusicSubgenre;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MusicSubgenre>
 */
class MusicSubgenreFactory extends Factory
{
    protected $model = MusicSubgenre::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
