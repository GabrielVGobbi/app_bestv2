<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions\Executable;

use App\Models\Export;
use App\Models\User;
use App\Modules\Notifications\Actions\Executable\Contracts\NotificationExecutableAction;
use App\Modules\Notifications\Models\AppNotification;

/**
 * Executable action that returns a download URL for a completed export.
 *
 * This action reads the export_uuid from notification data and returns
 * a signed download URL — it does not execute any state-changing logic.
 */
class DownloadExportNotificationAction implements NotificationExecutableAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(AppNotification $notification, User $user, array $payload = []): mixed
    {
        // Read auxiliary ID from display-only data field
        $exportUuid = $notification->data['export_uuid'] ?? null;

        if (! $exportUuid) {
            abort(422, 'UUID da exportação não encontrado na notificação.');
        }

        $export = Export::whereUuid($exportUuid)->first();

        abort_unless($export !== null, 404, 'Exportação não encontrada.');
        abort_unless($export->isOwnedBy($user), 403, 'Sem permissão para baixar este arquivo.');
        abort_if($export->isExpired(), 410, 'O link de download expirou.');

        return [
            'download_url' => route('exports.download', $export->uuid),
            'file_name' => $export->file_name,
        ];
    }
}
