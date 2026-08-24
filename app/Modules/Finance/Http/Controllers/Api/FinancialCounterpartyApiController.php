<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use ValueError;

class FinancialCounterpartyApiController extends Controller
{
    public function options(Request $request, FinancialCompanyAccess $companyAccess): JsonResponse
    {
        Gate::authorize('viewAny', FinancialEntry::class);

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'direction' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'ids' => ['nullable'],
            'values' => ['nullable'],
        ]);

        $companyId = filled($validated['company_id'] ?? null) ? (int) $validated['company_id'] : null;
        $user = $request->user();

        if ($companyId !== null) {
            $companyAccess->authorize($user, $companyId);
        }
        $direction = $this->directionOrNull($validated['direction'] ?? null);
        $search = trim((string) ($validated['search'] ?? ''));
        $limit = min(50, max(1, (int) ($validated['limit'] ?? $validated['per_page'] ?? 20)));
        $lookupValues = $this->lookupTokens($request->input('values', $request->input('ids')));

        if ($lookupValues !== []) {
            return response()->json([
                'data' => $this->optionsFromValues($lookupValues, $companyId, $direction, $user, $companyAccess)->all(),
            ]);
        }

        $options = collect();

        if ($direction === null || $direction === FinancialDirection::Receivable) {
            $options = $options->merge($this->clientOptions($companyId, $search, $limit, $user, $companyAccess));
        }

        if ($direction === null || $direction === FinancialDirection::Payable) {
            $options = $options->merge($this->supplierOptions($companyId, $search, $limit, $user, $companyAccess));
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
    private function clientOptions(
        ?int $companyId,
        string $search,
        int $limit,
        User $user,
        FinancialCompanyAccess $companyAccess,
    ): Collection {
        $query = Client::query();
        $this->scopeCounterparties($query, $companyId, $user, $companyAccess);

        return $query
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
    private function supplierOptions(
        ?int $companyId,
        string $search,
        int $limit,
        User $user,
        FinancialCompanyAccess $companyAccess,
    ): Collection {
        $query = Supplier::query();
        $this->scopeCounterparties($query, $companyId, $user, $companyAccess);

        return $query
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

    /**
     * @return list<string>
     */
    private function lookupTokens(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : explode(',', (string) $raw);

        return collect($items)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '' && str_contains($item, '|'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return Collection<int, array{value: string, label: string, description: string}>
     */
    private function optionsFromValues(
        array $values,
        ?int $companyId,
        ?FinancialDirection $direction,
        User $user,
        FinancialCompanyAccess $companyAccess,
    ): Collection {
        $options = collect();
        $clientMorph = (new Client)->getMorphClass();
        $supplierMorph = (new Supplier)->getMorphClass();

        foreach ($values as $value) {
            [$type, $id] = explode('|', $value, 2);

            if (! ctype_digit($id)) {
                continue;
            }

            $clientMorph = (new Client)->getMorphClass();
            $supplierMorph = (new Supplier)->getMorphClass();

            if (
                $type === $clientMorph
                && ($direction === null || $direction === FinancialDirection::Receivable)
            ) {
                $clientQuery = Client::query();
                $this->scopeCounterparties($clientQuery, $companyId, $user, $companyAccess);

                $client = $clientQuery
                    ->when(
                        $companyId !== null,
                        fn (Builder $query): Builder => $query->where(function (Builder $companyQuery) use ($companyId): void {
                            $companyQuery
                                ->whereNull('company_id')
                                ->orWhere('company_id', $companyId);
                        })
                    )
                    ->find((int) $id);

                if ($client instanceof Client) {
                    $options->push([
                        'value' => $client->getMorphClass().'|'.$client->id,
                        'label' => $client->name,
                        'description' => trim('Cliente'.($client->company_name ? " - {$client->company_name}" : '')),
                    ]);
                }

                continue;
            }

            if (
                $type === $supplierMorph
                && ($direction === null || $direction === FinancialDirection::Payable)
            ) {
                $supplierQuery = Supplier::query();
                $this->scopeCounterparties($supplierQuery, $companyId, $user, $companyAccess);

                $supplier = $supplierQuery
                    ->when(
                        $companyId !== null,
                        fn (Builder $query): Builder => $query->where(function (Builder $companyQuery) use ($companyId): void {
                            $companyQuery
                                ->whereNull('company_id')
                                ->orWhere('company_id', $companyId);
                        })
                    )
                    ->find((int) $id);

                if ($supplier instanceof Supplier) {
                    $options->push([
                        'value' => $supplier->getMorphClass().'|'.$supplier->id,
                        'label' => $supplier->name,
                        'description' => trim('Fornecedor'.($supplier->company_name ? " - {$supplier->company_name}" : '')),
                    ]);
                }
            }
        }

        return $options->values();
    }

    /**
     * @param  Builder<Client|Supplier>  $query
     */
    private function scopeCounterparties(
        Builder $query,
        ?int $companyId,
        User $user,
        FinancialCompanyAccess $companyAccess,
    ): void {
        if ($companyId !== null) {
            $query->where(function (Builder $companyQuery) use ($companyId): void {
                $companyQuery->whereNull('company_id')->orWhere('company_id', $companyId);
            });

            return;
        }

        if ($companyAccess->isGlobalAdministrator($user)) {
            return;
        }

        $query->where(function (Builder $companyQuery) use ($user): void {
            $companyQuery
                ->whereNull('company_id')
                ->orWhereIn('company_id', $user->companies()->select('companies.id'));
        });
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
