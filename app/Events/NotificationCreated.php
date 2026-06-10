<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast em tempo real quando uma nova notificação é criada.
 *
 * Canal: private-user.{recipientId}.notifications
 * Frontend escuta via: Echo.private(`user.${userId}.notifications`).listen('.notification.created', ...)
 *
 * Usa ShouldBroadcastNow para enviar imediatamente, sem depender de queue worker.
 * Troque por ShouldBroadcast se quiser passar pela fila em produção.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AppNotification $notification,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->notification->recipient_id}.notifications"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Payload enviado ao frontend.
     * Espelha o shape do NotificationResource para consistência.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $notification = $this->notification;
        $sender = $notification->relationLoaded('sender') ? $notification->sender : null;

        return [
            'notification' => [
                'id' => $notification->id,
                'uuid' => $notification->uuid,
                'type' => $notification->type?->value,
                'type_label' => $notification->type?->label(),
                'category' => $notification->category?->value,
                'category_label' => $notification->category?->label(),
                'title' => $notification->title,
                'body' => $notification->body,
                'link' => $notification->link,
                'actions' => $notification->actions,
                'action_status' => $notification->action_status,
                'is_read' => false,
                'read_at' => null,
                'created_at' => $notification->created_at->toISOString(),
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'uuid' => $sender->uuid,
                ] : null,
                'data' => null,
            ],
        ];
    }
}
