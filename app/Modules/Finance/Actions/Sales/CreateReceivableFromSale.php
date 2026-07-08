<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Sales;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Sales\Models\Sale;

class CreateReceivableFromSale
{
    public function __construct(private readonly CreateManualFinancialEntry $createEntry) {}

    public function handle(Sale $sale): FinancialEntry
    {
        $sale = Sale::query()
            ->with(['client', 'saleItems'])
            ->findOrFail($sale->id);

        $existing = FinancialEntry::query()
            ->where('company_id', $sale->company_id)
            ->where('direction', FinancialDirection::Receivable)
            ->where('originable_type', $sale->getMorphClass())
            ->where('originable_id', $sale->id)
            ->first();

        if ($existing instanceof FinancialEntry) {
            return $existing->load(['amountComponents', 'installments']);
        }

        $components = [
            [
                'code' => FinancialAmountComponentCode::Principal,
                'label' => 'Venda confirmada',
                'operation' => FinancialAmountOperation::Add,
                'amount' => (int) $sale->subtotal_amount,
                'position' => 0,
                'metadata' => [
                    'sale_uuid' => $sale->uuid,
                    'item_count' => $sale->saleItems->count(),
                ],
            ],
        ];

        if ((int) $sale->discount_amount > 0) {
            $components[] = [
                'code' => FinancialAmountComponentCode::Discount,
                'label' => 'Desconto comercial',
                'operation' => FinancialAmountOperation::Subtract,
                'amount' => (int) $sale->discount_amount,
                'position' => 1,
                'metadata' => ['sale_uuid' => $sale->uuid],
            ];
        }

        return $this->createEntry->handle([
            'company_id' => $sale->company_id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $sale->client_id,
            'originable_type' => $sale->getMorphClass(),
            'originable_id' => $sale->id,
            'description' => $sale->title ?: "Recebivel da venda {$sale->order_number}",
            'issue_date' => $sale->confirmed_at?->toDateString() ?? now()->toDateString(),
            'competence_date' => $sale->confirmed_at?->toDateString() ?? now()->toDateString(),
            'due_date' => $sale->confirmed_at?->toDateString() ?? now()->toDateString(),
            'currency' => $sale->currency,
            'components' => $components,
            'metadata' => [
                'source' => 'sales.confirmed',
                'sale_uuid' => $sale->uuid,
                'order_number' => $sale->order_number,
            ],
        ]);
    }
}
