<?php

use App\Modules\Sales\Http\Controllers\Api\SaleApiController;
use App\Modules\Sales\Http\Controllers\SaleController;
use App\Modules\Sales\Http\Controllers\SaleItemController;
use App\Modules\Sales\Http\Controllers\SaleItemSpotifyPresenceController;
use App\Modules\Sales\Http\Controllers\SaleLifecycleController;
use App\Modules\Sales\Http\Controllers\SpotifyTrackLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::patch('sales/{sale}/quote', [SaleLifecycleController::class, 'quote'])
        ->defaults('ability', 'quote')
        ->name('sales.quote');

    Route::patch('sales/{sale}/revert-to-draft', [SaleLifecycleController::class, 'revertToDraft'])
        ->defaults('ability', 'revert-to-draft')
        ->name('sales.revert-to-draft');

    Route::patch('sales/{sale}/confirm', [SaleLifecycleController::class, 'confirm'])
        ->defaults('ability', 'confirm')
        ->name('sales.confirm');

    Route::patch('sales/{sale}/finish', [SaleLifecycleController::class, 'finish'])
        ->defaults('ability', 'finish')
        ->name('sales.finish');

    Route::patch('sales/{sale}/cancel', [SaleLifecycleController::class, 'cancel'])
        ->defaults('ability', 'cancel')
        ->name('sales.cancel');

    Route::post('sales/items/spotify-track/lookup', SpotifyTrackLookupController::class)
        ->name('sales.items.spotify-track.lookup');

    Route::post('sales/{sale}/items', [SaleItemController::class, 'store'])
        ->name('sales.items.store');

    Route::post('sales/{sale}/spotify-presence/sync', [SaleItemSpotifyPresenceController::class, 'syncSale'])
        ->name('sales.spotify-presence.sync');

    Route::post('sales/{sale}/items/{saleItem}/spotify-presence/sync', [SaleItemSpotifyPresenceController::class, 'syncItem'])
        ->name('sales.items.spotify-presence.sync');

    Route::patch('sales/{sale}/items/{saleItem}', [SaleItemController::class, 'update'])
        ->name('sales.items.update');

    Route::delete('sales/{sale}/items/{saleItem}', [SaleItemController::class, 'destroy'])
        ->name('sales.items.destroy');

    Route::resource('sales', SaleController::class)
        ->parameters(['sales' => 'sale']);
});

Route::prefix('api/v1')
    ->as('api.')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function (): void {
        Route::apiResource('sales', SaleApiController::class)
            ->only(['index', 'show', 'destroy'])
            ->parameters(['sales' => 'sale']);
    });
