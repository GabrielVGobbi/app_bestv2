<?php

use App\Http\Controllers\Api\ActivityLogApiController;
use App\Http\Controllers\Api\Auth\AuthenticatedTokenController;
use App\Http\Controllers\Api\ExportApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\PermissionApiController;
use App\Http\Controllers\Api\RoleApiController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\AppController;
use App\Modules\Clients\Http\Controllers\Api\ClientApiController;
use App\Modules\Email\Http\Controllers\EmailWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', [AppController::class, 'ping']);

// Email delivery webhooks — public, no auth (signature verified per-provider)
Route::post('email/webhook/{provider}', [EmailWebhookController::class, 'handle'])->name('email.webhook')->withoutMiddleware(['auth', 'auth:sanctum']);

Route::prefix('v1')->as('api.')->group(function () {
    Route::post('auth/login', [AuthenticatedTokenController::class, 'store'])->middleware('throttle:5,1')->name('auth.login');

    Route::middleware('auth')->group(function () {
        Route::post('uploads/image', [UploadController::class, 'storeImage'])->name('uploads.image');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthenticatedTokenController::class, 'show'])->name('auth.me');
        Route::delete('auth/logout', [AuthenticatedTokenController::class, 'destroy'])->name('auth.logout');
        Route::delete('auth/logout-all', [AuthenticatedTokenController::class, 'destroyAll'])->name('auth.logout-all');

        /*
        |--------------------------------------------------------------------------
        | Resources
        |--------------------------------------------------------------------------
        */
        Route::apiResource('users', UserApiController::class)->only('index');
        Route::put('users/{user}/sync-roles-permissions', [UserApiController::class, 'syncRolesPermissions'])->name('users.sync-roles-permissions');

        Route::apiResource('clients', ClientApiController::class)->parameters(['clients' => 'client']);

        Route::apiResource('roles', RoleApiController::class)->except('show');
        Route::put('roles/{role}/sync-permissions', [RoleApiController::class, 'syncPermissions'])->name('roles.sync-permissions');

        Route::apiResource('permissions', PermissionApiController::class)->except('show');

        Route::get('activity-log', [ActivityLogApiController::class, 'index'])->name('activity-log.index');

        Route::prefix('exports')->as('exports.')->group(function () {
            Route::get('/', [ExportApiController::class, 'index'])->name('index');
            Route::post('/', [ExportApiController::class, 'store'])->name('store');
            Route::get('/columns', [ExportApiController::class, 'columns'])->name('columns');
            Route::get('/{export}', [ExportApiController::class, 'show'])->name('show');
        });

        Route::prefix('notifications')->as('notifications.')->group(function () {
            Route::get('/', [NotificationApiController::class, 'index'])->name('index');
            Route::get('/unread', [NotificationApiController::class, 'unread'])->name('unread');
            Route::get('/unread-count', [NotificationApiController::class, 'unreadCount'])->name('unread-count');
            Route::post('/mark-all-read', [NotificationApiController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::patch('/{notification}', [NotificationApiController::class, 'markAsRead'])->name('mark-read');
            Route::delete('/{notification}', [NotificationApiController::class, 'destroy'])->name('destroy');
            Route::post('/{notification}/execute-action', [NotificationApiController::class, 'executeAction'])->name('execute-action');
        });
    });
});
