<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Remove imagens órfãs da pasta uploads/rich-text/temp/.
 *
 * Imagens chegam aqui quando o usuário faz upload mas não salva o formulário.
 * Arquivos com mais de --hours horas são considerados órfãos e removidos.
 *
 * Uso manual:
 *   php artisan rich-text:prune-uploads
 *   php artisan rich-text:prune-uploads --hours=2
 *   php artisan rich-text:prune-uploads --dry-run
 */
class PruneRichTextTempUploads extends Command
{
    protected $signature = 'rich-text:prune-uploads
                            {--hours=24 : Remove arquivos de temp mais antigos que N horas}
                            {--dry-run  : Lista o que seria removido sem deletar}';

    protected $description = 'Remove imagens órfãs de uploads/rich-text/temp/';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subHours($hours)->timestamp;

        $files = $disk->files('uploads/rich-text/temp');

        if (empty($files)) {
            $this->info('Nenhum arquivo em uploads/rich-text/temp/.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $skipped = 0;

        foreach ($files as $path) {
            $lastModified = $disk->lastModified($path);

            if ($lastModified >= $cutoff) {
                $skipped++;

                continue; // arquivo recente — usuário pode ainda estar editando
            }

            if ($dryRun) {
                $age = Carbon::createFromTimestamp($lastModified)->diffForHumans();
                $this->line("  [dry-run] removeria: {$path} ({$age})");
                $deleted++;

                continue;
            }

            $disk->delete($path);
            $deleted++;
        }

        $label = $dryRun ? '[dry-run] removeria' : 'Removidos';
        $this->info("{$label}: {$deleted} arquivo(s). Ignorados (recentes): {$skipped}.");

        return self::SUCCESS;
    }
}
