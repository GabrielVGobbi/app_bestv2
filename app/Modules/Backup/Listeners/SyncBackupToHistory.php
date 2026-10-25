<?php

declare(strict_types=1);

namespace App\Modules\Backup\Listeners;

use App\Modules\Backup\Models\BackupHistory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupWasSuccessful;

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

    public function handleCleanup(CleanupWasSuccessful $event): void
    {
        $prefix = rtrim($event->backupName, '/').'/';
        if ($prefix === '/') {
            return;
        }
        $disk = Storage::disk($event->diskName);
        BackupHistory::query()->where('disk', $event->diskName)
            ->where('status', BackupHistory::STATUS_COMPLETED)->whereNotNull('path')
            ->chunkById(100, function (Collection $histories) use ($event, $prefix, $disk): void {
                foreach ($histories as $history) {
                    $path = $history->path;
                    if (! is_string($path) || ! str_starts_with($path, $prefix) || $disk->getDriver()->fileExists($path)) {
                        continue;
                    }
                    BackupHistory::query()->whereKey($history->getKey())->where('disk', $event->diskName)
                        ->where('status', BackupHistory::STATUS_COMPLETED)->where('path', $path)->update(['path' => null]);
                }
            });
    }
}
