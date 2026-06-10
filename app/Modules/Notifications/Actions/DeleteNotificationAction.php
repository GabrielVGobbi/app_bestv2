<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\AppNotification;

class DeleteNotificationAction
{
    public function handle(AppNotification $notification): bool
    {
        return (bool) $notification->delete();
    }
}
