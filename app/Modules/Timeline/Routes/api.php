<?php

declare(strict_types=1);

use App\Modules\Timeline\Http\Controllers\TimelineCommentController;
use App\Modules\Timeline\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::post('/{type}/{id}/comments', [TimelineCommentController::class, 'store'])->name('comments.store');
Route::put('/comments/{comment}', [TimelineCommentController::class, 'update'])->name('comments.update');
Route::delete('/comments/{comment}', [TimelineCommentController::class, 'destroy'])->name('comments.destroy');
Route::post('/comments/{comment}/replies', [TimelineCommentController::class, 'reply'])->name('comments.reply');
Route::get('/{type}/{id}', [TimelineController::class, 'show'])->name('show');
