<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Models\BackupHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('developer'), 403);

        $backups = BackupHistory::latest('backed_up_at')
            ->paginate(20);

        return Inertia::render('admin/backup/index', [
            'backups' => $backups,
        ]);
    }

    public function downloadBackup(BackupHistory $backupHistory, Request $request): StreamedResponse
    {
        abort_unless($request->user()?->hasRole('developer'), 403);
        abort_if($backupHistory->status !== BackupHistory::STATUS_COMPLETED, 404);
        abort_if(! $backupHistory->path, 404);

        $disk = $backupHistory->disk;

        abort_unless(Storage::disk($disk)->exists($backupHistory->path), 404);

        activity('backups')
            ->performedOn($backupHistory)
            ->causedBy($request->user())
            ->log('downloaded');

        return Storage::disk($disk)
            ->download($backupHistory->path, $backupHistory->file_name ?? basename($backupHistory->path));
    }
}
