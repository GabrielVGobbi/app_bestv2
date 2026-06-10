<?php

namespace App\Models;

use App\Supports\Traits\HasUuidIdentifier;
use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

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
])]
class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory, HasUuidIdentifier, Notifiable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

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

    public function incrementProcessed(int $count): void
    {
        $this->increment('processed_records', $count);
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
