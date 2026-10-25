<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions\Executable;

use App\Models\User;
use App\Modules\Notifications\Actions\Executable\Contracts\NotificationExecutableAction;
use App\Modules\Notifications\Models\AppNotification;

/**
 * Disabled example: implement entity lookup, policy and domain logic before use.
 */
class RejectRequestNotificationAction implements NotificationExecutableAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(AppNotification $notification, User $user, array $payload = []): mixed
    {
        abort(422, 'Ação de recusar indisponível: handler de negócio não implementado.');
    }
}
