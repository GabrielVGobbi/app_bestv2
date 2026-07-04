<?php

declare(strict_types=1);

use App\Modules\Finance\Http\Controllers\Api\FinancialCategoryApiController;
use App\Modules\Finance\Http\Controllers\Api\FinancialEntryApiController;
use App\Modules\Finance\Http\Controllers\Api\FinancialSettlementApiController;
use App\Modules\Finance\Http\Controllers\Api\PaymentMethodApiController;
use App\Modules\Finance\Http\Controllers\FinancialCategoryController;
use App\Modules\Finance\Http\Controllers\FinancialEntryController;
use App\Modules\Finance\Http\Controllers\FinancialSettlementController;
use App\Modules\Finance\Http\Controllers\PaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::resource('financial-entries', FinancialEntryController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->parameters(['financial-entries' => 'financial_entry']);

    Route::post('financial-installments/{financial_installment}/settlements', [FinancialSettlementController::class, 'store'])
        ->name('financial-installments.settlements.store');

    Route::post('financial-settlements/{financial_settlement}/reverse', [FinancialSettlementController::class, 'reverse'])
        ->name('financial-settlements.reverse');

    Route::patch('financial-categories/{financial_category}/status', [FinancialCategoryController::class, 'updateStatus'])
        ->name('financial-categories.status');

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

        Route::apiResource('financial-entries', FinancialEntryApiController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['financial-entries' => 'financial_entry']);

        Route::post('financial-installments/{financial_installment}/settlements', [FinancialSettlementApiController::class, 'store'])
            ->name('financial-installments.settlements.store');

        Route::post('financial-settlements/{financial_settlement}/reverse', [FinancialSettlementApiController::class, 'reverse'])
            ->name('financial-settlements.reverse');

        Route::apiResource('financial-categories', FinancialCategoryApiController::class)
            ->parameters(['financial-categories' => 'financial_category']);

        Route::get('payment-method-options', [PaymentMethodApiController::class, 'options'])
            ->name('payment-methods.options');

        Route::apiResource('payment-methods', PaymentMethodApiController::class)
            ->parameters(['payment-methods' => 'payment_method']);
    });
