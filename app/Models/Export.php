<?php

namespace App\Models;

use App\Supports\PrivateExportFiles;
use App\Supports\Traits\HasUuidIdentifier;
use Carbon\CarbonInterface;
use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Fillable([
    'uuid',
    'user_id',
    'model_class',
    'status',
    'format',
    'columns',
    'filters',
    'sort_column',
    'sort_direction',
    'search',
    'file_path',
    'file_name',
    'file_size',
    'total_records',
    'processed_records',
    'error_message',
    'started_at',
    'completed_at',
    'expires_at',
    'processing_token',
    'processing_expires_at',
    'processing_attempt',
    'processing_job_key',
    'dispatch_token',
])]
class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory, HasUuidIdentifier, Notifiable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            $export->dispatch_token ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
            'file_size' => 'integer',
            'total_records' => 'integer',
            'processed_records' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'processing_expires_at' => 'datetime',
            'processing_attempt' => 'integer',
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

    public function markAsCompleted(string $path, string $name, int $size, int $total): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'file_path' => $path,
            'file_name' => $name,
            'file_size' => $size,
            'total_records' => $total,
            'processed_records' => $total,
            'completed_at' => now(),
            'expires_at' => now()->addHours(config('exports.expires_after_hours', 1)),
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    public function hasActiveProcessingLease(): bool
    {
        return $this->status === self::STATUS_PROCESSING
            && $this->processing_token !== null
            && $this->processing_expires_at?->isFuture() === true;
    }

    public function claimProcessing(string $token, int $timeout, int $attempt, ?string $jobKey, ?string $dispatchToken, callable $discardPrevious): bool
    {
        return $this->getConnection()->transaction(function () use ($token, $timeout, $attempt, $jobKey, $dispatchToken, $discardPrevious): bool {
            $current = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();
            if ($current === null || $current->hasActiveProcessingLease()
                || $current->dispatch_token !== $dispatchToken
                || ! in_array($current->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true)) {
                return false;
            }

            $discardPrevious($current);

            return $current->update([
                'status' => self::STATUS_PROCESSING,
                'processing_token' => $token,
                'processing_attempt' => $attempt,
                'processing_job_key' => $jobKey,
                'processing_expires_at' => now()->addSeconds($timeout),
                'started_at' => now(),
                'processed_records' => 0,
                'error_message' => null,
            ]);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateProcessing(string $token, array $attributes): void
    {
        $this->getConnection()->transaction(function () use ($token, $attributes): void {
            $current = $this->currentProcessing($token);
            if ($current === null || ! $current->update($attributes)) {
                throw new RuntimeException('Export processing lease is no longer current.');
            }
        });
    }

    public function completeProcessing(string $token, string $path, string $name, int $size, int $total, callable $notify): void
    {
        $this->getConnection()->transaction(function () use ($token, $path, $name, $size, $total, $notify): void {
            $current = $this->currentProcessing($token);
            if ($current === null || ! $current->update([
                'status' => self::STATUS_COMPLETED,
                'file_path' => $path,
                'file_name' => $name,
                'file_size' => $size,
                'total_records' => $total,
                'processed_records' => $total,
                'completed_at' => now(),
                'expires_at' => now()->addHours(config('exports.expires_after_hours', 1)),
            ])) {
                throw new RuntimeException('Export processing lease was lost before completion.');
            }

            $notify($current);
        });
    }

    private function currentProcessing(string $token): ?self
    {
        return $this->newQuery()->whereKey($this->getKey())
            ->where('status', self::STATUS_PROCESSING)->where('processing_token', $token)
            ->where('processing_expires_at', '>', now())->lockForUpdate()->first();
    }

    public function incrementProcessed(int $count): void
    {
        $this->increment('processed_records', $count);
    }

    public function pruneRetained(CarbonInterface $at, CarbonInterface $failedBefore, PrivateExportFiles $files): bool
    {
        try {
            return $this->getConnection()->transaction(function () use ($at, $failedBefore, $files): bool {
                $current = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();
                if ($current === null || $current->isActive()) {
                    return true;
                }

                $expired = $current->status === self::STATUS_COMPLETED
                    && $current->expires_at !== null && $current->expires_at->lte($at);
                if (! $expired && $current->status !== self::STATUS_FAILED) {
                    return true;
                }

                $paths = $files->knownPaths((string) $current->uuid);
                if ($current->file_path !== null) {
                    $paths[] = $current->file_path;
                    $paths[] = $current->file_path.'.part';
                }
                $paths = array_values(array_unique($paths));
                foreach ($paths as $path) {
                    $reference = str_ends_with($path, '.part') ? substr($path, 0, -5) : $path;
                    $references = [$reference, $reference.'.part'];
                    $query = $current->newQuery()->whereKeyNot($current->getKey());
                    if (PHP_OS_FAMILY === 'Windows' && $files->usesLocalStorage()) {
                        $query->whereRaw('LOWER(file_path) IN (?, ?)', array_map('strtolower', $references));
                    } else {
                        $query->whereIn('file_path', $references);
                    }
                    if ($query->exists()) {
                        Log::warning('Export artifacts are referenced by another request.', ['export_id' => $current->getKey()]);

                        return false;
                    }
                }
                if (! $files->deletePaths((string) $current->uuid, $paths)) {
                    return false;
                }

                if ($expired || ($current->updated_at !== null && $current->updated_at->lte($failedBefore))) {
                    if ($current->delete() !== true) {
                        throw new RuntimeException('Export retention deletion was vetoed.');
                    }

                    return true;
                }

                if ($current->file_path !== null) {
                    $current->timestamps = false;
                    if (! $current->update(['file_path' => null])) {
                        throw new RuntimeException('Export artifact cleanup persistence was vetoed.');
                    }
                }

                return true;
            });
        } catch (Throwable $exception) {
            Log::warning('Export retention cleanup failed.', ['export_id' => $this->getKey(), 'exception' => $exception]);

            return false;
        }
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
}
