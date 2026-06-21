<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Actions;

use App\Models\User;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchedulePlaylistSlot
{
    public function __construct(
        private readonly CheckPlaylistSlotAvailability $checkPlaylistSlotAvailability,
        private readonly RefreshPlaylistTopOccupiedCount $refreshPlaylistTopOccupiedCount,
    ) {}

    public function handle(PlaylistSlot $playlistSlot, CarbonInterface|string $startsOn, CarbonInterface|string $endsOn, User $user): PlaylistSlot
    {
        return DB::transaction(function () use ($playlistSlot, $startsOn, $endsOn, $user): PlaylistSlot {
            $slot = PlaylistSlot::query()
                ->with(['playlistTop', 'saleItem.sale'])
                ->whereKey($playlistSlot->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->status !== PlaylistSlotStatus::PendingSchedule) {
                throw ValidationException::withMessages([
                    'status' => __('Apenas vagas pendentes podem ser agendadas.'),
                ]);
            }

            if ($slot->saleItem?->sale === null || $slot->saleItem->sale->status !== SaleStatus::CampaignStarted) {
                throw ValidationException::withMessages([
                    'sale' => __('A venda precisa estar confirmada para agendar a vaga.'),
                ]);
            }

            if ($endsOn < $startsOn) {
                throw ValidationException::withMessages([
                    'ends_on' => __('A data final deve ser igual ou posterior à data inicial.'),
                ]);
            }

            $playlistTop = PlaylistTop::query()
                ->whereKey($slot->playlist_top_id)
                ->lockForUpdate()
                ->firstOrFail();

            $availability = $this->checkPlaylistSlotAvailability->handle($playlistTop, $startsOn, $endsOn);

            if ($availability['available'] < $slot->quantity) {
                throw ValidationException::withMessages([
                    'playlist_top_id' => __('Não há vagas disponíveis para o período informado.'),
                ]);
            }

            $slot->forceFill([
                'status' => PlaylistSlotStatus::Active,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'created_by_id' => $slot->created_by_id ?? $user->id,
            ])->save();

            if ($slot->saleItem !== null) {
                $slot->saleItem->forceFill([
                    'status' => SaleItemStatus::Active,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                ])->save();
            }

            $this->refreshPlaylistTopOccupiedCount->handle($playlistTop);

            return $slot->refresh();
        });
    }
}
