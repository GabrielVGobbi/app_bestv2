<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Status;

use App\Modules\Playlists\Actions\DeliveryTasks\CancelPendingPlaylistDeliveryTasks;
use App\Modules\Playlists\Actions\DeliveryTasks\CreatePlaylistDeliveryTask;
use App\Modules\Playlists\Actions\RefreshPlaylistTopOccupiedCount;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleCampaignFinished;
use App\Modules\Sales\Models\Sale;
use App\Supports\StatusTransitions\StatusTransitionResult;
use App\Supports\StatusTransitions\StatusTransitionService;
use Illuminate\Support\Facades\DB;

class FinishSaleCampaign
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
        private readonly RefreshPlaylistTopOccupiedCount $refreshPlaylistTopOccupiedCount,
        private readonly CancelPendingPlaylistDeliveryTasks $cancelPendingPlaylistDeliveryTasks,
        private readonly CreatePlaylistDeliveryTask $createPlaylistDeliveryTask,
    ) {}

    public function handle(Sale $sale, bool $confirmed = false): StatusTransitionResult
    {
        return DB::transaction(function () use ($sale, $confirmed): StatusTransitionResult {
            $lockedSale = Sale::query()
                ->with('saleItems')
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $playlistSaleItemIds = $lockedSale->saleItems
                ->where('type', SaleItemType::PlaylistSlot)
                ->pluck('id');

            $playlistSlots = PlaylistSlot::query()
                ->with('playlistTop')
                ->whereIn('sale_item_id', $playlistSaleItemIds)
                ->whereNotIn('status', [
                    PlaylistSlotStatus::Finished,
                    PlaylistSlotStatus::Canceled,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($playlistSlots as $playlistSlot) {
                $this->cancelPendingPlaylistDeliveryTasks->handle(
                    playlistSlot: $playlistSlot,
                    types: [
                        PlaylistDeliveryTaskType::AddTrack,
                        PlaylistDeliveryTaskType::ReplaceTrack,
                    ],
                    user: auth()->user(),
                    reason: __('Campanha finalizada antes da tarefa ser concluída.'),
                );

                $this->createPlaylistDeliveryTask->handle($playlistSlot, PlaylistDeliveryTaskType::RemoveTrack);

                $playlistSlot->forceFill(['status' => PlaylistSlotStatus::Finished])->save();
                $this->refreshPlaylistTopOccupiedCount->handle($playlistSlot->playlistTop);
            }

            $result = $this->statusTransitionService->transition(
                model: $lockedSale,
                newStatus: SaleStatus::CampaignFinished,
                metadata: ['action' => self::class, 'domain' => 'sales'],
                confirmed: $confirmed,
            );

            SaleCampaignFinished::dispatch($lockedSale->refresh());

            return $result;
        });
    }
}
