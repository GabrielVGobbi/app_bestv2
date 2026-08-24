<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Actions\Status\CancelSale;
use App\Modules\Sales\Actions\Status\ConfirmSale;
use App\Modules\Sales\Actions\Status\FinishSaleCampaign;
use App\Modules\Sales\Actions\Status\QuoteSale;
use App\Modules\Sales\Actions\Status\RevertSaleToDraft;
use App\Modules\Sales\Http\Requests\UpdateSaleStatusRequest;
use App\Modules\Sales\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SaleLifecycleController extends Controller
{
    public function quote(UpdateSaleStatusRequest $request, Sale $sale, QuoteSale $action): RedirectResponse
    {
        $action->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda enviada como orçamento.')]);

        return back();
    }

    public function revertToDraft(UpdateSaleStatusRequest $request, Sale $sale, RevertSaleToDraft $action): RedirectResponse
    {
        $action->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda retornou para rascunho.')]);

        return back();
    }

    public function confirm(UpdateSaleStatusRequest $request, Sale $sale, ConfirmSale $action): RedirectResponse
    {
        $action->handle($sale, confirmed: $request->statusConfirmed());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda confirmada.')]);

        return back();
    }

    public function finish(UpdateSaleStatusRequest $request, Sale $sale, FinishSaleCampaign $action): RedirectResponse
    {
        $action->handle($sale, confirmed: $request->statusConfirmed());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campanha finalizada.')]);

        return back();
    }

    public function cancel(UpdateSaleStatusRequest $request, Sale $sale, CancelSale $action): RedirectResponse
    {
        $action->handle($sale, $request->statusReason(), $request->statusConfirmed());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda cancelada.')]);

        return back();
    }
}
