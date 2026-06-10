<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Mail\DynamicMailable;
use App\Modules\Email\Models\EmailLog;
use App\Supports\AppSettings;
use Illuminate\Support\Facades\Mail;

class EmailService implements EmailServiceInterface
{
    public function __construct(private readonly EmailProviderMessageIdResolver $messageIdResolver) {}

    public function queue(SendEmailDTO $dto): ?EmailLog
    {
        if (! $this->isEnabled($dto)) {
            return null;
        }

        $dto = $this->applyOverride($dto);

        $log = $this->findOrCreateLog($dto);

        if ($log === null) {
            return null; // duplicate detected
        }

        ProcessEmailJob::dispatch($log);

        return $log;
    }

    public function sendNow(SendEmailDTO $dto): ?EmailLog
    {
        if (! $this->isEnabled($dto)) {
            return null;
        }

        $dto = $this->applyOverride($dto);

        $log = $this->createLog($dto);

        $log->update(['status' => EmailStatus::Processing, 'attempts' => 1]);

        try {
            $mailable = (new DynamicMailable(
                emailTemplate: $dto->template,
                variables: $dto->variables,
                replyToAddress: $dto->replyTo,
            ))->subject($dto->subject)
                ->from(config('mail.from.address'), config('mail.from.name'));

            $mailer = config('mail.default');
            $sentMessage = Mail::mailer($mailer)
                ->to($dto->to, $dto->toName)
                ->send($mailable);

            $log->markAsSent($this->messageIdResolver->resolve($sentMessage, $mailer));
        } catch (\Throwable $e) {
            $log->markAsFailed($e->getMessage());
        }

        return $log;
    }

    private function isEnabled(SendEmailDTO $dto): bool
    {
        $globalDefault = (bool) config('email.defaults.enabled', true);
        if (! AppSettings::get('email', 'enabled', $globalDefault)) {
            return false;
        }

        $moduleKey = $dto->category->value.'_enabled';
        $moduleDefault = (bool) config("email.defaults.{$moduleKey}", true);

        return (bool) AppSettings::get('email', $moduleKey, $moduleDefault);
    }

    private function applyOverride(SendEmailDTO $dto): SendEmailDTO
    {
        $override = config('email.to_override');

        if ($override) {
            return $dto->withTo($override);
        }

        return $dto;
    }

    /**
     * Returns null if a non-failed email with the same idempotency key already exists.
     * Resets a failed log with the same key back to queued for retry.
     */
    private function findOrCreateLog(SendEmailDTO $dto): ?EmailLog
    {
        $key = $this->resolveIdempotencyKey($dto);

        $existing = EmailLog::where('idempotency_key', $key)->first();

        if (! $existing) {
            return $this->createLog($dto, $key);
        }

        // Already delivered or in-flight — skip duplicate send
        if (in_array($existing->status->value, [
            EmailStatus::Queued->value,
            EmailStatus::Processing->value,
            EmailStatus::Sent->value,
            EmailStatus::Delivered->value,
        ], true)) {
            return null;
        }

        // Failed log with same key: reset and retry
        $existing->update([
            'status' => EmailStatus::Queued,
            'error_message' => null,
            'failed_at' => null,
        ]);

        return $existing->fresh();
    }

    private function createLog(SendEmailDTO $dto, ?string $idempotencyKey = null): EmailLog
    {
        return EmailLog::create([
            'to_email' => $dto->to,
            'to_name' => $dto->toName,
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'subject' => $dto->subject,
            'template' => $dto->template,
            'variables' => $dto->variables ?: null,
            'category' => $dto->category,
            'status' => EmailStatus::Queued,
            'notifiable_type' => $dto->notifiableType,
            'notifiable_id' => $dto->notifiableId,
            'idempotency_key' => $idempotencyKey ?? $this->resolveIdempotencyKey($dto),
            'mailer' => config('mail.default'),
        ]);
    }

    private function resolveIdempotencyKey(SendEmailDTO $dto): string
    {
        if ($dto->idempotencyKey) {
            return $dto->idempotencyKey;
        }

        return hash('sha256', implode('|', [
            $dto->to,
            $dto->template,
            $dto->notifiableId ?? '',
            now()->toDateString(),
        ]));
    }
}
