<?php

declare(strict_types=1);

namespace App\Supports\Traits;

use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasNotifications
{
    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'recipient_id');
    }

    public function unreadNotificationsCount(): int
    {
        return $this->appNotifications()->whereNull('read_at')->count();
    }

    public function hasUnreadNotifications(): bool
    {
        return $this->unreadNotificationsCount() > 0;
    }
}
