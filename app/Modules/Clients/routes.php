<?php

use App\Modules\Clients\Http\Controllers\Api\ClientApiController;
use App\Modules\Clients\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
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
