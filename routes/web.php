<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AppController::class, 'dashboard'])->name('app.dashboard');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
