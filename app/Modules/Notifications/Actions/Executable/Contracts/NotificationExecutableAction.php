<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions\Executable\Contracts;

use App\Models\User;
use App\Modules\Notifications\Models\AppNotification;

interface NotificationExecutableAction
{
    /**
     * Execute an action triggered from a notification.
     *
     * Implementations must:
     * - Validate that the user has permission to perform this action
     * - Validate that the related entity still exists
     * - Execute the domain logic
     * - Return a result that will be forwarded to the frontend
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(AppNotification $notification, User $user, array $payload = []): mixed;
}
