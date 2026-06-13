<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Factories;

use App\Modules\Playlists\Enums\PlaylistStatus;
use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Playlist>
 */
class PlaylistFactory extends Factory
{
    protected $model = Playlist::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'uuid' => (string) Str::uuid(),
            'music_genre_id' => MusicGenre::factory(),
            'name' => str($name)->title()->toString(),
            'slug' => Str::slug($name),
            'status' => PlaylistStatus::Active,
            'partner_name' => fake()->optional()->company(),
            'spotify_playlist_id' => Str::random(22),
            'spotify_url' => fake()->optional()->url(),
            'youtube_url' => fake()->optional()->url(),
            'followers_count' => fake()->numberBetween(0, 100000),
            'estimated_reach' => fake()->numberBetween(0, 150000),
            'external_metadata' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
