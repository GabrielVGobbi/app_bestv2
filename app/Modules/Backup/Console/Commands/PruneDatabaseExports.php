<?php

declare(strict_types=1);

namespace App\Modules\Backup\Console\Commands;

use App\Modules\Backup\Models\DatabaseExport;
use App\Supports\PrivateExportFiles;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PruneDatabaseExports extends Command
{
    protected $signature = 'database-exports:prune';

    protected $description = 'Reconcile abandoned database exports and clean expired private artifacts';

    public function handle(): int
    {
        $failures = 0;
        $now = now()->toImmutable();
        $failedBefore = $now->subDays(max(1, (int) config('database-exports.failed_retention_days', 7)));
        $files = new PrivateExportFiles('database-exports', ['sql']);

        DatabaseExport::query()->chunkById(100, function (Collection $exports) use (&$failures, $now, $failedBefore, $files): void {
            foreach ($exports as $export) {
                try {
                    if (! $export->pruneRetained($now, $failedBefore, $files)) {
                        $failures++;
                    }
                } catch (Throwable $exception) {
                    Log::warning('Database export reconciliation failed.', ['database_export_id' => $export->getKey(), 'exception' => $exception]);
                    $failures++;
                }
            }
        });

        $failures += $files->pruneOrphans(function (string $uuid, string $path): bool {
            $base = str_ends_with($path, '.part') ? substr($path, 0, -5) : $path;

            $references = [$base, $base.'.part'];
            $query = DatabaseExport::query()->whereRaw('LOWER(uuid) = ?', [strtolower($uuid)]);
            if (PHP_OS_FAMILY === 'Windows') {
                $query->orWhereRaw('LOWER(file_path) IN (?, ?)', array_map('strtolower', $references));
            } else {
                $query->orWhereIn('file_path', $references);
            }

            return $query->exists();
        }, $now->subHours(max(1, (int) config('database-exports.orphan_retention_hours', 24))));

        if ($failures > 0) {
            $this->error("Database export cleanup left {$failures} request(s) requiring attention.");

            return self::FAILURE;
        }

        $this->info('Database export reconciliation and cleanup completed.');

        return self::SUCCESS;
    }
}
