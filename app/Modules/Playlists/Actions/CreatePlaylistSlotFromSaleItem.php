<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions;

use App\Models\User;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePlaylistSlotFromSaleItem
{
    public function __construct(
        private readonly CheckPlaylistSlotAvailability $checkPlaylistSlotAvailability,
        private readonly RefreshPlaylistTopOccupiedCount $refreshPlaylistTopOccupiedCount,
    ) {}

    public function handle(SaleItem $saleItem, User $user): PlaylistSlot
    {
        if ($saleItem->type !== SaleItemType::PlaylistSlot || $saleItem->playlist_top_id === null) {
            throw ValidationException::withMessages([
                'type' => __('Apenas itens de vaga de playlist podem criar ocupação.'),
            ]);
        }

        if (blank($saleItem->track_name) || blank($saleItem->track_spotify_url)) {
            throw ValidationException::withMessages([
                'track_name' => __('A vaga precisa de música e referência Spotify.'),
            ]);
        }

        return DB::transaction(function () use ($saleItem, $user): PlaylistSlot {
            $playlistTop = PlaylistTop::query()
                ->whereKey($saleItem->playlist_top_id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = PlaylistSlotStatus::PendingSchedule;

            if ($saleItem->hasCompletePeriod()) {
                $availability = $this->checkPlaylistSlotAvailability->handle(
                    playlistTop: $playlistTop,
                    startsOn: $saleItem->starts_on,
                    endsOn: $saleItem->ends_on,
                );

                if ($availability['available'] < $saleItem->quantity) {
                    throw ValidationException::withMessages([
                        'playlist_top_id' => __('Não há vagas disponíveis para o período informado.'),
                    ]);
                }

                $status = PlaylistSlotStatus::Active;
            }

            $slot = PlaylistSlot::query()->create([
                'playlist_top_id' => $playlistTop->id,
                'sale_item_id' => $saleItem->id,
                'status' => $status,
                'starts_on' => $saleItem->starts_on,
                'ends_on' => $saleItem->ends_on,
                'quantity' => $saleItem->quantity,
                'track_spotify_id' => $saleItem->track_spotify_id,
                'track_spotify_url' => $saleItem->track_spotify_url,
                'track_name' => $saleItem->track_name,
                'track_artists' => $saleItem->track_artists,
                'track_album_name' => $saleItem->track_album_name,
                'track_duration_ms' => $saleItem->track_duration_ms,
                'track_isrc' => $saleItem->track_isrc,
                'track_metadata' => $saleItem->track_metadata,
                'created_by_id' => $user->id,
            ]);

            $saleItem->forceFill([
                'status' => $status === PlaylistSlotStatus::PendingSchedule ? SaleItemStatus::Reserved : SaleItemStatus::Active,
                'sellable_type' => $slot->getMorphClass(),
                'sellable_id' => $slot->id,
            ])->save();

            $this->refreshPlaylistTopOccupiedCount->handle($playlistTop);

            return $slot->refresh();
        });
    }
}
