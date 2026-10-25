<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RichText;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;
use Throwable;

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
        $value = $this->option('hours');
        $hours = is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($hours === false) {
            $this->error('--hours deve ser um inteiro maior ou igual a 1.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        try {
            $cutoff = Carbon::now()->subHours($hours)->timestamp;
            $disk = Storage::disk('public');
            if (! $disk->getAdapter() instanceof LocalFilesystemAdapter
                || ($disk->getConfig()['driver'] ?? 'local') !== 'local') {
                throw new RuntimeException('Rich-text cleanup requires local storage.');
            }

            $directory = $this->directory($disk);
            $files = is_dir($directory) ? $disk->files('uploads/rich-text/temp') : [];
        } catch (Throwable $exception) {
            $this->reportFailure(null, $exception);

            return self::FAILURE;
        }

        if (empty($files)) {
            $this->info('Nenhum arquivo em uploads/rich-text/temp/.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $skipped = 0;
        $failures = 0;

        foreach ($files as $path) {
            try {
                $before = $this->snapshot($disk, $path);
                if ($before === null) {
                    $skipped++;

                    continue;
                }

                $lastModified = $disk->lastModified($path);
                if ($this->snapshot($disk, $path) !== $before || $lastModified >= $cutoff
                    || $before['mtime'] >= $cutoff || $this->referenced($path)) {
                    $skipped++;

                    continue;
                }

                if ($this->snapshot($disk, $path) !== $before) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("  [dry-run] removeria: {$path}");
                    $deleted++;

                    continue;
                }

                if (! $disk->delete($path)) {
                    throw new RuntimeException('Rich-text artifact deletion was refused.');
                }
                $absolute = $directory.'/'.basename($path);
                clearstatcache(true, $absolute);
                if (file_exists($absolute) || is_link($absolute)) {
                    throw new RuntimeException('Rich-text artifact deletion left a residue.');
                }
                $deleted++;
            } catch (Throwable $exception) {
                $failures++;
                $this->reportFailure($path, $exception);
            }
        }

        $label = $dryRun ? '[dry-run] removeria' : 'Removidos';
        $this->info("{$label}: {$deleted} arquivo(s). Ignorados (recentes, referenciados ou alterados): {$skipped}.");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Conserva qualquer menção ao nome, inclusive JSON com barras escapadas.
     * Falsos positivos retêm arquivos; rascunhos não salvos continuam limitados por --hours.
     */
    private function referenced(string $path): bool
    {
        $pattern = '%'.basename($path).'%';

        return RichText::query()->where('body', 'like', $pattern)->orWhere('body_html', 'like', $pattern)->exists();
    }

    private function directory(FilesystemAdapter $disk): string
    {
        $root = rtrim(str_replace('\\', '/', $disk->path('')), '/');
        if ($root === '' || str_contains($root, "\0") || preg_match('~(?:^|/)\.\.?(/|$)~', $root) === 1
            || (! str_starts_with($root, '/') && preg_match('~^[a-z]:/~i', $root) !== 1)) {
            throw new RuntimeException('Rich-text storage root is unsafe.');
        }

        $ancestor = $root;
        while (! file_exists($ancestor) && ! is_link($ancestor)) {
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                throw new RuntimeException('Rich-text storage root is unavailable.');
            }
            $ancestor = $parent;
        }
        $this->assertCanonical($ancestor, true);
        $resolved = realpath($ancestor);
        if ($resolved === false) {
            throw new RuntimeException('Rich-text storage root became unavailable.');
        }
        $root = rtrim(str_replace('\\', '/', $resolved), '/').substr($root, strlen($ancestor));

        $directory = $root;
        foreach (['uploads', 'rich-text', 'temp'] as $part) {
            $directory .= '/'.$part;
            if (file_exists($directory) || is_link($directory)) {
                $this->assertCanonical($directory, true);
            }
        }

        return $directory;
    }

    /** @return array{dev: int, ino: int, mode: int, nlink: int, size: int, mtime: int, ctime: int}|null */
    private function snapshot(FilesystemAdapter $disk, string $path): ?array
    {
        $prefix = 'uploads/rich-text/temp/';
        if (! str_starts_with($path, $prefix) || preg_match('~\A[^/\\\\\x00]+\z~', substr($path, strlen($prefix))) !== 1
            || in_array(basename($path), ['.', '..'], true)) {
            throw new RuntimeException('Rich-text artifact is outside the top-level temporary namespace.');
        }

        $absolute = $this->directory($disk).'/'.basename($path);
        clearstatcache(true, $absolute);
        if (! file_exists($absolute) && ! is_link($absolute)) {
            return null;
        }
        $this->assertCanonical($absolute, false);
        $stat = lstat($absolute);
        if ($stat === false) {
            throw new RuntimeException('Rich-text artifact metadata could not be read.');
        }

        return [
            'dev' => $stat['dev'], 'ino' => $stat['ino'], 'mode' => $stat['mode'], 'nlink' => $stat['nlink'],
            'size' => $stat['size'], 'mtime' => $stat['mtime'], 'ctime' => $stat['ctime'],
        ];
    }

    private function assertCanonical(string $path, bool $directory): void
    {
        clearstatcache(true, $path);
        $stat = lstat($path);
        $resolved = realpath($path);
        $parent = realpath(dirname($path));
        $expected = $parent === false ? null : rtrim($parent, '/\\').'/'.basename($path);
        if ($stat === false || $resolved === false || $expected === null || is_link($path)
            || $this->normalized($resolved) !== $this->normalized($expected)
            || ($stat['mode'] & 0170000) !== ($directory ? 0040000 : 0100000)
            || (! $directory && $stat['nlink'] !== 1)) {
            throw new RuntimeException('Rich-text namespace contains a redirected or linked entry.');
        }
    }

    private function normalized(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }

    private function reportFailure(?string $path, Throwable $exception): void
    {
        Log::warning('Rich-text temporary cleanup failed.', ['file_path' => $path, 'exception' => $exception]);
        $this->error('A limpeza deixou arquivo(s) que exigem atenção.');
    }
}
