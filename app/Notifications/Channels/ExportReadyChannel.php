<?php

namespace App\Notifications\Channels;

use App\Models\Export;
use App\Models\User;
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\Models\AppNotification;
use App\Notifications\ExportReadyNotification;
use InvalidArgumentException;

class ExportReadyChannel
{
    public function __construct(private readonly CreateNotificationAction $createNotification) {}

    public function send(object $notifiable, ExportReadyNotification $notification): AppNotification
    {
        $export = $notification->export->fresh();
        if (! $notifiable instanceof User || ! $export instanceof Export
            || $export->status !== Export::STATUS_COMPLETED || $export->user_id !== $notifiable->id) {
            throw new InvalidArgumentException('Export ready delivery requires its completed export and owner.');
        }

        if ($export->getConnection()->getName() !== (new AppNotification)->getConnection()->getName()) {
            throw new InvalidArgumentException('Export completion and ready notice must use the same database connection.');
        }

        return $this->createNotification->handle(
            (new ExportReadyNotification($export))->toAppNotification($notifiable),
            afterCommit: true,
        );
    }
}
