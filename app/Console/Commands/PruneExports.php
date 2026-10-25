<?php

namespace App\Console\Commands;

use App\Models\Export;
use App\Supports\PrivateExportFiles;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PruneExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Clean retained export requests and old unreferenced private artifacts';

    public function handle(): int
    {
        $at = now()->toImmutable();
        $failedBefore = $at->subDays(max(1, (int) config('exports.failed_retention_days', 7)));
        $orphanBefore = $at->subHours(max(1, (int) config('exports.orphan_retention_hours', 24)));
        $files = new PrivateExportFiles('exports', ['csv', 'tsv', 'xlsx'], true);
        $failures = 0;

        Export::query()->whereIn('status', [Export::STATUS_COMPLETED, Export::STATUS_FAILED])
            ->chunkById(100, function (Collection $exports) use ($at, $failedBefore, $files, &$failures): void {
                foreach ($exports as $export) {
                    if (! $export->pruneRetained($at, $failedBefore, $files)) {
                        $failures++;
                    }
                }
            });

        try {
            $failures += $files->pruneOrphans(function (string $uuid, string $path): bool {
                $reference = str_ends_with($path, '.part') ? substr($path, 0, -5) : $path;

                $references = [$reference, $reference.'.part'];
                $query = Export::query()->whereRaw('LOWER(uuid) = ?', [strtolower($uuid)]);
                if (PHP_OS_FAMILY === 'Windows') {
                    $query->orWhereRaw('LOWER(file_path) IN (?, ?)', array_map('strtolower', $references));
                } else {
                    $query->orWhereIn('file_path', $references);
                }

                return $query->exists();
            }, $orphanBefore);
        } catch (Throwable $exception) {
            $failures++;
            Log::warning('Export orphan cleanup failed.', ['exception' => $exception]);
        }

        if ($failures > 0) {
            $this->error("Export cleanup left {$failures} item(s) requiring attention.");

            return self::FAILURE;
        }

        $this->info('Export retention cleanup completed.');

        return self::SUCCESS;
    }
}
