<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Http\Requests\FinancialEntry\StoreFinancialEntryRequest;
use App\Modules\Finance\Http\Resources\FinancialEntryResource;
use App\Modules\Finance\Models\FinancialEntry;
use App\Supports\Api\ApiDisplay;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FinancialEntryApiController extends Controller
{
    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FinancialEntry::class);

        return ApiDisplay::for(FinancialEntry::class, FinancialEntryResource::class)
            ->usingQuery(FinancialEntry::query()->with(['category', 'counterparty']))
            ->paginate($request);
    }

    public function store(StoreFinancialEntryRequest $request, CreateManualFinancialEntry $createManualFinancialEntry): FinancialEntryResource
    {
        $entry = $createManualFinancialEntry->handle($request->validated());

        return new FinancialEntryResource($entry->load(['category', 'counterparty', 'amountComponents', 'installments']));
    }

    public function show(FinancialEntry $financialEntry): FinancialEntryResource
    {
        Gate::authorize('view', $financialEntry);

        return new FinancialEntryResource($financialEntry->load(['category', 'counterparty', 'amountComponents', 'installments']));
    }
}
