<?php

declare(strict_types=1);

namespace App\Modules\Email\Jobs;

use App\Models\User;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Mail\DynamicMailable;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use App\Modules\Notifications\Actions\CreateNotificationAction;
use App\Modules\Notifications\DTOs\CreateNotificationDTO;
use App\Modules\Notifications\Enums\NotificationCategory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class ProcessEmailJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var array<int, int> Exponential backoff in seconds */
    public array $backoff = [30, 120, 300];

    public ?string $dispatchToken = null;

    public function __construct(public readonly EmailLog $emailLog)
    {
        $this->dispatchToken = $emailLog->dispatch_token;

        if ($emailLog->scheduled_at?->isFuture()) {
            $driver = config('queue.connections.'.config('queue.default').'.driver');

            if (! in_array($driver, ['database', 'redis', 'beanstalkd'], true)) {
                throw new InvalidArgumentException('Scheduled email requires a durable database, redis or beanstalkd queue with delayed delivery support.');
            }

            $this->delay($emailLog->scheduled_at);
        }
    }

    public function handle(EmailProviderMessageIdResolver $messageIdResolver): void
    {
        if ($this->dispatchToken === null) {
            return;
        }

        $query = EmailLog::query()->whereKey($this->emailLog->getKey())->where('dispatch_token', $this->dispatchToken);
        $pending = (clone $query)->where('status', EmailStatus::Queued)->whereNull('sent_at')->whereNull('message_id');
        $scheduledAt = (clone $pending)->value('scheduled_at');

        if ($scheduledAt?->isFuture()) {
            $this->release(max(1, (int) ceil(now()->diffInSeconds($scheduledAt))));

            return;
        }

        $claimed = $pending->where(fn ($due) => $due->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()->utc()->format('Y-m-d H:i:s.u')))->update([
            'status' => EmailStatus::Processing,
            'attempts' => DB::raw('attempts + 1'),
            'retry_safe' => false,
        ]);

        if ($claimed !== 1) {
            return;
        }

        $log = $query->firstOrFail();
        $enteredMailer = false;
        try {
            $mailable = new DynamicMailable(
                emailTemplate: $log->template,
                variables: $log->variables ?? [],
                replyToAddress: $log->reply_to,
            );

            $mailable->subject($log->subject)
                ->from($log->from_email, $log->from_name);

            $mailable->render();

            if (! (clone $query)->where('status', EmailStatus::Processing)->whereNull('sent_at')->whereNull('message_id')->exists()) {
                return;
            }

            $mailer = $log->mailer;

            if (! is_string($mailer) || trim($mailer) === '' || ! is_array(config("mail.mailers.{$mailer}"))) {
                throw new InvalidArgumentException('A configured persisted mailer is required; reconcile the delivery configuration before retrying.');
            }

            $enteredMailer = true;
            $sentMessage = Mail::mailer($mailer)
                ->to($log->to_email, $log->to_name)
                ->send($mailable);

            $log->markAsSent($messageIdResolver->resolve($sentMessage, $mailer));
        } catch (Throwable $e) {
            (clone $query)->where('status', EmailStatus::Processing)->update([
                'status' => $enteredMailer ? EmailStatus::Processing : EmailStatus::Queued,
                'error_message' => $e->getMessage(),
                'retry_safe' => false,
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($this->dispatchToken === null) {
            return;
        }

        $log = EmailLog::query()->whereKey($this->emailLog->getKey())->where('dispatch_token', $this->dispatchToken)->first();

        if ($log !== null && $log->markAsFailed($exception->getMessage())) {
            $this->notifyOperators($log);
        }
    }

    private function notifyOperators(EmailLog $log): void
    {
        try {
            $action = app(CreateNotificationAction::class);

            foreach (User::query()->cursor() as $operator) {
                if (! Gate::forUser($operator)->allows('view-email-logs', EmailLog::class)) {
                    continue;
                }

                $action->handle(CreateNotificationDTO::system(
                    recipientId: $operator->id,
                    title: 'Falha crítica de e-mail',
                    body: "O envio {$log->uuid} falhou antes da entrega. Consulte o registro operacional.",
                    category: NotificationCategory::SYSTEM,
                    link: route('email-logs.index', absolute: false),
                ));
            }
        } catch (Throwable) {
            // Notification failure must not mask the original email failure.
        }
    }
}
