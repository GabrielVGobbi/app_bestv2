<?php

use App\Modules\CrudGenerator\Http\Controllers\CrudGeneratorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:useDeveloperTools'])
    ->prefix('admin/dev/crud-generator')
    ->name('admin.dev.crud-generator.')
    ->group(function (): void {
        Route::get('/', [CrudGeneratorController::class, 'index'])->name('index');
        Route::post('/preview', [CrudGeneratorController::class, 'preview'])->name('preview');
        Route::post('/apply', [CrudGeneratorController::class, 'store'])->name('apply');
    });
