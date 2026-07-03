<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Factories;

use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlaylistDeliveryTask>
 */
class PlaylistDeliveryTaskFactory extends Factory
{
    protected $model = PlaylistDeliveryTask::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'playlist_slot_id' => PlaylistSlot::factory(),
            'sale_item_id' => null,
            'sale_id' => null,
            'playlist_top_id' => null,
            'playlist_id' => null,
            'type' => PlaylistDeliveryTaskType::AddTrack,
            'status' => PlaylistDeliveryTaskStatus::Pending,
            'sale_order_number' => 'VEN-'.$this->faker->unique()->numerify('######'),
            'client_name' => $this->faker->name(),
            'playlist_name' => $this->faker->words(3, true),
            'top_name' => 'TOP 10',
            'from_track_spotify_id' => null,
            'from_track_spotify_url' => null,
            'from_track_name' => null,
            'from_track_artists' => null,
            'from_track_album_name' => null,
            'from_track_duration_ms' => null,
            'from_track_isrc' => null,
            'from_track_image_url' => null,
            'to_track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'to_track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'to_track_name' => $this->faker->sentence(3),
            'to_track_artists' => [['name' => $this->faker->name()]],
            'to_track_album_name' => null,
            'to_track_duration_ms' => null,
            'to_track_isrc' => null,
            'to_track_image_url' => null,
            'completed_at' => null,
            'completed_by_id' => null,
            'canceled_at' => null,
            'canceled_by_id' => null,
            'cancellation_reason' => null,
            'metadata' => null,
        ];
    }
}
