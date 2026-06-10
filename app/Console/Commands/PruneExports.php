<?php

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired export files and their database records';

    public function handle(): int
    {
        $disk = config('exports.disk', 'local');

        $count = 0;

        Export::where('expires_at', '<', now())
            ->each(function (Export $export) use ($disk, &$count): void {
                if ($export->file_path && Storage::disk($disk)->exists($export->file_path)) {
                    Storage::disk($disk)->delete($export->file_path);
                }

                $export->delete();
                $count++;
            });

        $this->info("Pruned {$count} expired export(s).");

        return self::SUCCESS;
    }
}
