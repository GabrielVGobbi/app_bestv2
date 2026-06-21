<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Status;

use App\Models\User;
use App\Modules\Playlists\Actions\CreatePlaylistSlotFromSaleItem;
use App\Modules\Sales\Actions\Items\RecalculateSaleItemTotals;
use App\Modules\Sales\Actions\Items\SyncSaleTotals;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleConfirmed;
use App\Modules\Sales\Models\Sale;
use App\Supports\StatusTransitions\StatusTransitionResult;
use App\Supports\StatusTransitions\StatusTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSale
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
        private readonly CreatePlaylistSlotFromSaleItem $createPlaylistSlotFromSaleItem,
        private readonly RecalculateSaleItemTotals $recalculateSaleItemTotals,
        private readonly SyncSaleTotals $syncSaleTotals,
    ) {}

    public function handle(Sale $sale, bool $confirmed = false): StatusTransitionResult
    {
        return DB::transaction(function () use ($sale, $confirmed): StatusTransitionResult {
            $lockedSale = Sale::query()
                ->with('saleItems')
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->seller_id === null) {
                throw ValidationException::withMessages([
                    'seller_id' => __('A venda precisa ter um vendedor antes da confirmação.'),
                ]);
            }

            if ($lockedSale->saleItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => __('A venda precisa ter pelo menos um item antes da confirmação.'),
                ]);
            }

            $user = auth()->user();

            if (! $user instanceof User) {
                throw ValidationException::withMessages([
                    'user' => __('Usuário autenticado é obrigatório para confirmar venda.'),
                ]);
            }

            foreach ($lockedSale->saleItems as $saleItem) {
                $this->recalculateSaleItemTotals->handle($saleItem)->save();

                if ($saleItem->type === SaleItemType::PlaylistSlot && $saleItem->sellable_id === null) {
                    $this->createPlaylistSlotFromSaleItem->handle($saleItem, $user);
                }
            }

            $this->syncSaleTotals->handle($lockedSale);

            $result = $this->statusTransitionService->transition(
                model: $lockedSale,
                newStatus: SaleStatus::CampaignStarted,
                metadata: ['action' => self::class, 'domain' => 'sales'],
                confirmed: $confirmed,
            );

            SaleConfirmed::dispatch($lockedSale->refresh());

            return $result;
        });
    }
}
