<?php

declare(strict_types=1);

use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Http\Controllers\Api\FinancialAccountApiController;
use App\Modules\Finance\Http\Controllers\Api\FinancialCategoryApiController;
use App\Modules\Finance\Http\Controllers\Api\FinancialCounterpartyApiController;
use App\Modules\Finance\Http\Controllers\Api\FinancialEntryApiController;
use App\Modules\Finance\Http\Controllers\Api\FinancialInstallmentApiController;
use App\Modules\Finance\Http\Controllers\Api\FinancialReportApiController;
use App\Modules\Finance\Http\Controllers\Api\FinancialSettlementApiController;
use App\Modules\Finance\Http\Controllers\Api\PaymentMethodApiController;
use App\Modules\Finance\Http\Controllers\Api\ReconciliationMatchApiController;
use App\Modules\Finance\Http\Controllers\FinancialAccountController;
use App\Modules\Finance\Http\Controllers\FinancialCategoryController;
use App\Modules\Finance\Http\Controllers\FinancialEntryController;
use App\Modules\Finance\Http\Controllers\FinancialInstallmentController;
use App\Modules\Finance\Http\Controllers\FinancialReconciliationImportController;
use App\Modules\Finance\Http\Controllers\FinancialReportController;
use App\Modules\Finance\Http\Controllers\FinancialSettlementController;
use App\Modules\Finance\Http\Controllers\PaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('contas-a-receber', [FinancialInstallmentController::class, 'index'])
        ->defaults('direction', FinancialDirection::Receivable->value)
        ->name('financial-installments.receivable.index');

    Route::get('contas-a-pagar', [FinancialInstallmentController::class, 'index'])
        ->defaults('direction', FinancialDirection::Payable->value)
        ->name('financial-installments.payable.index');

    Route::get('financial-reports', [FinancialReportController::class, 'index'])
        ->name('financial-reports.index');

    Route::get('financial-reconciliation/imports', [FinancialReconciliationImportController::class, 'index'])
        ->name('financial-reconciliation.imports.index');
    Route::post('financial-reconciliation/imports', [FinancialReconciliationImportController::class, 'store'])
        ->name('financial-reconciliation.imports.store');

    Route::patch('financial-entries/{financial_entry}/cancel', [FinancialEntryController::class, 'cancel'])
        ->name('financial-entries.cancel');

    Route::resource('financial-entries', FinancialEntryController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->parameters(['financial-entries' => 'financial_entry']);

    Route::post('financial-installments/{financial_installment}/settlements', [FinancialSettlementController::class, 'store'])
        ->name('financial-installments.settlements.store');

    Route::post('financial-settlements/{financial_settlement}/reverse', [FinancialSettlementController::class, 'reverse'])
        ->name('financial-settlements.reverse');

    Route::patch('financial-categories/{financial_category}/status', [FinancialCategoryController::class, 'updateStatus'])
        ->name('financial-categories.status');

    Route::patch('financial-accounts/{financial_account}/status', [FinancialAccountController::class, 'updateStatus'])
        ->name('financial-accounts.status');

    Route::resource('financial-accounts', FinancialAccountController::class)
        ->parameters(['financial-accounts' => 'financial_account']);

    Route::resource('financial-categories', FinancialCategoryController::class)
        ->parameters(['financial-categories' => 'financial_category']);

    Route::patch('payment-methods/{payment_method}/status', [PaymentMethodController::class, 'updateStatus'])
        ->name('payment-methods.status');

    Route::resource('payment-methods', PaymentMethodController::class)
        ->parameters(['payment-methods' => 'payment_method']);
});

Route::prefix('api/v1')
    ->as('api.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::get('financial-category-tree', [FinancialCategoryApiController::class, 'tree'])
            ->name('financial-categories.tree');

        Route::get('financial-counterparty-options', [FinancialCounterpartyApiController::class, 'options'])
            ->name('financial-counterparties.options');

        Route::get('financial-account-options', [FinancialAccountApiController::class, 'options'])
            ->name('financial-accounts.options');

        Route::get('financial-reports/dashboard', [FinancialReportApiController::class, 'dashboard'])
            ->name('financial-reports.dashboard');
        Route::get('financial-reports/open-installments', [FinancialReportApiController::class, 'openInstallments'])
            ->name('financial-reports.open-installments');
        Route::get('financial-reports/installments-schedule', [FinancialReportApiController::class, 'installmentsSchedule'])
            ->name('financial-reports.installments-schedule');

        Route::post('reconciliation-matches/{match}/confirm', [ReconciliationMatchApiController::class, 'confirm'])
            ->name('reconciliation-matches.confirm');
        Route::post('reconciliation-matches/{match}/reject', [ReconciliationMatchApiController::class, 'reject'])
            ->name('reconciliation-matches.reject');
        Route::post('reconciliation-matches/{match}/reverse', [ReconciliationMatchApiController::class, 'reverse'])
            ->name('reconciliation-matches.reverse');

        Route::apiResource('financial-entries', FinancialEntryApiController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['financial-entries' => 'financial_entry']);

        Route::get('financial-installments/{direction}', [FinancialInstallmentApiController::class, 'index'])
            ->whereIn('direction', ['all', FinancialDirection::Receivable->value, FinancialDirection::Payable->value])
            ->name('financial-installments.index');

        Route::post('financial-installments/{financial_installment}/settlements', [FinancialSettlementApiController::class, 'store'])
            ->name('financial-installments.settlements.store');

        Route::post('financial-settlements/{financial_settlement}/reverse', [FinancialSettlementApiController::class, 'reverse'])
            ->name('financial-settlements.reverse');

        Route::apiResource('financial-categories', FinancialCategoryApiController::class)
            ->parameters(['financial-categories' => 'financial_category']);

        Route::apiResource('financial-accounts', FinancialAccountApiController::class)
            ->parameters(['financial-accounts' => 'financial_account']);

        Route::get('payment-method-options', [PaymentMethodApiController::class, 'options'])
            ->name('payment-methods.options');

        Route::apiResource('payment-methods', PaymentMethodApiController::class)
            ->parameters(['payment-methods' => 'payment_method']);
    });
