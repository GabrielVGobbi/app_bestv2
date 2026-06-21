<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Items;

use App\Models\User;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncSaleItemsFromPayload
{
    public function __construct(
        private readonly UpsertSaleItem $upsertSaleItem,
        private readonly RemoveSaleItem $removeSaleItem,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(Sale $sale, array $items, User $user): void
    {
        DB::transaction(function () use ($sale, $items, $user): void {
            $keptItemIds = [];

            foreach ($items as $itemPayload) {
                $saleItem = $this->itemFromPayload($sale, $itemPayload);
                $item = $this->upsertSaleItem->handle($sale, $itemPayload, $user, $saleItem);

                $keptItemIds[] = $item->id;
            }

            $sale->saleItems()
                ->when($keptItemIds !== [], fn ($query) => $query->whereNotIn('id', $keptItemIds))
                ->get()
                ->each(fn (SaleItem $saleItem) => $this->removeSaleItem->handle($sale, $saleItem));
        });
    }

    /**
     * @param  array<string, mixed>  $itemPayload
     */
    private function itemFromPayload(Sale $sale, array $itemPayload): ?SaleItem
    {
        if (blank($itemPayload['uuid'] ?? null)) {
            return null;
        }

        $saleItem = $sale->saleItems()
            ->where('uuid', $itemPayload['uuid'])
            ->first();

        if (! $saleItem instanceof SaleItem) {
            throw ValidationException::withMessages([
                'items' => __('Um dos itens informados nÃ£o pertence a esta venda.'),
            ]);
        }

        return $saleItem;
    }
}
