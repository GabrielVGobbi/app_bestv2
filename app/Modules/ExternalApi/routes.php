<?php

use App\Modules\ExternalApi\Http\Controllers\ExternalApiDiagnosticsController;
use App\Modules\ExternalApi\Modules\Spotify\Http\Controllers\SpotifyBrowserController;
use App\Modules\ExternalApi\Modules\Spotify\Http\Controllers\SpotifyOAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('external-api')
    ->name('external-api.')
    ->group(function () {
        Route::get('diagnostics', ExternalApiDiagnosticsController::class)->middleware('can:manageIntegrationSettings')->name('diagnostics');
    });

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('external-api/spotify')
    ->name('external-api.spotify.')
    ->group(function () {
        Route::get('artists', [SpotifyBrowserController::class, 'artists'])->name('artists');
        Route::get('playlists', [SpotifyBrowserController::class, 'playlists'])->name('playlists');
        Route::get('redirect', [SpotifyOAuthController::class, 'redirect'])->name('redirect');
        Route::get('callback', [SpotifyOAuthController::class, 'callback'])->name('callback');
        Route::delete('disconnect', [SpotifyOAuthController::class, 'disconnect'])->name('disconnect');
    });
