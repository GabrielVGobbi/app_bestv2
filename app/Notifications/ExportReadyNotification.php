<?php

namespace App\Notifications;

use App\Models\Export;
use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;
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
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'export_uuid' => $this->export->uuid,
            'file_name' => $this->export->file_name,
            'format' => $this->export->format,
            'total_records' => $this->export->total_records,
        ];
    }

    public function toMail(object $notifiable): void
    {
        app(EmailServiceInterface::class)->queue(new SendEmailDTO(
            to: $notifiable->email,
            subject: 'Sua exportação está pronta para download',
            template: 'emails.exports.ready',
            category: EmailCategory::Export,
            variables: [
                'userName' => $notifiable->name,
                'downloadUrl' => route('exports.download', $this->export->uuid),
                'expiresAt' => now()->addHours(
                    (int) config('exports.expires_after_hours', 1)
                )->format('d/m/Y \à\s H:i'),
            ],
            toName: $notifiable->name,
            notifiableType: Export::class,
            notifiableId: $this->export->id,
        ));
    }
}
