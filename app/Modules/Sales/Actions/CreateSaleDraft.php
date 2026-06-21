<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions;

use App\Models\User;
use App\Modules\Sales\Actions\Items\UpsertSaleItem;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;

class CreateSaleDraft
{
    public function __construct(
        private readonly UpsertSaleItem $upsertSaleItem,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user): Sale {
            $sale = new Sale([
                'company_id' => $data['company_id'],
                'client_id' => $data['client_id'],
                'seller_id' => $user->id,
                'created_by_id' => $user->id,
                'status' => SaleStatus::Draft,
                'order_number' => $this->nextOrderNumber(),
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal_amount' => 0,
                'discount_amount' => 0,
                'currency' => 'BRL',
            ]);

            $sale->total_amount = $this->calculateTotal(
                subtotalAmount: $sale->subtotal_amount,
                discountAmount: $sale->discount_amount,
            );

            $sale->save();

            foreach ($this->saleItems($data) as $item) {
                $this->upsertSaleItem->handle($sale, [
                    ...$item,
                    'discount_amount' => 0,
                ], $user);
            }

            return $sale->refresh();
        });
    }

    private function nextOrderNumber(): string
    {
        do {
            $orderNumber = 'VEN-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Sale::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function calculateTotal(int $subtotalAmount, int $discountAmount): int
    {
        return max(0, $subtotalAmount - $discountAmount);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function saleItems(array $data): array
    {
        $items = $data['items'] ?? [];

        foreach ($data['service_items'] ?? [] as $serviceItem) {
            $items[] = [
                ...$serviceItem,
                'type' => 'service',
            ];
        }

        return $items;
    }
}
