<?php

declare(strict_types=1);

use App\Modules\Addresses\Http\Controllers\AddressController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AddressController::class, 'index'])->name('index');
Route::post('/', [AddressController::class, 'store'])->name('store');
Route::put('/{address:uuid}', [AddressController::class, 'update'])->name('update');
Route::delete('/{address:uuid}', [AddressController::class, 'destroy'])->name('destroy');
Route::patch('/{address:uuid}/default', [AddressController::class, 'setDefault'])->name('default');
