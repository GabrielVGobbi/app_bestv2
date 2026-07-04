<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Actions\Settlements\ReverseFinancialSettlement;
use App\Modules\Finance\Http\Requests\FinancialSettlement\ReverseFinancialSettlementRequest;
use App\Modules\Finance\Http\Requests\FinancialSettlement\StoreFinancialSettlementRequest;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FinancialSettlementController extends Controller
{
    public function store(
        FinancialInstallment $financialInstallment,
        StoreFinancialSettlementRequest $request,
        CreateFinancialSettlement $createFinancialSettlement,
    ): RedirectResponse {
        $settlement = $createFinancialSettlement->handle($financialInstallment, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Baixa financeira registrada.')]);

        return to_route('financial-entries.show', $settlement->financialEntry);
    }

    public function reverse(
        FinancialSettlement $financialSettlement,
        ReverseFinancialSettlementRequest $request,
        ReverseFinancialSettlement $reverseFinancialSettlement,
    ): RedirectResponse {
        $settlement = $reverseFinancialSettlement->handle($financialSettlement, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Baixa financeira estornada.')]);

        return to_route('financial-entries.show', $settlement->financialEntry);
    }
}
