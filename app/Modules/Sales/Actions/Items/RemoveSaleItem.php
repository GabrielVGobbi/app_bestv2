<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Items;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveSaleItem
{
    public function __construct(private readonly SyncSaleTotals $syncSaleTotals) {}

    public function handle(Sale $sale, SaleItem $saleItem): void
    {
        if (! $sale->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('Itens só podem ser removidos em vendas editáveis.'),
            ]);
        }

        if ($saleItem->sale_id !== $sale->id) {
            throw ValidationException::withMessages([
                'item' => __('O item informado não pertence a esta venda.'),
            ]);
        }

        DB::transaction(function () use ($sale, $saleItem): void {
            $saleItem->delete();

            $this->syncSaleTotals->handle($sale);
        });
    }
}
