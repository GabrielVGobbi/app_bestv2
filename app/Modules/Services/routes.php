<?php

use App\Modules\Services\Http\Controllers\Api\ServiceApiController;
use App\Modules\Services\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::patch('services/{service}/restore', [ServiceController::class, 'restore'])->name('services.restore');
    Route::patch('services/{service}/quick-update', [ServiceController::class, 'quickUpdate'])->name('services.quick-update');
    Route::resource('services', ServiceController::class)->parameters(['services' => 'service']);
});

Route::prefix('api/v1')
    ->as('api.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::patch('services/{service}/restore', [ServiceApiController::class, 'restore'])->name('services.restore');
        Route::apiResource('services', ServiceApiController::class)
            ->parameters(['services' => 'service']);
    });
