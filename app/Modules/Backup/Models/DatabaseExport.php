<?php

declare(strict_types=1);

namespace App\Modules\Backup\Models;

use App\Models\User;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
