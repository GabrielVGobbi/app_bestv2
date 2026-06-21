<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions;

use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;

class RefreshPlaylistTopOccupiedCount
{
    public function handle(PlaylistTop $playlistTop): PlaylistTop
    {
        $occupied = (int) PlaylistSlot::query()
            ->whereBelongsTo($playlistTop)
            ->whereIn('status', [PlaylistSlotStatus::Reserved, PlaylistSlotStatus::Active])
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->sum('quantity');

        $playlistTop->forceFill(['occupied_slots_count' => $occupied])->save();

        return $playlistTop->refresh();
    }
}
