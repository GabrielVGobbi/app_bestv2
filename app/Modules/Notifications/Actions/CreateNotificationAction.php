<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Events\NotificationCreated;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Broadcasting\BroadcastException;

class CreateNotificationAction
{
    public function handle(CreateNotificationDTO $dto): AppNotification
    {
        $notification = AppNotification::create([
            'recipient_id' => $dto->recipientId,
            'sender_id' => $dto->senderId,
            'type' => $dto->type->value,
            'category' => $dto->category->value,
            'title' => $dto->title,
            'body' => $dto->body,
            'data' => $dto->data,
            'actions' => $dto->actions,
            'link' => $dto->link,
            'notifiable_type' => $dto->notifiableType,
            'notifiable_id' => $dto->notifiableId,
            'action_status' => $dto->actions ? 'pending' : null,
        ]);

        try {
            broadcast(new NotificationCreated($notification))->toOthers();
        } catch (BroadcastException) {
            // Reverb unavailable — notification is persisted, realtime delivery skipped.
            report(new BroadcastException("Realtime broadcast skipped for notification {$notification->uuid}: Reverb unreachable."));
        }

        return $notification;
    }
}
