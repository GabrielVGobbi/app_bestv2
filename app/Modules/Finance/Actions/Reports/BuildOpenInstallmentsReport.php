<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reports;

use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialInstallment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detalhe de parcelas em aberto, nas duas direcoes.
 *
 * Substitui o antigo relatorio de recebiveis em aberto, que era entry-level, so
 * atendia recebiveis e nao excluia status terminais, divergindo do dashboard. Aqui
 * a base sao os mesmos scopes usados pelos cards e pelas filas, entao os numeros
 * fecham entre as telas.
 */
class BuildOpenInstallmentsReport
{
    /**
     * @param  array{category_id?: int|null, company_id?: int|null, direction?: string|null, limit?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters = []): array
    {
        $categoryIds = $this->categoryIds($filters['category_id'] ?? null);
        $limit = min(500, max(1, (int) ($filters['limit'] ?? 200)));

        $base = FinancialInstallment::query()
            ->open()
            ->when(
                filled($filters['company_id'] ?? null),
                fn (Builder $query): Builder => $query->where('company_id', (int) $filters['company_id'])
            )
            ->when(
                filled($filters['direction'] ?? null),
                fn (Builder $query): Builder => $query->direction((string) $filters['direction'])
            )
            ->when(
                $categoryIds !== [],
                fn (Builder $query): Builder => $query->whereIn('category_id', $categoryIds)
            );

        $today = today()->toDateString();

        $count = (int) (clone $base)->count();
        $openAmount = (int) (clone $base)->sum('balance_amount');
        $overdueAmount = (int) (clone $base)->overdue($today)->sum('balance_amount');

        $items = (clone $base)
            ->with(['financialEntry', 'counterparty', 'category'])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return [
            'summary' => [
                'count' => $count,
                'open_amount' => $openAmount,
                'overdue_amount' => $overdueAmount,
                'upcoming_amount' => $openAmount - $overdueAmount,
                'truncated' => $items->count() >= $limit,
                'limit' => $limit,
            ],
            'items' => $items
                ->map(fn (FinancialInstallment $installment): array => $this->item($installment))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(FinancialInstallment $installment): array
    {
        $entry = $installment->financialEntry;
        $status = $installment->calculatedStatus();

        return [
            'id' => $installment->id,
            'uuid' => $installment->uuid,
            'company_id' => $installment->company_id,
            'direction' => $installment->direction?->value,
            'direction_label' => $installment->directionLabel(),
            'installment_number' => $installment->installment_number,
            'status' => $status->value,
            'status_presenter' => $status->toPresenterArray(),
            'due_date' => $installment->due_date?->toDateString(),
            'competence_date' => $installment->competence_date?->toDateString(),
            'original_amount' => (int) $installment->original_amount,
            'settled_amount' => (int) $installment->settled_amount,
            'balance_amount' => (int) $installment->balance_amount,
            'currency' => $installment->currency,
            'is_overdue' => $installment->isOverdue(),
            'counterparty_label' => $installment->counterpartyLabel(),
            'entry' => $entry ? [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'description' => $entry->description,
                'origin_label' => $entry->originLabel(),
            ] : null,
            'category' => $installment->category ? [
                'id' => $installment->category->id,
                'name' => $installment->category->name,
                'full_path' => $installment->category->full_path,
            ] : null,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function categoryIds(mixed $categoryId): array
    {
        if (blank($categoryId)) {
            return [];
        }

        $category = FinancialCategory::query()->find((int) $categoryId);

        if (! $category) {
            return [];
        }

        return [$category->id, ...$category->descendantIds()];
    }
}
