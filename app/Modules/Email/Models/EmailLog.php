<?php

declare(strict_types=1);

namespace App\Modules\Email\Models;

use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Enums\EmailStatus;
use App\Supports\Traits\HasUuidIdentifier;
use Carbon\CarbonImmutable;
use Database\Factories\EmailLogFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

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
        'reply_to',
        'scheduled_at',
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
        'dispatch_token',
        'retry_safe',
        'webhook_occurred_at',
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
            'retry_safe' => 'boolean',
            'scheduled_at' => 'immutable_datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'webhook_occurred_at' => 'immutable_datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return Attribute<CarbonImmutable|null, DateTimeInterface|string|null> */
    protected function scheduledAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value, 'UTC'),
            set: fn (DateTimeInterface|string|null $value): ?string => $value === null ? null
                : ($value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value, 'UTC'))
                    ->utc()->format('Y-m-d H:i:s.u'),
        );
    }

    /** @return Attribute<CarbonImmutable|null, DateTimeInterface|string|null> */
    protected function webhookOccurredAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value, 'UTC'),
            set: fn (DateTimeInterface|string|null $value): ?string => $value === null ? null
                : ($value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value, 'UTC'))
                    ->utc()->format('Y-m-d H:i:s.u'),
        );
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(EmailWebhookEvent::class);
    }

    public function markAsSent(?string $messageId = null): bool
    {
        return $this->updateDelivery([EmailStatus::Processing], [
            'status' => EmailStatus::Sent,
            'sent_at' => now(),
            'message_id' => $messageId,
            'error_message' => null,
            'retry_safe' => false,
        ]);
    }

    public function markAsFailed(string $errorMessage): bool
    {
        return $this->updateDelivery([EmailStatus::Queued], [
            'status' => EmailStatus::Failed,
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'retry_safe' => true,
        ]);
    }

    public function markAsDelivered(?array $metadata = null): bool
    {
        return $this->markFromWebhook(EmailStatus::Delivered, $metadata);
    }

    public function markAsBounced(?array $metadata = null): bool
    {
        return $this->markFromWebhook(EmailStatus::Bounced, $metadata);
    }

    public function markFromWebhook(EmailStatus $status, ?array $metadata = null, ?string $errorMessage = null): bool
    {
        $current = $this->deliveryQuery()->first();

        if ($current === null || in_array($status, [EmailStatus::Queued, EmailStatus::Processing], true)) {
            return false;
        }

        $safeFailureAccepted = $current->status === EmailStatus::Failed && $current->retry_safe && $status === EmailStatus::Sent;

        if (! $safeFailureAccepted && ! $current->status->acceptsProviderStatus($status)) {
            if ($current->status === $status && $current->retry_safe) {
                return $this->updateDelivery([$current->status], ['retry_safe' => false], true);
            }

            return false;
        }

        $attributes = [
            'status' => $status,
            'metadata' => $metadata,
            'retry_safe' => false,
        ];

        if ($status === EmailStatus::Sent && $current->sent_at === null) {
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

        return $this->updateDelivery([$current->status], $attributes, $current->retry_safe);
    }

    public function queueAgain(): ?self
    {
        $changed = $this->deliveryQuery()
            ->where('status', EmailStatus::Failed)
            ->where('retry_safe', true)
            ->whereNull('sent_at')
            ->whereNull('message_id')
            ->update([
                'status' => EmailStatus::Queued,
                'dispatch_token' => (string) Str::uuid(),
                'retry_safe' => false,
                'error_message' => null,
                'failed_at' => null,
                'metadata' => null,
                'webhook_occurred_at' => null,
            ]);

        return $changed === 1 ? $this->fresh() : null;
    }

    /** @return Builder<static> */
    public function deliveryQuery(): Builder
    {
        return static::query()->whereKey($this->getKey())->where('dispatch_token', $this->dispatch_token);
    }

    /**
     * @param  list<EmailStatus>  $states
     * @param  array<string, mixed>  $attributes
     */
    private function updateDelivery(array $states, array $attributes, ?bool $expectedRetrySafe = null): bool
    {
        $values = $this->newInstance()->forceFill($attributes)->getAttributes();
        $query = $this->deliveryQuery()->whereIn('status', $states);

        if ($expectedRetrySafe !== null) {
            $query->where('retry_safe', $expectedRetrySafe);
        }

        $changed = $query->update($values) === 1;

        if ($changed) {
            $this->refresh();
        }

        return $changed;
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
