<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Items;

use App\Modules\Sales\Models\SaleItem;
use Illuminate\Validation\ValidationException;

class RecalculateSaleItemTotals
{
    public function handle(SaleItem $saleItem): SaleItem
    {
        $grossAmount = $saleItem->quantity * $saleItem->unit_amount;

        if ($saleItem->discount_amount > $grossAmount) {
            throw ValidationException::withMessages([
                'discount_amount' => __('O desconto do item não pode ser maior que o valor bruto.'),
            ]);
        }

        $saleItem->total_amount = max(0, $grossAmount - $saleItem->discount_amount);

        return $saleItem;
    }
}
