<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions;

use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use Carbon\CarbonInterface;

class CheckPlaylistSlotAvailability
{
    /**
     * @return array{capacity: int, occupied: int, available: int, can_fit_one: bool}
     */
    public function handle(PlaylistTop $playlistTop, CarbonInterface|string $startsOn, CarbonInterface|string $endsOn): array
    {
        $occupied = (int) PlaylistSlot::query()
            ->whereBelongsTo($playlistTop)
            ->whereIn('status', [PlaylistSlotStatus::Reserved, PlaylistSlotStatus::Active])
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->sum('quantity');

        $available = max(0, $playlistTop->slots_quantity - $occupied);

        return [
            'capacity' => $playlistTop->slots_quantity,
            'occupied' => $occupied,
            'available' => $available,
            'can_fit_one' => $available >= 1,
        ];
    }
}
