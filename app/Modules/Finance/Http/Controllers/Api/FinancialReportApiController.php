<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Reports\BuildFinancialDashboardReport;
use App\Modules\Finance\Actions\Reports\BuildInstallmentsScheduleReport;
use App\Modules\Finance\Actions\Reports\BuildOpenReceivablesReport;
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

    public function receivablesOpen(Request $request, BuildOpenReceivablesReport $report): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return response()->json([
            'data' => $report->handle([
                'company_id' => $request->integer('company_id') ?: null,
                'category_id' => $request->integer('category_id') ?: null,
            ]),
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
