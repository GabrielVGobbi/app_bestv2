<?php

declare(strict_types=1);

use App\Modules\Uploads\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/{upload}/download', [UploadController::class, 'download'])->name('download');
