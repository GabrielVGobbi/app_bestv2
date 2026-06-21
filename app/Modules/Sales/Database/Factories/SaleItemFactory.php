<?php

declare(strict_types=1);

namespace App\Modules\Sales\Database\Factories;

use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitAmount = fake()->numberBetween(1000, 500000);
        $discountAmount = fake()->numberBetween(0, $quantity * $unitAmount);

        return [
            'uuid' => (string) Str::uuid(),
            'sale_id' => Sale::factory(),
            'type' => SaleItemType::Service,
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => max(0, ($quantity * $unitAmount) - $discountAmount),
            'currency' => 'BRL',
            'starts_on' => null,
            'ends_on' => null,
            'status' => SaleItemStatus::Draft,
            'service_id' => Service::factory(),
            'playlist_top_id' => null,
            'track_spotify_id' => null,
            'track_spotify_url' => null,
            'track_name' => null,
            'track_artists' => null,
            'track_album_name' => null,
            'track_duration_ms' => null,
            'track_isrc' => null,
            'track_metadata' => null,
            'spotify_playlist_check_status' => 'unchecked',
            'spotify_playlist_checked_at' => null,
            'spotify_playlist_track_position' => null,
            'spotify_playlist_match_type' => null,
            'spotify_playlist_id_checked' => null,
            'spotify_playlist_snapshot_id_checked' => null,
            'spotify_playlist_check_error' => null,
            'metadata' => null,
            'created_by_id' => null,
        ];
    }
}
