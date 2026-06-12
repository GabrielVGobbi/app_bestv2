<?php

use App\Modules\Companies\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::resource('companies', CompanyController::class)->parameters(['companies' => 'company']);
});
