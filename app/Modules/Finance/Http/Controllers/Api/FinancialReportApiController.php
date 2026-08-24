<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Reports\BuildFinancialDashboardReport;
use App\Modules\Finance\Actions\Reports\BuildInstallmentsScheduleReport;
use App\Modules\Finance\Actions\Reports\BuildOpenInstallmentsReport;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FinancialReportApiController extends Controller
{
    public function dashboard(Request $request, BuildFinancialDashboardReport $dashboard): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return response()->json([
            'data' => $dashboard->handle($request->integer('company_id') ?: null),
        ]);
    }

    /**
     * Detalhe de parcelas em aberto, servindo tanto contas a receber quanto a pagar.
     *
     * Sem `direction` devolve as duas, o que da a visao consolidada de tudo que
     * ainda esta em aberto.
     */
    public function openInstallments(Request $request, BuildOpenInstallmentsReport $report): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'direction' => ['nullable', Rule::in(array_column(FinancialDirection::cases(), 'value'))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json([
            'data' => $report->handle($validated),
        ]);
    }

    public function installmentsSchedule(Request $request, BuildInstallmentsScheduleReport $report): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'direction' => ['nullable', Rule::in(array_column(FinancialDirection::cases(), 'value'))],
            'status' => ['nullable', Rule::in(array_column(FinancialEntryStatus::cases(), 'value'))],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $report->handle($validated),
        ]);
    }
}
