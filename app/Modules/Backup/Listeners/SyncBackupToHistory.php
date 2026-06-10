<?php

declare(strict_types=1);

namespace App\Modules\Backup\Listeners;

use App\Modules\Backup\Models\BackupHistory;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;

class SyncBackupToHistory
{
    public function handleSuccess(BackupWasSuccessful $event): void
    {
        $disk = $event->diskName;
        $backupName = $event->backupName;

        // Find the newest file in the backup directory on the disk
        $files = Storage::disk($disk)->files($backupName);
        $newestPath = collect($files)->sortByDesc(fn ($f) => Storage::disk($disk)->lastModified($f))->first();

        BackupHistory::create([
            'disk' => $disk,
            'path' => $newestPath,
            'file_name' => $newestPath ? basename($newestPath) : null,
            'file_size' => $newestPath ? Storage::disk($disk)->size($newestPath) : null,
            'status' => BackupHistory::STATUS_COMPLETED,
            'backed_up_at' => now(),
        ]);
    }

    public function handleFailure(BackupHasFailed $event): void
    {
        BackupHistory::create([
            'disk' => $event->diskName,
            'path' => null,
            'file_name' => null,
            'file_size' => null,
            'status' => BackupHistory::STATUS_FAILED,
            'error_message' => $event->exception->getMessage(),
            'backed_up_at' => now(),
        ]);
    }
}
