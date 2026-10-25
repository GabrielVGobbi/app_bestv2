<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use App\Supports\AppSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class EmailService implements EmailServiceInterface
{
    public function __construct(private readonly EmailProviderMessageIdResolver $messageIdResolver) {}

    public function queue(SendEmailDTO $dto): ?EmailLog
    {
        return $this->submit($dto, false);
    }

    public function sendNow(SendEmailDTO $dto): ?EmailLog
    {
        return $this->submit($dto, true);
    }

    private function submit(SendEmailDTO $dto, bool $synchronous): ?EmailLog
    {
        if (! $this->isEnabled($dto)) {
            return null;
        }

        $dto = $this->applyOverride($dto);

        $log = DB::transaction(function () use ($dto, $synchronous): ?EmailLog {
            $log = $this->findOrCreateLog($dto);

            if ($log === null) {
                return null;
            }

            $job = new ProcessEmailJob($log);
            DB::afterCommit(function () use ($job, $synchronous): void {
                if (! $synchronous || $job->delay !== null) {
                    dispatch($job->afterCommit());

                    return;
                }

                try {
                    $job->handle($this->messageIdResolver);
                } catch (Throwable $exception) {
                    $job->failed($exception);
                }
            });

            return $log;
        }, attempts: 3);

        return $log?->fresh();
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
     * The unique key arbitrates creation; only a proven pre-transport failure may be retried.
     */
    private function findOrCreateLog(SendEmailDTO $dto): ?EmailLog
    {
        $key = $this->resolveIdempotencyKey($dto);

        $attributes = [
            'to_email' => $dto->to,
            'to_name' => $dto->toName,
            'reply_to' => $dto->replyTo,
            'scheduled_at' => $dto->scheduledAt,
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'subject' => $dto->subject,
            'template' => $dto->template,
            'variables' => $dto->variables ?: null,
            'category' => $dto->category,
            'status' => EmailStatus::Queued,
            'notifiable_type' => $dto->notifiableType,
            'notifiable_id' => $dto->notifiableId,
            'mailer' => config('mail.default'),
            'dispatch_token' => (string) Str::uuid(),
            'retry_safe' => false,
        ];

        try {
            $log = EmailLog::query()->firstOrCreate(['idempotency_key' => $key], $attributes);
        } catch (UniqueConstraintViolationException $exception) {
            // A current read sees the winner even under a pre-existing REPEATABLE READ snapshot.
            $log = EmailLog::query()->where('idempotency_key', $key)->lockForUpdate()->first();

            if ($log === null) {
                throw $exception;
            }
        }

        return $log->wasRecentlyCreated ? $log : $log->queueAgain();
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
