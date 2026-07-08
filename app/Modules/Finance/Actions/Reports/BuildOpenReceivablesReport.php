<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reports;

use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;

class BuildOpenReceivablesReport
{
    /**
     * @param  array{category_id?: int|null, company_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters = []): array
    {
        $categoryIds = $this->categoryIds($filters['category_id'] ?? null);

        $entries = FinancialEntry::query()
            ->with(['category', 'counterparty', 'installments'])
            ->where('direction', FinancialDirection::Receivable)
            ->where('balance_amount', '>', 0)
            ->when(isset($filters['company_id']), fn ($query) => $query->where('company_id', (int) $filters['company_id']))
            ->when($categoryIds !== [], fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->orderBy('due_date')
            ->get();

        return [
            'summary' => [
                'count' => $entries->count(),
                'open_amount' => (int) $entries->sum('balance_amount'),
            ],
            'items' => $entries->map(fn (FinancialEntry $entry): array => [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'description' => $entry->description,
                'due_date' => $entry->due_date?->toDateString(),
                'balance_amount' => (int) $entry->balance_amount,
                'counterparty_label' => $entry->counterparty_label ?? null,
                'category' => $entry->category ? [
                    'id' => $entry->category->id,
                    'name' => $entry->category->name,
                    'full_path' => $entry->category->full_path,
                ] : null,
            ])->values()->all(),
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
