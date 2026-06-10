<?php

declare(strict_types=1);

use App\Modules\Backup\Http\Controllers\Admin\BackupController;
use App\Modules\Backup\Http\Controllers\Api\DatabaseExportApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Backup — Web Routes (admin + downloads diretos do browser)
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth', 'verified'])->name('admin.dev.backups.')->group(function (): void {

    Route::prefix('admin/dev/backups')->group(function (): void {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::get('/{backupHistory}/download', [BackupController::class, 'downloadBackup'])->name('download');
    });

    // Download direto — browser navega aqui, não usa fetch/axios
    Route::get('api/v1/database-exports/{databaseExport}/download', [DatabaseExportApiController::class, 'download'])
        ->name('database-exports.download');
});

/*
|--------------------------------------------------------------------------
| Backup — API Routes (JSON, chamadas via fetch/axios com Sanctum)
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')->as('backup.')->middleware(['api', 'auth:sanctum'])->group(function (): void {
    Route::prefix('database-exports')->as('database-exports.')->group(function (): void {
        Route::get('/', [DatabaseExportApiController::class, 'index'])->name('index');
        Route::post('/', [DatabaseExportApiController::class, 'store'])->name('store');
        Route::get('/tables', [DatabaseExportApiController::class, 'tables'])->name('tables');
        Route::get('/{databaseExport}', [DatabaseExportApiController::class, 'show'])->name('show');
    });
});
