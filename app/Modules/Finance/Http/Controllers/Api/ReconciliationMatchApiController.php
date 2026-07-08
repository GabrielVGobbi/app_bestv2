<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Reconciliation\ConfirmReconciliationMatch;
use App\Modules\Finance\Actions\Reconciliation\RejectReconciliationMatch;
use App\Modules\Finance\Actions\Reconciliation\ReverseReconciliationMatch;
use App\Modules\Finance\Http\Requests\Reconciliation\ReverseReconciliationMatchRequest;
use App\Modules\Finance\Http\Resources\ReconciliationMatchResource;
use App\Modules\Finance\Models\ReconciliationMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReconciliationMatchApiController extends Controller
{
    public function confirm(
        Request $request,
        ReconciliationMatch $match,
        ConfirmReconciliationMatch $confirmMatch,
    ): ReconciliationMatchResource {
        Gate::authorize('finance.reconciliation.manage');

        return new ReconciliationMatchResource($confirmMatch->handle($match));
    }

    public function reject(
        Request $request,
        ReconciliationMatch $match,
        RejectReconciliationMatch $rejectMatch,
    ): ReconciliationMatchResource {
        Gate::authorize('finance.reconciliation.manage');

        return new ReconciliationMatchResource($rejectMatch->handle($match));
    }

    public function reverse(
        ReverseReconciliationMatchRequest $request,
        ReconciliationMatch $match,
        ReverseReconciliationMatch $reverseMatch,
    ): ReconciliationMatchResource {
        Gate::authorize('finance.reconciliation.manage');

        return new ReconciliationMatchResource($reverseMatch->handle($match, $request->validated()));
    }
}
