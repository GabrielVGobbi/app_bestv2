<?php

namespace App\Listeners;

use App\Supports\Traits\HasActivityLog;
use Illuminate\Auth\Events\Logout;

class LogUserLogout
{
    public function handle(Logout $event): void
    {
        if (! $event->user || ! in_array(HasActivityLog::class, class_uses_recursive($event->user), true)) {
            return;
        }

        $event->user->logLogout([
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
