<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Events\NotificationCreated;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Broadcasting\BroadcastException;
use RuntimeException;
use Throwable;

class CreateNotificationAction
{
    public function handle(CreateNotificationDTO $dto, bool $afterCommit = false): AppNotification
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

        if (! $notification->exists) {
            throw new RuntimeException('Notification persistence was vetoed.');
        }

        if ($afterCommit) {
            $notification->getConnection()->afterCommit(fn () => $this->broadcast($notification, true));
        } else {
            $this->broadcast($notification);
        }

        return $notification;
    }

    private function broadcast(AppNotification $notification, bool $afterCommit = false): void
    {
        try {
            broadcast(new NotificationCreated($notification))->toOthers();
        } catch (Throwable $exception) {
            if (! $afterCommit && ! $exception instanceof BroadcastException) {
                throw $exception;
            }
            // Reverb unavailable: notification is persisted, realtime delivery skipped.
            report($exception);
        }
    }
}
