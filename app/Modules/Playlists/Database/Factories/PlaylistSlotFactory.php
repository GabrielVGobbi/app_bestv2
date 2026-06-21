<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Factories;

use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlaylistSlot>
 */
class PlaylistSlotFactory extends Factory
{
    protected $model = PlaylistSlot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'playlist_top_id' => PlaylistTop::factory(),
            'sale_item_id' => SaleItem::factory(),
            'status' => PlaylistSlotStatus::PendingSchedule,
            'starts_on' => null,
            'ends_on' => null,
            'quantity' => 1,
            'reserved_until' => null,
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'track_name' => fake()->sentence(3),
            'track_artists' => null,
            'track_album_name' => null,
            'track_duration_ms' => null,
            'track_isrc' => null,
            'track_metadata' => null,
            'notes' => null,
            'created_by_id' => null,
            'canceled_at' => null,
            'canceled_by_id' => null,
            'cancellation_reason' => null,
        ];
    }
}
