<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Jobs;

use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class GenerateZipJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ZipRequest $zipRequest) {}

    public function handle(): void
    {
        $this->zipRequest->update(['status' => 'processing']);

        // Alinha com config('filesystems.disks.local.root') => storage/app/private
        $directory = storage_path('app/private/zip-requests');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $zipPath = "{$directory}/{$this->zipRequest->uuid}.zip";
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->zipRequest->update(['status' => 'failed', 'error_message' => 'Falha ao criar ZIP.']);

            return;
        }

        try {
            $uploads = Uploaded::query()->whereIn('id', (array) $this->zipRequest->file_ids)->get();
            $usedNames = [];
            $temporaryPaths = [];
            $addedFiles = 0;

            foreach ($uploads as $upload) {
                $disk = Storage::disk($upload->disk);
                if (! $disk->exists($upload->path)) {
                    continue;
                }

                $zipName = $this->uniqueZipName($upload->original_name ?: $upload->file_name, $usedNames);
                $localPath = $this->localPathFor($upload);

                if ($localPath !== null) {
                    if ($zip->addFile($localPath, $zipName)) {
                        $addedFiles++;
                    }

                    continue;
                }

                $temporaryPath = $this->copyToTemporaryFile($upload);
                if ($temporaryPath === null) {
                    continue;
                }

                $temporaryPaths[] = $temporaryPath;
                if ($zip->addFile($temporaryPath, $zipName)) {
                    $addedFiles++;
                }
            }

            if ($addedFiles === 0) {
                $zip->close();
                $this->deleteTemporaryFiles($temporaryPaths);
                $this->zipRequest->update([
                    'status' => 'failed',
                    'error_message' => 'Nenhum arquivo disponível para compactação.',
                ]);

                return;
            }

            $zip->close();
            $this->deleteTemporaryFiles($temporaryPaths);

            $this->zipRequest->update([
                'status' => 'ready',
                'zip_path' => "zip-requests/{$this->zipRequest->uuid}.zip",
                'expires_at' => now()->addHours(2),
            ]);
        } catch (Throwable $e) {
            $zip->close();
            if (isset($temporaryPaths)) {
                $this->deleteTemporaryFiles($temporaryPaths);
            }
            $this->zipRequest->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, true>  $usedNames
     */
    private function uniqueZipName(string $name, array &$usedNames): string
    {
        $name = trim(str_replace(['\\', '/'], '-', $name)) ?: 'arquivo';

        if (! isset($usedNames[$name])) {
            $usedNames[$name] = true;

            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $baseName = $extension !== ''
            ? mb_substr($name, 0, -1 * (mb_strlen($extension) + 1))
            : $name;

        for ($i = 2; ; $i++) {
            $candidate = $extension !== ''
                ? "{$baseName} ({$i}).{$extension}"
                : "{$baseName} ({$i})";

            if (! isset($usedNames[$candidate])) {
                $usedNames[$candidate] = true;

                return $candidate;
            }
        }
    }

    private function localPathFor(Uploaded $upload): ?string
    {
        try {
            $path = Storage::disk($upload->disk)->path($upload->path);
        } catch (Throwable) {
            return null;
        }

        return is_file($path) ? $path : null;
    }

    private function copyToTemporaryFile(Uploaded $upload): ?string
    {
        $disk = Storage::disk($upload->disk);
        $stream = $disk->readStream($upload->path);

        if ($stream === false) {
            return null;
        }

        $directory = storage_path('app/private/zip-requests/tmp');
        File::ensureDirectoryExists($directory);

        $temporaryPath = tempnam($directory, 'zip-file-');
        if ($temporaryPath === false) {
            fclose($stream);

            return null;
        }

        $target = fopen($temporaryPath, 'wb');
        if ($target === false) {
            fclose($stream);
            @unlink($temporaryPath);

            return null;
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $temporaryPath;
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteTemporaryFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
