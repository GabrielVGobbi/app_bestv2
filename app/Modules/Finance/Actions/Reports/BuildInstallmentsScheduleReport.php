<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reports;

use App\Models\User;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Database\Eloquent\Builder;

class BuildInstallmentsScheduleReport
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    /**
     * @param  array{category_id?: int|null, company_id?: int|null, direction?: string|null, due_from?: string|null, due_to?: string|null, limit?: int|null, status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters = [], ?User $user = null): array
    {
        $categoryIds = $this->categoryIds($filters['category_id'] ?? null);
        $limit = min(200, max(1, (int) ($filters['limit'] ?? 100)));

        $query = FinancialInstallment::query();

        if ($user instanceof User) {
            $query = $this->companyAccess->scope($query, $user);
        }

        $query
            ->with(['financialEntry.category', 'financialEntry.counterparty'])
            ->open()
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $query): Builder => $query->where('status', (string) $filters['status'])
            )
            ->when(
                filled($filters['due_from'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('due_date', '>=', (string) $filters['due_from'])
            )
            ->when(
                filled($filters['due_to'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('due_date', '<=', (string) $filters['due_to'])
            )
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

        $summaryQuery = clone $query;

        $items = $query
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return [
            'summary' => [
                'count' => (int) $summaryQuery->count(),
                'open_amount' => (int) $summaryQuery->sum('balance_amount'),
                'truncated' => $items->count() >= $limit,
                'limit' => $limit,
            ],
            'items' => $items->map(fn (FinancialInstallment $installment): array => $this->item($installment))->values()->all(),
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
            'direction' => $installment->direction?->value,
            'direction_label' => $installment->directionLabel(),
            'counterparty_label' => $entry?->counterparty_label,
            'entry' => $entry ? [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'description' => $entry->description,
                'origin_label' => $entry->originLabel(),
                'origin_display_name' => $entry->originDisplayName(),
            ] : null,
            'category' => $entry?->category ? [
                'id' => $entry->category->id,
                'name' => $entry->category->name,
                'full_path' => $entry->category->full_path,
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
