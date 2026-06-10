<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions\Executable;

use App\Models\User;
use App\Modules\Notifications\Actions\Executable\Contracts\NotificationExecutableAction;
use App\Modules\Notifications\Models\AppNotification;

/**
 * Example executable action for rejecting a request from a notification.
 *
 * Adapt this class to your domain: replace the comment blocks with real
 * model lookups, policy checks, and business logic.
 */
class RejectRequestNotificationAction implements NotificationExecutableAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(AppNotification $notification, User $user, array $payload = []): mixed
    {
        $notifiableId = $notification->notifiable_id;
        $notifiableType = $notification->notifiable_type;

        if (! $notifiableId || ! $notifiableType) {
            abort(422, 'Esta notificação não está vinculada a uma entidade.');
        }

        // 1. Resolve the related model
        // $relatedModel = $notifiableType::findOrFail($notifiableId);

        // 2. Authorize via policy or gate
        // abort_unless($user->can('reject', $relatedModel), 403, 'Sem permissão para recusar esta solicitação.');

        // 3. Optionally use payload data (reason, etc.) passed from frontend
        // $reason = $payload['reason'] ?? null;

        // 4. Execute domain logic
        // $relatedModel->reject($user, $reason);

        return [
            'message' => 'Solicitação recusada.',
            'notifiable_id' => $notifiableId,
        ];
    }
}
