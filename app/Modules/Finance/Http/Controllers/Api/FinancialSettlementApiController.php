<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Actions\Settlements\ReverseFinancialSettlement;
use App\Modules\Finance\Http\Requests\FinancialSettlement\ReverseFinancialSettlementRequest;
use App\Modules\Finance\Http\Requests\FinancialSettlement\StoreFinancialSettlementRequest;
use App\Modules\Finance\Http\Resources\FinancialSettlementResource;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;

class FinancialSettlementApiController extends Controller
{
    public function store(
        FinancialInstallment $financialInstallment,
        StoreFinancialSettlementRequest $request,
        CreateFinancialSettlement $createFinancialSettlement,
    ): FinancialSettlementResource {
        $settlement = $createFinancialSettlement->handle($financialInstallment, $request->validated(), $request->user());

        return new FinancialSettlementResource($settlement);
    }

    public function reverse(
        FinancialSettlement $financialSettlement,
        ReverseFinancialSettlementRequest $request,
        ReverseFinancialSettlement $reverseFinancialSettlement,
    ): FinancialSettlementResource {
        $settlement = $reverseFinancialSettlement->handle($financialSettlement, $request->validated(), $request->user());

        return new FinancialSettlementResource($settlement);
    }
}
