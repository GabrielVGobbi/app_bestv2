<?php

use App\Modules\SeederGenerator\Http\Controllers\SeederGeneratorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'role:developer'])
    ->prefix('admin/dev/seeder-generator')
    ->name('admin.dev.seeder-generator.')
    ->group(function (): void {
        Route::get('/', [SeederGeneratorController::class, 'index'])->name('index');
        Route::get('/tables', [SeederGeneratorController::class, 'tables'])->name('tables');
        Route::post('/preview', [SeederGeneratorController::class, 'preview'])->name('preview');
        Route::post('/execute', [SeederGeneratorController::class, 'execute'])->name('execute');
        Route::post('/generate', [SeederGeneratorController::class, 'generate'])->name('generate');
        Route::post('/run', [SeederGeneratorController::class, 'run'])->name('run');
    });
