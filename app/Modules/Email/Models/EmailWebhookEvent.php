<?php

declare(strict_types=1);

namespace App\Modules\Email\Models;

use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailWebhookEvent extends Model
{
    use HasUuidIdentifier;

    protected $fillable = [
        'uuid',
        'email_log_id',
        'provider',
        'event_id',
        'event_type',
        'event_family',
        'message_id',
        'signature_id',
        'signature_timestamp',
        'headers',
        'payload',
        'raw_payload',
        'occurred_at',
        'received_at',
        'processed_at',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }

    public function markAsProcessed(): bool
    {
        return $this->update(['processed_at' => now()]);
    }

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeOfType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }
}
