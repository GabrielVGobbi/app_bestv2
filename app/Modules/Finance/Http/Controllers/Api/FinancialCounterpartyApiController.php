<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use ValueError;

class FinancialCounterpartyApiController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FinancialEntry::class);

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'direction' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $companyId = filled($validated['company_id'] ?? null) ? (int) $validated['company_id'] : null;
        $direction = $this->directionOrNull($validated['direction'] ?? null);
        $search = trim((string) ($validated['search'] ?? ''));
        $limit = min(50, max(1, (int) ($validated['limit'] ?? $validated['per_page'] ?? 20)));

        $options = collect();

        if ($direction === null || $direction === FinancialDirection::Receivable) {
            $options = $options->merge($this->clientOptions($companyId, $search, $limit));
        }

        if ($direction === null || $direction === FinancialDirection::Payable) {
            $options = $options->merge($this->supplierOptions($companyId, $search, $limit));
        }

        return response()->json([
            'data' => $options
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->take($limit)
                ->all(),
        ]);
    }

    /**
     * @return Collection<int, array{value: string, label: string, description: string}>
     */
    private function clientOptions(?int $companyId, string $search, int $limit): Collection
    {
        return Client::query()
            ->when(
                $companyId !== null,
                fn (Builder $query): Builder => $query->where(function (Builder $companyQuery) use ($companyId): void {
                    $companyQuery
                        ->whereNull('company_id')
                        ->orWhere('company_id', $companyId);
                })
            )
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'company_name'])
            ->map(fn (Client $client): array => [
                'value' => $client->getMorphClass().'|'.$client->id,
                'label' => $client->name,
                'description' => trim('Cliente'.($client->company_name ? " - {$client->company_name}" : '')),
            ]);
    }

    /**
     * @return Collection<int, array{value: string, label: string, description: string}>
     */
    private function supplierOptions(?int $companyId, string $search, int $limit): Collection
    {
        return Supplier::query()
            ->when(
                $companyId !== null,
                fn (Builder $query): Builder => $query->where(function (Builder $companyQuery) use ($companyId): void {
                    $companyQuery
                        ->whereNull('company_id')
                        ->orWhere('company_id', $companyId);
                })
            )
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'company_name'])
            ->map(fn (Supplier $supplier): array => [
                'value' => $supplier->getMorphClass().'|'.$supplier->id,
                'label' => $supplier->name,
                'description' => trim('Fornecedor'.($supplier->company_name ? " - {$supplier->company_name}" : '')),
            ]);
    }

    private function directionOrNull(mixed $value): ?FinancialDirection
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof FinancialDirection) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return FinancialDirection::from($value);
            } catch (ValueError) {
                return null;
            }
        }

        return null;
    }
}
