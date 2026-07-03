<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions\DeliveryTasks;

use App\Models\User;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;

class CancelPendingPlaylistDeliveryTasks
{
    /**
     * @param  array<int, PlaylistDeliveryTaskType>  $types
     */
    public function handle(PlaylistSlot $playlistSlot, array $types, ?User $user, string $reason): int
    {
        return PlaylistDeliveryTask::query()
            ->where('playlist_slot_id', $playlistSlot->id)
            ->whereIn('type', $types)
            ->where('status', PlaylistDeliveryTaskStatus::Pending)
            ->update([
                'status' => PlaylistDeliveryTaskStatus::Canceled,
                'canceled_at' => now(),
                'canceled_by_id' => $user?->id,
                'cancellation_reason' => $reason,
            ]);
    }
}
