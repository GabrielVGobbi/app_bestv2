<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions\Executable;

use App\Models\User;
use App\Modules\Notifications\Actions\Executable\Contracts\NotificationExecutableAction;
use App\Modules\Notifications\Models\AppNotification;

/**
 * Example executable action for approving a request from a notification.
 *
 * Adapt this class to your domain: replace the comment blocks with real
 * model lookups, policy checks, and business logic.
 */
class ApproveRequestNotificationAction implements NotificationExecutableAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(AppNotification $notification, User $user, array $payload = []): mixed
    {
        // 1. Extract the related entity ID from notification data (display-only fields)
        $notifiableId = $notification->notifiable_id;
        $notifiableType = $notification->notifiable_type;

        if (! $notifiableId || ! $notifiableType) {
            abort(422, 'Esta notificação não está vinculada a uma entidade.');
        }

        // 2. Resolve the related model
        // $relatedModel = $notifiableType::findOrFail($notifiableId);

        // 3. Authorize via policy or gate
        // abort_unless($user->can('approve', $relatedModel), 403, 'Sem permissão para aprovar esta solicitação.');

        // 4. Execute domain logic
        // $relatedModel->approve($user);

        // 5. Return a response payload (safe data only)
        return [
            'message' => 'Solicitação aprovada com sucesso.',
            'notifiable_id' => $notifiableId,
        ];
    }
}
