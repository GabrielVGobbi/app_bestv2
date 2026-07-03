<?php

use App\Modules\Suppliers\Http\Controllers\Api\SupplierApiController;
use App\Modules\Suppliers\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::patch('suppliers/{supplier}/status', [SupplierController::class, 'updateStatus'])->name('suppliers.status');
    Route::resource('suppliers', SupplierController::class)
        ->parameters(['suppliers' => 'supplier']);
});

Route::prefix('api/v1')
    ->as('api.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::apiResource('suppliers', SupplierApiController::class)
            ->parameters(['suppliers' => 'supplier']);
    });
