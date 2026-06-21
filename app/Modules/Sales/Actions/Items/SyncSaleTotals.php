<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Items;

use App\Modules\Sales\Models\Sale;

class SyncSaleTotals
{
    public function handle(Sale $sale): Sale
    {
        $subtotalAmount = (int) $sale->saleItems()->sum('total_amount');

        $sale->forceFill([
            'subtotal_amount' => $subtotalAmount,
            'total_amount' => max(0, $subtotalAmount - $sale->discount_amount),
        ])->save();

        return $sale->refresh();
    }
}
