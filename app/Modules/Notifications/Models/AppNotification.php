<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Supports\Traits\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppNotification extends Model
{
    use HasUuidIdentifier;

    protected $table = 'app_notifications';

    protected $fillable = [
        'uuid',
        'recipient_id',
        'sender_id',
        'type',
        'category',
        'title',
        'body',
        'data',
        'actions',
        'action_status',
        'link',
        'notifiable_type',
        'notifiable_id',
        'read_at',
        'clicked_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'category' => NotificationCategory::class,
            'data' => 'array',
            'actions' => 'array',
            'read_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeForRecipient(Builder $query, User $user): Builder
    {
        return $query->where('recipient_id', $user->id);
    }

    public function scopeOfType(Builder $query, NotificationType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeOfCategory(Builder $query, NotificationCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    public function markAsRead(): bool
    {
        if ($this->isRead()) {
            return false;
        }

        return $this->update(['read_at' => now()]);
    }

    public function markAsClicked(): bool
    {
        return $this->update(['clicked_at' => now()]);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function hasActions(): bool
    {
        return ! empty($this->actions);
    }

    public function hasActionOfType(string $actionType): bool
    {
        if (empty($this->actions)) {
            return false;
        }

        return collect($this->actions)->contains('type', $actionType);
    }

    public function isActionPending(): bool
    {
        return $this->action_status === null || $this->action_status === 'pending';
    }
}
