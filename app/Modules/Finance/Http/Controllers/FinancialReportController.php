<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Reports\BuildFinancialDashboardReport;
use App\Modules\Finance\Actions\Reports\BuildInstallmentsScheduleReport;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function index(BuildFinancialDashboardReport $dashboard, BuildInstallmentsScheduleReport $installmentsSchedule): Response
    {
        Gate::authorize('finance.reports.view');

        return Inertia::render('app/financial-reports/index', [
            'dashboard' => $dashboard->handle(),
            'installmentsSchedule' => $installmentsSchedule->handle(['limit' => 10]),
        ]);
    }
}
