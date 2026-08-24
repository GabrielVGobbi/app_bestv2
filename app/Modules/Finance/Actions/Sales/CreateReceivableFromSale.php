<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Sales;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialCategoryType;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Sales\Models\Sale;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateReceivableFromSale
{
    public function __construct(private readonly CreateManualFinancialEntry $createEntry) {}

    /**
     * Cria o recebivel da venda de forma idempotente.
     *
     * A checagem previa resolve o caso comum. O indice unico
     * `financial_entries_origin_unique` resolve a corrida entre duas confirmacoes
     * simultaneas da mesma venda: quem perder a disputa recebe o registro que o
     * vencedor criou, em vez de estourar a excecao.
     */
    public function handle(Sale $sale): FinancialEntry
    {
        $sale = Sale::query()
            ->with(['client', 'saleItems'])
            ->findOrFail($sale->id);

        $existing = $this->findExistingReceivable($sale);

        if ($existing instanceof FinancialEntry) {
            return $existing->load(['amountComponents', 'installments']);
        }

        try {
            return $this->createEntry->handle($this->buildPayload($sale));
        } catch (UniqueConstraintViolationException $exception) {
            $concurrent = $this->findExistingReceivable($sale);

            if (! $concurrent instanceof FinancialEntry) {
                throw $exception;
            }

            return $concurrent->load(['amountComponents', 'installments']);
        }
    }

    private function findExistingReceivable(Sale $sale): ?FinancialEntry
    {
        return FinancialEntry::query()
            ->where('company_id', $sale->company_id)
            ->where('direction', FinancialDirection::Receivable)
            ->where('originable_type', $sale->getMorphClass())
            ->where('originable_id', $sale->id)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Sale $sale): array
    {
        $referenceDate = $sale->confirmed_at?->toDateString() ?? now()->toDateString();

        return [
            'company_id' => $sale->company_id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $sale->client_id,
            'originable_type' => $sale->getMorphClass(),
            'originable_id' => $sale->id,
            'description' => $sale->title ?: "Recebivel da venda {$sale->order_number}",
            'issue_date' => $referenceDate,
            'competence_date' => $referenceDate,
            'due_date' => $referenceDate,
            'category_id' => $this->salesCategoryId(),
            'currency' => $sale->currency,
            'components' => $this->buildComponents($sale),
            'metadata' => [
                'source' => 'sales.confirmed',
                'sale_uuid' => $sale->uuid,
                'order_number' => $sale->order_number,
            ],
        ];
    }

    private function salesCategoryId(): int
    {
        $seededCategory = FinancialCategory::query()
            ->whereNull('company_id')
            ->where('code', 'REC-VEND')
            ->whereDoesntHave('children')
            ->first();

        if ($seededCategory instanceof FinancialCategory) {
            return (int) $seededCategory->id;
        }

        return (int) FinancialCategory::query()->firstOrCreate(
            ['company_id' => null, 'code' => 'SYS-SALES', 'type' => FinancialCategoryType::Income->value],
            [
                'name' => 'Receitas de vendas',
                'slug' => 'receitas-de-vendas-sistema',
                'description' => 'Categoria sistêmica para recebíveis originados por vendas.',
                'sort_order' => 65534,
                'is_active' => true,
            ],
        )->id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildComponents(Sale $sale): array
    {
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

        return $components;
    }
}
