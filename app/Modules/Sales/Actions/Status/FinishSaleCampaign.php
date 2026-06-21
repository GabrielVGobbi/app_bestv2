<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Status;

use App\Modules\Playlists\Actions\RefreshPlaylistTopOccupiedCount;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
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
    ) {}

    public function handle(Sale $sale, bool $confirmed = false): StatusTransitionResult
    {
        return DB::transaction(function () use ($sale, $confirmed): StatusTransitionResult {
            $lockedSale = Sale::query()
                ->with('saleItems.sellable')
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($lockedSale->saleItems as $saleItem) {
                if (! $saleItem->sellable instanceof PlaylistSlot || $saleItem->sellable->status !== PlaylistSlotStatus::Active) {
                    continue;
                }

                $saleItem->sellable->forceFill(['status' => PlaylistSlotStatus::Finished])->save();
                $this->refreshPlaylistTopOccupiedCount->handle($saleItem->sellable->playlistTop);
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
