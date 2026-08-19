<?php

use App\Http\Controllers\AppController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AppController::class, 'dashboard'])->name('app.dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
