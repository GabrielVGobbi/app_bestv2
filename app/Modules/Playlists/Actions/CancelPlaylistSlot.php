<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions;

use App\Models\User;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Sales\Enums\SaleItemStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPlaylistSlot
{
    public function __construct(private readonly RefreshPlaylistTopOccupiedCount $refreshPlaylistTopOccupiedCount) {}

    public function handle(PlaylistSlot $playlistSlot, User $user, ?string $reason = null): PlaylistSlot
    {
        return DB::transaction(function () use ($playlistSlot, $user, $reason): PlaylistSlot {
            $slot = PlaylistSlot::query()
                ->with(['playlistTop', 'saleItem'])
                ->whereKey($playlistSlot->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->status === PlaylistSlotStatus::Finished) {
                throw ValidationException::withMessages([
                    'status' => __('Vagas finalizadas não podem ser canceladas.'),
                ]);
            }

            $slot->forceFill([
                'status' => PlaylistSlotStatus::Canceled,
                'canceled_at' => now(),
                'canceled_by_id' => $user->id,
                'cancellation_reason' => $reason,
            ])->save();

            if ($slot->saleItem !== null) {
                $slot->saleItem->forceFill([
                    'status' => SaleItemStatus::Canceled,
                ])->save();
            }

            $this->refreshPlaylistTopOccupiedCount->handle($slot->playlistTop);

            return $slot->refresh();
        });
    }
}
