<?php

declare(strict_types=1);

namespace App\Modules\Backup\Models;

use App\Models\User;
use App\Supports\PrivateExportFiles;
use App\Supports\Traits\HasUuidIdentifier;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

#[Fillable([
    'uuid',
    'user_id',
    'tables',
    'export_type',
    'status',
    'file_path',
    'file_name',
    'file_size',
    'error_message',
    'started_at',
    'completed_at',
    'expires_at',
])]
class DatabaseExport extends Model
{
    use HasUuidIdentifier;

    public const int PROCESSING_TIMEOUT = 900;

    public const int PENDING_TIMEOUT = 1020;

    public const string FAILURE_MESSAGE = 'Não foi possível disponibilizar o dump. Solicite uma nova exportação.';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TYPE_SCHEMA = 'schema';

    public const TYPE_DATA = 'data';

    public const TYPE_FULL = 'full';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tables' => 'array',
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $path, string $name, int $size): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'file_path' => $path,
            'file_name' => $name,
            'file_size' => $size,
            'completed_at' => now(),
            'expires_at' => now()->addHours(config('database-exports.expires_after_hours', 24)),
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function candidatePath(string $token): string
    {
        if (! Str::isUuid((string) $this->uuid) || ! Str::isUuid($token)) {
            throw new InvalidArgumentException('Invalid database export attempt identifier.');
        }

        return $this->artifactDirectory().'/'.$this->uuid.'/'.$token.'.sql';
    }

    public function claimAttempt(string $path): bool
    {
        if (! $this->isCandidatePath($path)) {
            return false;
        }

        if ($this->failStalePending()) {
            $this->cleanupArtifacts();

            return false;
        }

        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $claimed = $this->newQuery()->whereKey($this->getKey())->where('uuid', $this->uuid)
            ->where('status', self::STATUS_PENDING)->whereNull('file_path')
            ->where('updated_at', '>', now()->subSeconds(self::PENDING_TIMEOUT))
            ->update(['status' => self::STATUS_PROCESSING, 'file_path' => $path, 'started_at' => now(), 'error_message' => null]) === 1;
        $this->reloadState();

        if (! $claimed && $this->status === self::STATUS_PENDING && $this->failStalePending()) {
            $this->cleanupArtifacts();
        }

        return $claimed;
    }

    /** @param callable(self): void $notify */
    public function completeAttempt(string $path, string $name, int $size, callable $notify): bool
    {
        if (! $this->isCandidatePath($path)) {
            return false;
        }

        $completed = $this->getConnection()->transaction(function () use ($path, $name, $size, $notify): bool {
            $current = $this->newQuery()->whereKey($this->getKey())->where('uuid', $this->uuid)
                ->where('status', self::STATUS_PROCESSING)->where('file_path', $path)
                ->where('started_at', '>', now()->subSeconds(self::PROCESSING_TIMEOUT))
                ->lockForUpdate()->first();

            if ($current === null) {
                return false;
            }

            if (! $current->update([
                'status' => self::STATUS_COMPLETED, 'file_name' => $name, 'file_size' => $size,
                'completed_at' => now(), 'expires_at' => now()->addHours(config('database-exports.expires_after_hours', 24)),
                'error_message' => null,
            ])) {
                throw new RuntimeException('Database export completion persistence was vetoed.');
            }

            $notify($current);

            return true;
        });
        $this->reloadState();

        return $completed;
    }

    public function failAttempt(string $path): bool
    {
        if (! $this->isCandidatePath($path)) {
            return false;
        }

        $failed = $this->newQuery()->whereKey($this->getKey())->where('uuid', $this->uuid)
            ->where(function (Builder $query) use ($path): void {
                $query->where(function (Builder $processing) use ($path): void {
                    $processing->where('status', self::STATUS_PROCESSING)->where('file_path', $path);
                })->orWhere(function (Builder $pending): void {
                    $pending->where('status', self::STATUS_PENDING)->whereNull('file_path');
                });
            })->update(['status' => self::STATUS_FAILED, 'error_message' => self::FAILURE_MESSAGE]) === 1;
        $this->reloadState();

        return $failed;
    }

    public function failStaleProcessing(): bool
    {
        if (! $this->reloadState() || $this->status !== self::STATUS_PROCESSING) {
            return false;
        }

        $started = $this->started_at ?? $this->updated_at;

        if ($started !== null && $started->gt(now()->subSeconds(self::PROCESSING_TIMEOUT))) {
            return false;
        }

        $failed = $this->stateQuery()->update(['status' => self::STATUS_FAILED, 'error_message' => self::FAILURE_MESSAGE]) === 1;
        $this->reloadState();

        return $failed;
    }

    /** @phpstan-impure */
    public function failStalePending(): bool
    {
        if (! $this->reloadState() || $this->status !== self::STATUS_PENDING
            || ($this->updated_at !== null && $this->updated_at->gt(now()->subSeconds(self::PENDING_TIMEOUT)))) {
            return false;
        }

        $failed = $this->stateQuery()->update(['status' => self::STATUS_FAILED, 'error_message' => self::FAILURE_MESSAGE]) === 1;
        $this->reloadState();

        return $failed;
    }

    public function cleanupArtifacts(): bool
    {
        try {
            if (! $this->reloadState()) {
                return true;
            }

            if ($this->status !== self::STATUS_FAILED
                && ! ($this->status === self::STATUS_COMPLETED && $this->isExpired())) {
                return false;
            }

            if (! $this->stateQuery()->exists() || ! $this->cleanRetainedFiles($this->artifactFiles(), false)) {
                return false;
            }

            if ($this->file_path === null) {
                return true;
            }

            $cleared = $this->stateQuery()->toBase()->update(['file_path' => null]) === 1;
            $this->reloadState();

            return $cleared || $this->file_path === null;
        } catch (Throwable $exception) {
            $this->logArtifactFailure('Database export artifact cleanup failed.', $exception);

            return false;
        }
    }

    public function cleanupAttempt(string $path): bool
    {
        $current = $this->fresh();

        if ($current !== null && $current->file_path === $path) {
            if ($current->status === self::STATUS_FAILED) {
                return $current->cleanupArtifacts();
            }

            return false;
        }

        return $this->isCandidatePath($path) && $this->deleteArtifactFiles($path, true);
    }

    public function cleanupTemporary(string $path): bool
    {
        return $this->isCandidatePath($path) && $this->deleteArtifactFiles($path, false);
    }

    public function deleteExpiredRecord(): bool
    {
        if (! $this->reloadState() || $this->status !== self::STATUS_COMPLETED || ! $this->isExpired()) {
            return true;
        }

        return $this->pruneRetained(now(), now()->subDays(max(1, (int) config('database-exports.failed_retention_days', 7))), $this->artifactFiles());
    }

    public function pruneRetained(CarbonInterface $now, CarbonInterface $failedBefore, PrivateExportFiles $files): bool
    {
        try {
            return $this->getConnection()->transaction(function () use ($now, $failedBefore, $files): bool {
                $current = $this->newQuery()->whereKey($this->getKey())->where('uuid', $this->uuid)->lockForUpdate()->first();

                if ($current === null) {
                    return true;
                }

                $pendingExpired = $current->status === self::STATUS_PENDING
                    && ($current->updated_at === null || $current->updated_at->lte($now->copy()->subSeconds(self::PENDING_TIMEOUT)));
                $started = $current->started_at ?? $current->updated_at;
                $processingExpired = $current->status === self::STATUS_PROCESSING
                    && ($started === null || $started->lte($now->copy()->subSeconds(self::PROCESSING_TIMEOUT)));

                if (($pendingExpired || $processingExpired)
                    && ! $current->update(['status' => self::STATUS_FAILED, 'error_message' => self::FAILURE_MESSAGE])) {
                    throw new RuntimeException('Database export failure persistence was vetoed.');
                }

                $expired = $current->status === self::STATUS_COMPLETED && $current->expires_at !== null
                    && $current->expires_at->lte($now);

                if ($current->status !== self::STATUS_FAILED && ! $expired) {
                    return true;
                }

                $removeRecord = $expired || ($current->updated_at !== null && $current->updated_at->lte($failedBefore));

                if (! $current->cleanRetainedFiles($files, ! $removeRecord)) {
                    return false;
                }

                if ($removeRecord && $current->delete() !== true) {
                    throw new RuntimeException('Database export record deletion was vetoed.');
                }

                return true;
            });
        } catch (Throwable $exception) {
            $this->logArtifactFailure('Database export retention cleanup failed.', $exception);

            return false;
        }
    }

    private function cleanRetainedFiles(PrivateExportFiles $files, bool $clearPath = true): bool
    {
        $paths = $files->knownPaths((string) $this->uuid);

        if ($this->file_path !== null) {
            $paths[] = $this->file_path;
            $paths[] = str_ends_with($this->file_path, '.part') ? substr($this->file_path, 0, -5) : $this->file_path.'.part';
        }

        $references = [];

        foreach ($paths as $path) {
            $base = str_ends_with($path, '.part') ? substr($path, 0, -5) : $path;
            $references[] = $base;
            $references[] = $base.'.part';
        }

        $references = array_values(array_unique($references));
        $query = $this->newQuery()->whereKeyNot($this->getKey());
        if (PHP_OS_FAMILY === 'Windows' && $references !== []) {
            $query->whereRaw('LOWER(file_path) IN ('.implode(', ', array_fill(0, count($references), '?')).')', array_map('strtolower', $references));
        } else {
            $query->whereIn('file_path', $references);
        }
        if ($query->exists()) {
            $this->logArtifactFailure('Database export artifacts are referenced by another request.');

            return false;
        }

        if (! $files->deletePaths((string) $this->uuid, array_values(array_unique($paths)))) {
            return false;
        }

        if ($clearPath && $this->file_path !== null) {
            $timestamps = $this->timestamps;

            try {
                $this->timestamps = false;
                $this->setAttribute('file_path', null);

                if (! $this->save()) {
                    throw new RuntimeException('Database export artifact metadata persistence was vetoed.');
                }
            } finally {
                $this->timestamps = $timestamps;
            }
        }

        return true;
    }

    public function resolveArtifactPath(string $path): ?string
    {
        $files = $this->artifactFiles();
        $parsed = $files->parse($path);

        return $parsed !== null && ! $parsed['temporary'] ? $files->resolveLocal((string) $this->uuid, $path) : null;
    }

    public function resolveTemporaryPath(string $path): ?string
    {
        return $this->isCandidatePath($path) ? $this->artifactFiles()->resolveLocal((string) $this->uuid, $path.'.part') : null;
    }

    private function artifactDirectory(): string
    {
        return $this->artifactFiles()->directory();
    }

    private function isCandidatePath(string $path): bool
    {
        $parsed = $this->artifactFiles()->parse($path);

        return $parsed !== null && strcasecmp($parsed['uuid'], (string) $this->uuid) === 0
            && $parsed['token'] !== null && ! $parsed['temporary'];
    }

    private function artifactFiles(): PrivateExportFiles
    {
        return new PrivateExportFiles('database-exports', ['sql']);
    }

    private function deleteArtifactFiles(string $path, bool $includeFinal): bool
    {
        return $this->artifactFiles()->deletePaths((string) $this->uuid, $includeFinal ? [$path, $path.'.part'] : [$path.'.part']);
    }

    /** @return Builder<self> */
    private function stateQuery(): Builder
    {
        return $this->newQuery()->whereKey($this->getKey())->where('uuid', $this->uuid)
            ->where('status', $this->status)->where('file_path', $this->getRawOriginal('file_path'))
            ->where('started_at', $this->getRawOriginal('started_at'))
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

    private function logArtifactFailure(string $message, ?Throwable $exception = null): void
    {
        Log::warning($message, ['database_export_id' => $this->getKey(), 'file_path' => $this->file_path, 'exception' => $exception]);
    }
}
