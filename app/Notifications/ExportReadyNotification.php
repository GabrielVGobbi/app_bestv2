<?php

namespace App\Notifications;

use App\Models\Export;
use App\Models\User;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Notifications\Channels\ExportReadyChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Export $export) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [ExportReadyChannel::class];
    }

    public function toAppNotification(User $notifiable): CreateNotificationDTO
    {
        return new CreateNotificationDTO(
            recipientId: $notifiable->id,
            type: NotificationType::SUCCESS,
            category: NotificationCategory::EXPORT,
            title: 'Exportação concluída',
            body: 'Sua exportação está pronta para download.',
            link: route('exports.download', $this->export->uuid),
            notifiableType: Export::class,
            notifiableId: $this->export->id,
            data: [
                'export_uuid' => $this->export->uuid,
                'file_name' => $this->export->file_name,
                'format' => $this->export->format,
                'total_records' => $this->export->total_records,
            ],
        );
    }
}
