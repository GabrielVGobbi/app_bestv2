<?php

declare(strict_types=1);

namespace App\Modules\Email\Models;

use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Enums\EmailStatus;
use App\Supports\Traits\HasUuidIdentifier;
use Database\Factories\EmailLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    /** @use HasFactory<EmailLogFactory> */
    use HasFactory, HasUuidIdentifier;

    protected static function newFactory(): EmailLogFactory
    {
        return EmailLogFactory::new();
    }

    protected $table = 'email_logs';

    protected $fillable = [
        'uuid',
        'to_email',
        'to_name',
        'from_email',
        'from_name',
        'subject',
        'template',
        'variables',
        'category',
        'status',
        'error_message',
        'attempts',
        'notifiable_type',
        'notifiable_id',
        'idempotency_key',
        'sent_at',
        'failed_at',
        'mailer',
        'message_id',
        'metadata',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'category' => EmailCategory::class,
            'status' => EmailStatus::class,
            'variables' => 'array',
            'metadata' => 'array',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(EmailWebhookEvent::class);
    }

    public function markAsSent(?string $messageId = null): bool
    {
        return $this->update([
            'status' => EmailStatus::Sent,
            'sent_at' => now(),
            'message_id' => $messageId,
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'status' => EmailStatus::Failed,
            'failed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    public function markAsDelivered(?array $metadata = null): bool
    {
        return $this->update([
            'status' => EmailStatus::Delivered,
            'metadata' => $metadata,
        ]);
    }

    public function markAsBounced(?array $metadata = null): bool
    {
        return $this->update([
            'status' => EmailStatus::Bounced,
            'metadata' => $metadata,
        ]);
    }

    public function markFromWebhook(EmailStatus $status, ?array $metadata = null, ?string $errorMessage = null): bool
    {
        $attributes = [
            'status' => $status,
            'metadata' => $metadata,
        ];

        if ($status === EmailStatus::Sent && $this->sent_at === null) {
            $attributes['sent_at'] = now();
        }

        if (in_array($status, [EmailStatus::Failed, EmailStatus::Bounced, EmailStatus::Suppressed], true)) {
            $attributes['failed_at'] = now();
        }

        if ($errorMessage !== null) {
            $attributes['error_message'] = $errorMessage;
        } elseif (! in_array($status, [EmailStatus::Failed, EmailStatus::Bounced, EmailStatus::DeliveryDelayed, EmailStatus::Suppressed], true)) {
            $attributes['error_message'] = null;
        }

        return $this->update($attributes);
    }

    public function incrementAttempts(): bool
    {
        return $this->update(['attempts' => $this->attempts + 1]);
    }

    public function isSent(): bool
    {
        return $this->status === EmailStatus::Sent;
    }

    public function isFailed(): bool
    {
        return $this->status === EmailStatus::Failed;
    }

    public function scopeOfStatus(Builder $query, EmailStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeOfCategory(Builder $query, EmailCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('to_email', $email);
    }
}
