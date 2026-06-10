<?php

declare(strict_types=1);

namespace App\Modules\Backup\Console\Commands;

use App\Modules\Backup\Models\DatabaseExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDatabaseExports extends Command
{
    protected $signature = 'database-exports:prune';

    protected $description = 'Delete expired database export files and their records';

    public function handle(): int
    {
        $disk = config('database-exports.disk', 'local');
        $count = 0;

        DatabaseExport::where('expires_at', '<', now())
            ->each(function (DatabaseExport $export) use ($disk, &$count): void {
                if ($export->file_path && Storage::disk($disk)->exists($export->file_path)) {
                    Storage::disk($disk)->delete($export->file_path);
                }

                $export->delete();
                $count++;
            });

        $this->info("Pruned {$count} expired database export(s).");

        return self::SUCCESS;
    }
}
