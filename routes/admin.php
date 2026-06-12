<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\DevToolsController;
use App\Http\Controllers\Admin\SlackSettingsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Modules\Email\Http\Controllers\EmailLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin|super-admin'])->group(function () {

    Route::resource('users', UserController::class);
    Route::get('users/{user}/roles', [UserController::class, 'roles'])->name('users.roles');
    Route::get('users/{user}/permissions', [UserController::class, 'userPermissions'])->name('users.permissions');

    Route::resource('roles', RoleController::class)->except(['create', 'store']);
    Route::get('roles/{role}/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');

    Route::resource('permissions', PermissionController::class)->except(['create', 'store']);

    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    Route::get('exportacoes', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::prefix('email-logs')->name('email-logs.')->group(function () {
        Route::get('/', [EmailLogController::class, 'index'])->name('index');
        Route::post('/{emailLog}/resend', [EmailLogController::class, 'resend'])->name('resend');
        Route::patch('/settings', [EmailLogController::class, 'updateSettings'])->name('settings.update');
    });
});

Route::name('admin.slack.')->prefix('admin/slack')->middleware(['auth', 'verified', 'role:admin|super-admin'])->group(function () {
    Route::get('/', [SlackSettingsController::class, 'show'])->name('index');
    Route::put('/', [SlackSettingsController::class, 'update'])->name('update');
    Route::post('/test', [SlackSettingsController::class, 'test'])->name('test');
});

Route::name('admin.dev.')->prefix('admin/dev')->middleware(['auth', 'verified', 'role:developer'])->group(function () {
    Route::get('/', [DevToolsController::class, 'index'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Dev Tools — Ferramentas de manutenção e desenvolvimento
    |--------------------------------------------------------------------------
    */
    Route::prefix('dev-tools')->name('dev-tools.')->group(function () {
        Route::get('/', [DevToolsController::class, 'index'])->name('index');

        Route::post('/maintenance/toggle', [DevToolsController::class, 'toggleMaintenance'])->name('maintenance.toggle');
        Route::post('/cache/clear', [DevToolsController::class, 'clearAllCaches'])->name('cache.clear');
        Route::post('/cache/clear-specific', [DevToolsController::class, 'clearSpecificCache'])->name('cache.clear-specific');
        Route::post('/optimize', [DevToolsController::class, 'optimize'])->name('optimize');
        Route::post('/telescope/toggle', [DevToolsController::class, 'toggleTelescope'])->name('telescope.toggle');
        Route::post('/telescope/capture-all', [DevToolsController::class, 'toggleTelescopeCaptureAll'])->name('telescope.capture-all');
        Route::post('/env/update', [DevToolsController::class, 'updateEnv'])->name('env.update');
    });

    Route::prefix('components')->name('components.')->group(function () {
        Route::get('/', [DevToolsController::class, 'components'])->name('index');
        Route::get('/file-attachments', [DevToolsController::class, 'fileAttachments'])->name('file-attachments');
        Route::get('/design-system', [DevToolsController::class, 'designSystem'])->name('design-system');
        Route::get('/inline-cell-edit', [DevToolsController::class, 'inlineCellEdit'])->name('inline-cell-edit');
        Route::patch('/inline-cell-edit/users/{user}', [DevToolsController::class, 'updateInlineCellEditUser'])->name('inline-cell-edit.users.update');
        Route::get('/async-select', [DevToolsController::class, 'asyncSelect'])->name('async-select');
        Route::get('/async-select/users', [DevToolsController::class, 'asyncSelectUsers'])->name('async-select.users');
        Route::get('/form-playground', [DevToolsController::class, 'formPlayground'])->name('form-playground');
        Route::get('/toasts', [DevToolsController::class, 'toasts'])->name('toasts');
        Route::get('/notifications', [DevToolsController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/seed', [DevToolsController::class, 'seedNotification'])->name('notifications.seed');
        Route::get('/activity-actions', [DevToolsController::class, 'activityActions'])->name('activity-actions');
        Route::post('/activity-actions/seed', [DevToolsController::class, 'seedActivityAction'])->name('activity-actions.seed');
        Route::get('/activity-timeline', [DevToolsController::class, 'activityTimeline'])->name('activity-timeline');
        Route::get('/rich-text-editor', [DevToolsController::class, 'richTextEditor'])->name('rich-text-editor');
        Route::get('/email', [DevToolsController::class, 'email'])->name('email');
        Route::post('/email/preview', [DevToolsController::class, 'previewEmail'])->name('email.preview');
        Route::post('/email/send-test', [DevToolsController::class, 'sendTestEmail'])->name('email.send-test');
    });
});
