<?php

declare(strict_types=1);

namespace App\Modules\Email\Jobs;

use App\Models\User;
use App\Modules\Email\Mail\DynamicMailable;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> Exponential backoff in seconds */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly EmailLog $emailLog) {}

    public function handle(EmailProviderMessageIdResolver $messageIdResolver): void
    {
        $log = $this->emailLog->fresh();

        if (! $log) {
            return;
        }

        $log->update([
            'status' => 'processing',
            'attempts' => $log->attempts + 1,
        ]);

        try {
            $mailable = new DynamicMailable(
                emailTemplate: $log->template,
                variables: $log->variables ?? [],
                replyToAddress: null,
            );

            $mailable->subject($log->subject)
                ->from($log->from_email, $log->from_name);

            $mailer = config('mail.default');
            $sentMessage = Mail::mailer($mailer)
                ->to($log->to_email, $log->to_name)
                ->send($mailable);

            $log->markAsSent($messageIdResolver->resolve($sentMessage, $mailer));
        } catch (Throwable $e) {
            $log->update(['error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $log = $this->emailLog->fresh();

        if ($log) {
            $log->markAsFailed($exception->getMessage());
        }

        $this->notifyAdmins($exception);
    }

    private function notifyAdmins(Throwable $exception): void
    {
        try {
            $admins = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->get();

            if ($admins->isEmpty()) {
                $admins = User::query()->limit(1)->get();
            }

            $log = $this->emailLog;
            $action = app(CreateNotificationAction::class);

            foreach ($admins as $admin) {
                $action->handle(CreateNotificationDTO::system(
                    recipientId: $admin->id,
                    title: 'Falha crítica de e-mail',
                    body: "Email para [{$log->to_email}] falhou após {$this->tries} tentativas. Erro: {$exception->getMessage()}",
                    category: NotificationCategory::SYSTEM,
                    link: '/email-logs',
                ));
            }
        } catch (Throwable) {
            // Notification failure must not mask the original email failure.
        }
    }
}
