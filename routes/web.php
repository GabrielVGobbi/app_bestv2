<?php

use App\Http\Controllers\AppController;
use App\Modules\Clients\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AppController::class, 'dashboard'])->name('app.dashboard');

    /*
    |--------------------------------------------------------------------------
    | Clientes
    |--------------------------------------------------------------------------
    */
    Route::get('clients/split', [ClientController::class, 'split'])->name('clients.split');
    Route::resource('clients', ClientController::class)->parameters(['clients' => 'client']);
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
