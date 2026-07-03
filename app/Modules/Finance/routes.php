<?php

declare(strict_types=1);

use App\Modules\Finance\Http\Controllers\Api\FinancialCategoryApiController;
use App\Modules\Finance\Http\Controllers\Api\PaymentMethodApiController;
use App\Modules\Finance\Http\Controllers\FinancialCategoryController;
use App\Modules\Finance\Http\Controllers\PaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
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

        Route::apiResource('financial-categories', FinancialCategoryApiController::class)
            ->parameters(['financial-categories' => 'financial_category']);

        Route::get('payment-method-options', [PaymentMethodApiController::class, 'options'])
            ->name('payment-methods.options');

        Route::apiResource('payment-methods', PaymentMethodApiController::class)
            ->parameters(['payment-methods' => 'payment_method']);
    });
