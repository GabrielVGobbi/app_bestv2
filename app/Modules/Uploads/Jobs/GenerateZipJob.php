<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Jobs;

use App\Models\User;
use App\Modules\Timeline\Models\TimelineComment;
use App\Modules\Uploads\Models\Uploaded;
use App\Modules\Uploads\Models\ZipRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class GenerateZipJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    private readonly string $attemptToken;

    public function __construct(private readonly ZipRequest $zipRequest)
    {
        $this->attemptToken = (string) Str::uuid();
        $this->timeout = max(1, (int) config('uploads.zip.processing_timeout', 60));
    }

    public function handle(): void
    {
        $request = null;
        $path = null;
        $archive = null;
        $opened = false;
        $claimed = false;
        $published = false;

        try {
            $request = $this->zipRequest->fresh();
            if ($request === null) {
                return;
            }

            $path = $request->attemptPath($this->attemptToken);
            if (! $request->claimAttempt($path)) {
                return;
            }
            $claimed = true;

            $actor = User::query()->find($request->user_id);
            if ($actor === null || ! $actor->hasVerifiedEmail() || $actor->must_change_password) {
                throw new RuntimeException('ZIP requester is no longer eligible.');
            }

            $ids = array_values(array_unique(array_map('intval', (array) $request->file_ids)));
            $uploads = Uploaded::query()->with('parentable')->whereIn('id', $ids)->orderBy('id')->get()
                ->loadMorph('parentable', [TimelineComment::class => ['commentable']]);
            if ($ids === [] || $uploads->count() !== count($ids) || count($ids) !== (int) $request->file_count) {
                throw new RuntimeException('ZIP sources no longer match the requested selection.');
            }
            foreach ($uploads as $upload) {
                Gate::forUser($actor)->authorize('view', $upload);
            }

            $absolutePath = $request->resolveArtifactPath($path);
            if ($absolutePath === null) {
                throw new RuntimeException('ZIP destination is not a canonical private local path.');
            }
            $directory = dirname($path);
            File::ensureDirectoryExists(dirname($absolutePath));
            $part = $directory.'/archive.part';
            $archive = app(ZipArchive::class);
            if ($archive->open(dirname($absolutePath).'/archive.part', ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('ZIP archive could not be opened.');
            }
            $opened = true;
            $usedNames = [];
            $temporaryPaths = [];

            foreach ($uploads as $upload) {
                $this->assertActiveAttempt($request, $path);
                $temporary = $this->copyToTemporaryFile($upload, $directory);
                $temporaryPaths[] = $temporary;
                $name = $this->uniqueZipName($upload->original_name ?: $upload->file_name, $usedNames);
                if (! $archive->addFile(Storage::disk('local')->path($temporary), $name)) {
                    throw new RuntimeException('ZIP source could not be added.');
                }
            }

            $this->assertActiveAttempt($request, $path);
            if (! $archive->close()) {
                throw new RuntimeException('ZIP archive could not be finalized.');
            }
            $opened = false;
            $archive = null;
            foreach ($temporaryPaths as $temporary) {
                if (! Storage::disk('local')->delete($temporary)) {
                    throw new RuntimeException('ZIP staging file could not be removed.');
                }
            }
            $this->assertActiveAttempt($request, $path);
            if (! Storage::disk('local')->move($part, $path)) {
                throw new RuntimeException('ZIP archive could not be published.');
            }
            if (! $request->completeAttempt($path)) {
                throw new RuntimeException('ZIP attempt lost its processing state before publication.');
            }
            $published = true;
        } catch (Throwable $exception) {
            report($exception);
            if ($claimed && $request !== null && $path !== null) {
                $request->failAttempt($path);
            }
        } finally {
            if ($opened && $archive !== null) {
                try {
                    $archive->close();
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
            $archive = null;
            if ($claimed && ! $published && $request !== null && $path !== null) {
                $current = $request->fresh();
                if ($current !== null && $current->status === 'failed' && $current->zip_path === $path) {
                    $current->cleanupArtifacts();
                } elseif ($request->resolveArtifactPath($path) !== null) {
                    try {
                        if (! Storage::disk('local')->deleteDirectory(dirname($path))) {
                            throw new RuntimeException('ZIP attempt residue remains at '.$path);
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $current = $this->zipRequest->fresh();
        if ($current !== null && $current->failAttempt($current->attemptPath($this->attemptToken))) {
            if ($exception !== null) {
                report($exception);
            }
            $current->cleanupArtifacts();
        }
    }

    private function assertActiveAttempt(ZipRequest $request, string $path): void
    {
        $current = $request->fresh();
        if ($current === null || $current->status !== 'processing' || $current->zip_path !== $path
            || $current->expires_at === null || ! $current->expires_at->isFuture()) {
            throw new RuntimeException('ZIP processing attempt is no longer current.');
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

    private function copyToTemporaryFile(Uploaded $upload, string $directory): string
    {
        $stream = Storage::disk($upload->disk)->readStream($upload->path);
        if (! is_resource($stream)) {
            throw new RuntimeException('ZIP source stream is unavailable.');
        }

        try {
            $path = $directory.'/'.Str::uuid().'.tmp';
            $disk = Storage::disk('local');
            if (! $disk->put($path, $stream) || ($upload->size !== null && $disk->size($path) !== (int) $upload->size)) {
                throw new RuntimeException('ZIP source could not be copied completely.');
            }

            return $path;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
