<?php

use App\Modules\Clients\Http\Controllers\Api\ClientApiController;
use App\Modules\Clients\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('clients/split', [ClientController::class, 'split'])->name('clients.split');
    Route::patch('clients/{client}/quick-update', [ClientController::class, 'quickUpdate'])->name('clients.quick-update');
    Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status');
    Route::resource('clients', ClientController::class)
        ->parameters(['clients' => 'client']);
});

Route::prefix('api/v1')
    ->as('api.')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::apiResource('clients', ClientApiController::class)
            ->parameters(['clients' => 'client']);
    });
