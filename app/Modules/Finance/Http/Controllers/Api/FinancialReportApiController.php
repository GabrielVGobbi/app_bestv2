<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Reports\BuildFinancialDashboardReport;
use App\Modules\Finance\Actions\Reports\BuildInstallmentsScheduleReport;
use App\Modules\Finance\Actions\Reports\BuildOpenInstallmentsReport;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FinancialReportApiController extends Controller
{
    public function dashboard(
        Request $request,
        BuildFinancialDashboardReport $dashboard,
        FinancialCompanyAccess $companyAccess,
    ): JsonResponse {
        Gate::authorize('finance.reports.view');

        $companyId = $request->integer('company_id') ?: null;

        if ($companyId !== null) {
            $companyAccess->authorize($request->user(), $companyId);
        }

        return response()->json([
            'data' => $dashboard->handle($companyId, user: $request->user()),
        ]);
    }

    /**
     * Detalhe de parcelas em aberto, servindo tanto contas a receber quanto a pagar.
     *
     * Sem `direction` devolve as duas, o que da a visao consolidada de tudo que
     * ainda esta em aberto.
     */
    public function openInstallments(
        Request $request,
        BuildOpenInstallmentsReport $report,
        FinancialCompanyAccess $companyAccess,
    ): JsonResponse {
        Gate::authorize('finance.reports.view');

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'direction' => ['nullable', Rule::in(array_column(FinancialDirection::cases(), 'value'))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        if (filled($validated['company_id'] ?? null)) {
            $companyAccess->authorize($request->user(), (int) $validated['company_id']);
        }

        return response()->json([
            'data' => $report->handle($validated, $request->user()),
        ]);
    }

    public function installmentsSchedule(
        Request $request,
        BuildInstallmentsScheduleReport $report,
        FinancialCompanyAccess $companyAccess,
    ): JsonResponse {
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

        if (filled($validated['company_id'] ?? null)) {
            $companyAccess->authorize($request->user(), (int) $validated['company_id']);
        }

        return response()->json([
            'data' => $report->handle($validated, $request->user()),
        ]);
    }
}
