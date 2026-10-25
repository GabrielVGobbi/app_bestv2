<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AppController::class, 'dashboard'])->name('app.dashboard');

    Route::get('exportacoes', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
