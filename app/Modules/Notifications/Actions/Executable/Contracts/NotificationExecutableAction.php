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
     * - Validate the payload and authorize the user against the related entity
     * - Validate that the related entity still exists
     * - Use the notification's database connection for transactional domain effects
     * - Throw on refusal or failure so domain effects and notification state roll back
     * - Return safe frontend data only after successful domain logic
     *
     * The executor locks this notification, without automatically retrying the handler.
     * Other notifications for the same entity still need domain-level invariants.
     * External effects need their own post-commit, idempotency and durability design;
     * a database transaction does not make external delivery exactly once.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(AppNotification $notification, User $user, array $payload = []): mixed;
}
