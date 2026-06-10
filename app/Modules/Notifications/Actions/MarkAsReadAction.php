<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\AppNotification;

class MarkAsReadAction
{
    public function handle(AppNotification $notification): AppNotification
    {
        $notification->markAsRead();

        return $notification->refresh();
    }
}
