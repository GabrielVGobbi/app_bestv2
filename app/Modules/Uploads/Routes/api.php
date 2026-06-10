<?php

declare(strict_types=1);

use App\Modules\Uploads\Http\Controllers\UploadController;
use App\Modules\Uploads\Http\Controllers\ZipRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [UploadController::class, 'index'])->name('index');
Route::post('/', [UploadController::class, 'store'])->name('store');
Route::get('/tags', [UploadController::class, 'tags'])->name('tags');
Route::delete('/bulk', [ZipRequestController::class, 'bulkDestroy'])->name('bulk-destroy');
Route::post('/zip-requests', [ZipRequestController::class, 'store'])->name('zip-requests.store');
Route::get('/zip-requests/{uuid}', [ZipRequestController::class, 'show'])->name('zip-requests.show');
Route::get('/zip-requests/{uuid}/download', [ZipRequestController::class, 'download'])->name('zip-requests.download');

Route::get('/{upload}', [UploadController::class, 'show'])->name('show');
Route::put('/{upload}', [UploadController::class, 'update'])->name('update');
Route::delete('/{upload}', [UploadController::class, 'destroy'])->name('destroy');
Route::post('/{upload}/set-cover', [UploadController::class, 'setCover'])->name('set-cover');
Route::post('/{upload}/toggle-favorite', [UploadController::class, 'toggleFavorite'])->name('toggle-favorite');
