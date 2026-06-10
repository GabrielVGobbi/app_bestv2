<?php

namespace App\Listeners;

use App\Supports\Traits\HasActivityLog;
use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    public function handle(Login $event): void
    {
        if (! in_array(HasActivityLog::class, class_uses_recursive($event->user))) {
            return;
        }

        $event->user->logLogin([
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
