<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Status;

use App\Modules\Sales\Actions\Items\RecalculateSaleItemTotals;
use App\Modules\Sales\Actions\Items\SyncSaleTotals;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use App\Supports\StatusTransitions\StatusTransitionResult;
use App\Supports\StatusTransitions\StatusTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteSale
{
    public function __construct(
        private readonly StatusTransitionService $statusTransitionService,
        private readonly RecalculateSaleItemTotals $recalculateSaleItemTotals,
        private readonly SyncSaleTotals $syncSaleTotals,
    ) {}

    public function handle(Sale $sale): StatusTransitionResult
    {
        return DB::transaction(function () use ($sale): StatusTransitionResult {
            $lockedSale = Sale::query()
                ->with('saleItems')
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->saleItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => __('A venda precisa ter pelo menos um item antes do orçamento.'),
                ]);
            }

            foreach ($lockedSale->saleItems as $saleItem) {
                $this->recalculateSaleItemTotals->handle($saleItem)->save();
            }

            $this->syncSaleTotals->handle($lockedSale);

            return $this->statusTransitionService->transition(
                model: $lockedSale,
                newStatus: SaleStatus::Quoted,
                metadata: ['action' => self::class, 'domain' => 'sales'],
            );
        });
    }
}
