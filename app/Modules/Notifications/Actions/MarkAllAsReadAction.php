<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Models\User;
use App\Modules\Notifications\Models\AppNotification;

class MarkAllAsReadAction
{
    public function handle(User $user): int
    {
        return AppNotification::forRecipient($user)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
