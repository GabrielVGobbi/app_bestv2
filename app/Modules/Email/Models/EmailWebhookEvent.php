<?php

declare(strict_types=1);

namespace App\Modules\Email\Models;

use App\Supports\Traits\HasUuidIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailWebhookEvent extends Model
{
    use HasUuidIdentifier;

    protected $dateFormat = 'Y-m-d H:i:s.u';

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
        'payload_hash',
        'authenticated_at',
        'dispatch_token',
        'linked_at',
        'processing_result',
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
            'authenticated_at' => 'datetime',
            'linked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmailLog, $this> */
    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }

    /** @return Attribute<CarbonImmutable|null, never> */
    protected function occurredAt(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value, 'UTC'));
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
