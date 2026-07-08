<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Reconciliation\ImportBankStatement;
use App\Modules\Finance\Http\Requests\BankStatementImport\StoreBankStatementImportRequest;
use App\Modules\Finance\Http\Resources\BankStatementImportResource;
use App\Modules\Finance\Models\BankStatementImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReconciliationImportController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('finance.reconciliation.manage');

        return Inertia::render('app/financial-reconciliation/imports/index', [
            'imports' => BankStatementImportResource::collection(
                BankStatementImport::query()
                    ->with(['financialAccount', 'lines.financialAccount', 'lines.matches'])
                    ->latest()
                    ->paginate(20)
            ),
        ]);
    }

    public function store(StoreBankStatementImportRequest $request, ImportBankStatement $importBankStatement): RedirectResponse
    {
        $importBankStatement->handle($request->file('file'), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Extrato importado para conciliacao.')]);

        return to_route('financial-reconciliation.imports.index');
    }
}
