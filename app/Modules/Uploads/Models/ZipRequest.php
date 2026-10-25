<?php

declare(strict_types=1);

namespace App\Modules\Uploads\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

class ZipRequest extends Model
{
    public const string FAILURE_MESSAGE = 'Não foi possível disponibilizar o arquivo ZIP. Solicite novamente.';

    protected $table = 'zip_requests';

    protected $fillable = [
        'uuid',
        'user_id',
        'label',
        'file_ids',
        'file_count',
        'status',
        'zip_path',
        'error_message',
        'expires_at',
    ];

    protected $casts = [
        'file_ids' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready'
            && $this->artifactPath() !== null
            && $this->deadline()?->isFuture() === true;
    }

    public function attemptPath(string $token): string
    {
        if (! Str::isUuid((string) $this->uuid) || ! Str::isUuid($token)) {
            throw new InvalidArgumentException('Invalid ZIP request or attempt identifier.');
        }

        return "zip-requests/{$this->uuid}/{$token}/archive.zip";
    }

    public function artifactPath(): ?string
    {
        $path = $this->getAttribute('zip_path') ?? "zip-requests/{$this->uuid}.zip";

        return is_string($path) && $this->isArtifactPath($path) ? $path : null;
    }

    public function resolveArtifactPath(string $relativePath): ?string
    {
        if (! $this->isArtifactPath($relativePath)) {
            return null;
        }

        try {
            $disk = $this->privateLocalDisk(Storage::disk('local'));

            if ($disk === null) {
                return null;
            }

            $root = rtrim(str_replace('\\', '/', $disk->path('')), '/');

            if ($root === '' || str_contains($root, "\0")
                || preg_match('~(?:^|/)\.\.?(/|$)~', $root) === 1
                || (! str_starts_with($root, '/') && preg_match('~^[a-z]:/~i', $root) !== 1)) {
                return null;
            }

            $ancestor = $root;

            while (! file_exists($ancestor) && ! is_link($ancestor)) {
                $parent = dirname($ancestor);

                if ($parent === $ancestor) {
                    return null;
                }

                $ancestor = $parent;
            }

            if (! $this->isCanonicalEntry($ancestor, true)) {
                return null;
            }

            $resolvedAncestor = realpath($ancestor);

            if ($resolvedAncestor === false) {
                return null;
            }

            $root = rtrim(str_replace('\\', '/', $resolvedAncestor), '/')
                .substr($root, strlen(rtrim(str_replace('\\', '/', $ancestor), '/')));

            foreach ([public_path(), config('filesystems.disks.public.root')] as $publicRoot) {
                if (! is_string($publicRoot) || $publicRoot === '') {
                    continue;
                }

                $canonicalPublicRoot = $this->canonicalPublicRoot($publicRoot);

                if ($canonicalPublicRoot === null) {
                    return null;
                }

                if ($this->isWithin($root, $canonicalPublicRoot)
                    || $this->isWithin($root.'/'.$relativePath, $canonicalPublicRoot)) {
                    return null;
                }
            }

            $current = $root;
            $parts = explode('/', $relativePath);

            foreach ($parts as $index => $part) {
                $current .= '/'.$part;
                clearstatcache(true, $current);

                if (! file_exists($current) && ! is_link($current)) {
                    continue;
                }

                if (! $this->isCanonicalEntry($current, $index < count($parts) - 1)) {
                    return null;
                }
            }

            return $root.'/'.$relativePath;
        } catch (Throwable $exception) {
            $this->logCleanupFailure('Unable to resolve private ZIP storage.', $exception);

            return null;
        }
    }

    public function claimAttempt(string $path): bool
    {
        if (! $this->isAttemptPath($path)) {
            return false;
        }

        if (! $this->reloadState() || $this->status !== 'pending') {
            return false;
        }

        if ($this->deadline()?->isFuture() !== true) {
            $expired = $this->stateQuery()->where('status', 'pending')->update([
                'status' => 'failed',
                'error_message' => self::FAILURE_MESSAGE,
            ]) === 1;
            $this->reloadState();

            if ($expired) {
                $this->cleanupArtifacts();
            }

            return false;
        }

        $claimed = $this->newQuery()->whereKey($this->getKey())->where('uuid', $this->uuid)
            ->where('status', 'pending')->whereNull('zip_path')
            ->where('updated_at', '>', now()->subSeconds(max(1, (int) config('uploads.zip.pending_timeout', 120))))
            ->update([
                'status' => 'processing',
                'zip_path' => $path,
                'error_message' => null,
                'expires_at' => now()->addSeconds(max(1, (int) config('uploads.zip.processing_timeout', 60))),
            ]) === 1;
        $this->reloadState();

        return $claimed;
    }

    public function completeAttempt(string $path): bool
    {
        if (! $this->isAttemptPath($path)) {
            return false;
        }

        $completed = $this->newQuery()->whereKey($this->getKey())->where('uuid', $this->uuid)
            ->where('status', 'processing')->where('zip_path', $path)
            ->where('expires_at', '>', now())
            ->update([
                'status' => 'ready',
                'error_message' => null,
                'expires_at' => now()->addHours(max(1, (int) config('uploads.zip.retention_hours', 2))),
            ]) === 1;
        $this->reloadState();

        return $completed;
    }

    public function failAttempt(string $path): bool
    {
        if (! $this->isAttemptPath($path)) {
            return false;
        }

        $failed = $this->newQuery()->whereKey($this->getKey())->where('uuid', $this->uuid)
            ->where(function (Builder $query) use ($path): void {
                $query->where(function (Builder $processing) use ($path): void {
                    $processing->where('status', 'processing')->where('zip_path', $path);
                })->orWhere(function (Builder $pending): void {
                    $pending->where('status', 'pending')->whereNull('zip_path');
                });
            })
            ->update(['status' => 'failed', 'error_message' => self::FAILURE_MESSAGE]) === 1;
        $this->reloadState();

        return $failed;
    }

    public function cleanupArtifacts(): bool
    {
        if (! $this->reloadState() || $this->status !== 'failed') {
            return true;
        }

        $path = $this->artifactPath();
        $absolute = $path === null ? null : $this->resolveArtifactPath($path);

        if ($path === null || $absolute === null) {
            $this->logCleanupFailure('ZIP artifact metadata or storage is unsafe.');

            return false;
        }

        $directory = $this->isAttemptPath($path);
        $target = $directory ? dirname($absolute) : $absolute;
        $relativeTarget = $directory ? dirname($path) : $path;

        try {
            if ($directory && is_dir($target) && ! $this->hasSafeContents($target)) {
                $this->logCleanupFailure('ZIP attempt contains redirected or linked entries.');

                return false;
            }

            if (! $this->stateQuery()->exists()) {
                $this->reloadState();

                return false;
            }

            $disk = Storage::disk('local');

            if (file_exists($target) || is_link($target)) {
                $deleted = $directory
                    ? $disk->deleteDirectory($relativeTarget)
                    : $disk->delete($relativeTarget);
                clearstatcache(true, $target);

                if (! $deleted || file_exists($target) || is_link($target)) {
                    $this->logCleanupFailure('ZIP artifact deletion was refused or left a residue.');

                    return false;
                }
            }

            if ($this->zip_path !== null) {
                $cleared = $this->stateQuery()->update(['zip_path' => null]) === 1;
                $this->reloadState();

                return $cleared || ($this->status === 'failed' && $this->zip_path === null);
            }

            return true;
        } catch (Throwable $exception) {
            $this->logCleanupFailure('ZIP artifact cleanup failed.', $exception);

            return false;
        }
    }

    public function expireIfNeeded(): bool
    {
        if (! $this->reloadState()) {
            return true;
        }

        if ($this->status === 'failed') {
            return $this->cleanupArtifacts();
        }

        if (! in_array($this->status, ['pending', 'processing', 'ready'], true)
            || $this->deadline()?->isFuture() === true) {
            return true;
        }

        $expired = $this->stateQuery()->update([
            'status' => 'failed',
            'error_message' => self::FAILURE_MESSAGE,
        ]) === 1;
        $this->reloadState();

        return ! $expired || $this->cleanupArtifacts();
    }

    public function markUnavailable(): bool
    {
        if (! $this->reloadState() || $this->status !== 'ready') {
            return false;
        }

        $path = $this->artifactPath();
        $absolute = $path === null ? null : $this->resolveArtifactPath($path);

        if ($absolute !== null && is_file($absolute)) {
            return false;
        }

        $failed = $this->stateQuery()->update([
            'status' => 'failed',
            'error_message' => self::FAILURE_MESSAGE,
        ]) === 1;
        $this->reloadState();

        if ($failed) {
            $this->cleanupArtifacts();
        }

        return $failed;
    }

    private function deadline(): ?CarbonInterface
    {
        if ($this->status !== 'pending' && $this->expires_at !== null) {
            return $this->expires_at;
        }

        if ($this->updated_at === null) {
            return null;
        }

        return match ($this->status) {
            'pending' => $this->updated_at->copy()->addSeconds(max(1, (int) config('uploads.zip.pending_timeout', 120))),
            'processing' => $this->updated_at->copy()->addSeconds(max(1, (int) config('uploads.zip.processing_timeout', 60))),
            'ready' => $this->updated_at->copy()->addHours(max(1, (int) config('uploads.zip.retention_hours', 2))),
            default => null,
        };
    }

    private function isArtifactPath(string $path): bool
    {
        return Str::isUuid((string) $this->uuid)
            && ($path === "zip-requests/{$this->uuid}.zip" || $this->isAttemptPath($path));
    }

    private function isAttemptPath(string $path): bool
    {
        if (! Str::isUuid((string) $this->uuid)) {
            return false;
        }

        $prefix = "zip-requests/{$this->uuid}/";

        if (! str_starts_with($path, $prefix) || ! str_ends_with($path, '/archive.zip')) {
            return false;
        }

        return Str::isUuid(substr($path, strlen($prefix), -strlen('/archive.zip')));
    }

    /** @return Builder<self> */
    private function stateQuery(): Builder
    {
        return $this->newQuery()->whereKey($this->getKey())
            ->where('uuid', $this->uuid)
            ->where('status', $this->status)
            ->where('zip_path', $this->getRawOriginal('zip_path'))
            ->where('expires_at', $this->getRawOriginal('expires_at'))
            ->where('updated_at', $this->getRawOriginal('updated_at'));
    }

    /** @phpstan-impure */
    private function reloadState(): bool
    {
        $fresh = $this->fresh();

        if ($fresh === null) {
            return false;
        }

        $this->setRawAttributes($fresh->getAttributes(), true);
        $this->setRelations($fresh->getRelations());

        return true;
    }

    private function privateLocalDisk(mixed $disk): ?FilesystemAdapter
    {
        if (! $disk instanceof FilesystemAdapter || ! $disk->getAdapter() instanceof LocalFilesystemAdapter) {
            return null;
        }

        $configuration = $disk->getConfig();

        return ($configuration['driver'] ?? 'local') === 'local'
            && ($configuration['visibility'] ?? null) !== 'public' ? $disk : null;
    }

    private function normalizePath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }

    private function canonicalPublicRoot(string $path): ?string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, "\0")
            || preg_match('~(?:^|/)\.\.?(/|$)~', $path) === 1
            || (! str_starts_with($path, '/') && preg_match('~^[a-z]:/~i', $path) !== 1)) {
            return null;
        }

        $ancestor = $path;

        while (! file_exists($ancestor) && ! is_link($ancestor)) {
            $parent = dirname($ancestor);

            if ($parent === $ancestor) {
                return null;
            }

            $ancestor = $parent;
        }

        $resolved = realpath($ancestor);

        if ($resolved === false || ! is_dir($resolved)) {
            return null;
        }

        return rtrim(str_replace('\\', '/', $resolved), '/')
            .substr($path, strlen(rtrim(str_replace('\\', '/', $ancestor), '/')));
    }

    private function isWithin(string $path, string $root): bool
    {
        $normalized = $this->normalizePath($path);
        $base = $this->normalizePath($root);

        return $normalized === $base || str_starts_with($normalized, $base.'/');
    }

    private function isCanonicalEntry(string $path, bool $directory): bool
    {
        clearstatcache(true, $path);
        $stat = lstat($path);
        $resolved = realpath($path);
        $parent = realpath(dirname($path));
        $expected = $parent === false ? null : rtrim($parent, '/\\').'/'.basename($path);

        return $stat !== false && $resolved !== false && $expected !== null && ! is_link($path)
            && $this->normalizePath($resolved) === $this->normalizePath($expected)
            && ($stat['mode'] & 0170000) === ($directory ? 0040000 : 0100000)
            && ($directory || $stat['nlink'] === 1);
    }

    private function hasSafeContents(string $directory): bool
    {
        $entries = scandir($directory);

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;
            $isDirectory = is_dir($path);

            if (! $this->isCanonicalEntry($path, $isDirectory)
                || ($isDirectory && ! $this->hasSafeContents($path))) {
                return false;
            }
        }

        return true;
    }

    private function logCleanupFailure(string $message, ?Throwable $exception = null): void
    {
        Log::warning($message, [
            'zip_request_id' => $this->getKey(),
            'zip_request_uuid' => $this->uuid,
            'zip_path' => $this->zip_path,
            'exception' => $exception,
        ]);
    }
}
