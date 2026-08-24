<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Finance\Http\Requests\FinancialAccount\StoreFinancialAccountRequest;
use App\Modules\Finance\Http\Requests\FinancialAccount\UpdateFinancialAccountRequest;
use App\Modules\Finance\Http\Resources\FinancialAccountResource;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use App\Supports\Api\ApiDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FinancialAccountApiController extends Controller
{
    public function index(PaginationRequest $request, FinancialCompanyAccess $companyAccess): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FinancialAccount::class);

        return ApiDisplay::for(FinancialAccount::class, FinancialAccountResource::class)
            ->usingQuery($companyAccess->scope(FinancialAccount::query(), $request->user()))
            ->paginate($request);
    }

    public function options(Request $request, FinancialCompanyAccess $companyAccess): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FinancialAccount::class);

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:32'],
            'active' => ['nullable', 'in:true,false,1,0'],
        ]);

        $query = $companyAccess->scope(FinancialAccount::query(), $request->user())
            ->when(
                filled($validated['company_id'] ?? null),
                fn (Builder $query) => $query->forCompany((int) $validated['company_id'])
            )
            ->when(
                filled($validated['type'] ?? null),
                fn (Builder $query) => $query->where('type', $validated['type'])
            )
            ->when(
                $request->boolean('active'),
                fn (Builder $query) => $query->active()
            )
            ->orderBy('name');

        return FinancialAccountResource::collection($query->get());
    }

    public function store(StoreFinancialAccountRequest $request): FinancialAccountResource
    {
        $financialAccount = FinancialAccount::query()->create($request->validated());

        return new FinancialAccountResource($financialAccount);
    }

    public function show(FinancialAccount $financialAccount): FinancialAccountResource
    {
        Gate::authorize('view', $financialAccount);

        return new FinancialAccountResource($financialAccount);
    }

    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $financialAccount): FinancialAccountResource
    {
        $financialAccount->update($request->validated());

        return new FinancialAccountResource($financialAccount);
    }

    public function destroy(FinancialAccount $financialAccount): JsonResponse
    {
        Gate::authorize('delete', $financialAccount);

        if ($financialAccount->movements()->exists()
            || $financialAccount->settlements()->exists()
            || $financialAccount->bankStatementImports()->exists()
            || $financialAccount->bankStatementLines()->exists()) {
            return response()->json([
                'message' => 'Conta financeira com movimentos, baixas ou extratos nao pode ser excluida.',
                'errors' => [
                    'financial_account' => ['Conta financeira com movimentos, baixas ou extratos nao pode ser excluida.'],
                ],
            ], 422);
        }

        $financialAccount->delete();

        return response()->json(null, 204);
    }
}
