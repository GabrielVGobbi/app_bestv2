<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Models\EmailWebhookEvent;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EmailWebhookEventRecorder
{
    public const AUTHENTICATED_REQUEST = 'email.webhook.authenticated_request';

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: EmailWebhookEvent, 1: bool}
     */
    public function record(string $provider, Request $request, array $payload): array
    {
        $raw = $request->getContent();
        $hash = hash('sha256', $raw);
        $proof = $request->attributes->get(self::AUTHENTICATED_REQUEST);
        if ($provider !== 'resend' || ! is_array($proof) || ($proof['provider'] ?? null) !== $provider || ($proof['hash'] ?? null) !== $hash) {
            throw new HttpException(401, 'Invalid webhook authentication context.');
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! str_starts_with(ltrim($raw), '{') || $decoded !== $payload) {
            throw new HttpException(401, 'Invalid webhook payload.');
        }

        $eventId = $request->header('svix-id') ?? ($payload['id'] ?? null);
        $eventId = $this->identifier($eventId) ?? 'body:'.$hash;
        $type = $this->identifier($payload['type'] ?? null);
        $messageId = $this->messageId($payload);
        $candidates = $messageId === null ? collect() : $this->candidates($messageId);
        $log = $candidates->count() === 1 && $this->compatible($candidates->first()) ? $candidates->first() : null;
        $attributes = [
            'email_log_id' => $log?->id,
            'dispatch_token' => $log?->dispatch_token,
            'linked_at' => $log === null ? null : now(),
            'event_type' => $type,
            'event_family' => $type === null ? null : Str::before($type, '.'),
            'message_id' => $messageId,
            'signature_id' => $eventId,
            'signature_timestamp' => $request->header('svix-timestamp'),
            'headers' => array_intersect_key($request->headers->all(), array_flip(['svix-id', 'svix-timestamp'])),
            'payload' => $payload,
            'payload_hash' => $hash,
            'raw_payload' => $raw,
            'authenticated_at' => now(),
            'occurred_at' => $this->occurredAt($payload),
            'received_at' => now(),
        ];

        return DB::transaction(function () use ($provider, $eventId, $attributes, $raw, $hash): array {
            try {
                $event = EmailWebhookEvent::firstOrCreate(['provider' => $provider, 'event_id' => $eventId], $attributes);
            } catch (UniqueConstraintViolationException $exception) {
                $event = EmailWebhookEvent::where('provider', $provider)->where('event_id', $eventId)->lockForUpdate()->first();
                if ($event === null) {
                    throw $exception;
                }
            }
            if ($event->wasRecentlyCreated) {
                return [$event, true];
            }
            $event = EmailWebhookEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->provider !== $provider || $event->event_id !== $eventId || $event->raw_payload !== $raw || ($event->payload_hash !== null && $event->payload_hash !== $hash)) {
                throw new HttpException(409, 'Conflicting webhook event.');
            }
            if ($event->authenticated_at === null || $event->payload_hash === null) {
                unset($attributes['received_at']);
                $event->fill($attributes)->save();
            }

            return [$event, false];
        }, attempts: 3);
    }

    public function process(EmailWebhookEvent $source): bool
    {
        return DB::transaction(function () use ($source): bool {
            $event = EmailWebhookEvent::whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            if ($event->processed_at !== null) {
                return true;
            }
            if ($event->provider !== 'resend' || $event->authenticated_at === null || ! is_string($event->raw_payload)
                || ! is_string($event->payload_hash) || ! hash_equals($event->payload_hash, hash('sha256', $event->raw_payload))) {
                return $this->pending($event, 'untrusted');
            }

            $payload = json_decode($event->raw_payload, true);
            if (! is_array($payload) || ! str_starts_with(ltrim($event->raw_payload), '{')) {
                return $this->pending($event, 'quarantined');
            }
            $status = $this->status($payload['type'] ?? null);
            if ($status === null) {
                return $this->complete($event, 'unsupported');
            }
            $messageId = $this->messageId($payload);
            if ($messageId === null) {
                return $this->pending($event, 'unmatched');
            }

            if ($event->linked_at !== null) {
                $log = EmailLog::whereKey($event->email_log_id)->lockForUpdate()->first();
                if ($log === null || $event->dispatch_token !== $log->dispatch_token || $log->message_id !== $messageId) {
                    return $this->complete($event, 'obsolete_generation');
                }
            } else {
                $logs = $this->candidates($messageId, true);
                if ($logs->count() !== 1) {
                    return $this->pending($event, $logs->isEmpty() ? 'unmatched' : 'ambiguous');
                }
                $log = $logs->first();
            }
            if (! $this->compatible($log)) {
                return $this->pending($event, 'provider_mismatch');
            }
            if ($event->linked_at !== null && ($event->email_log_id !== $log->id || $event->dispatch_token !== $log->dispatch_token)) {
                return $this->complete($event, 'obsolete_generation');
            }
            if ($event->linked_at === null) {
                $event->email_log_id = $log->id;
                $event->dispatch_token = $log->dispatch_token;
                $event->linked_at = now();
            }

            $timestamp = $payload['created_at'] ?? data_get($payload, 'data.created_at');
            $occurredAt = $this->occurredAt($payload);
            if ($timestamp !== null && $occurredAt === null) {
                return $this->complete($event, 'invalid_timestamp');
            }
            if ($occurredAt !== null && $occurredAt->greaterThan(CarbonImmutable::now('UTC')->addMinutes(5))) {
                return $this->pending($event, 'future_timestamp');
            }
            $cursor = $log->getRawOriginal('webhook_occurred_at');
            $cursor = $cursor === null ? null : CarbonImmutable::parse($cursor, 'UTC');
            $strong = in_array($status, [EmailStatus::Delivered, EmailStatus::Opened, EmailStatus::Clicked, EmailStatus::Suppressed, EmailStatus::Complained], true);
            if ($cursor !== null && ! $strong && ($occurredAt === null || $occurredAt->lessThan($cursor))) {
                return $this->complete($event, $occurredAt === null ? 'unordered' : 'stale');
            }

            $changed = $log->markFromWebhook($status, is_array($payload['data'] ?? null) ? $payload['data'] : $payload, $this->errorMessage($payload));
            if ($occurredAt !== null && ($cursor === null || $occurredAt->greaterThan($cursor))) {
                $log->getConnection()->table($log->getTable())->where('id', $log->id)->where('dispatch_token', $log->dispatch_token)
                    ->update(['webhook_occurred_at' => $occurredAt->format('Y-m-d H:i:s.u')]);
            }

            return $this->complete($event, $changed ? 'applied' : 'state_preserved');
        }, attempts: 3);
    }

    private function pending(EmailWebhookEvent $event, string $result): bool
    {
        $event->processing_result = $result;
        $event->save();

        return false;
    }

    private function complete(EmailWebhookEvent $event, string $result): bool
    {
        $event->processing_result = $result;
        $event->processed_at = now();
        $event->save();

        return true;
    }

    private function compatible(EmailLog $log): bool
    {
        $driver = config('mail.mailers.'.$log->mailer.'.transport', $log->mailer);

        return in_array($driver, ['resend', 'smtp', 'log', 'array'], true);
    }

    /** @return Collection<int, EmailLog> */
    private function candidates(string $messageId, bool $lock = false): Collection
    {
        $query = EmailLog::query()->where('message_id', $messageId);
        if ($query->getConnection()->getDriverName() === 'mysql') {
            $query->whereRaw('BINARY message_id = ?', [$messageId]);
        }
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->orderBy('id')->limit(2)->get()->filter(fn (EmailLog $log): bool => $log->message_id === $messageId)->values();
    }

    private function identifier(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || $value === '' || strlen($value) > 255 || preg_match('//u', $value) !== 1 || preg_match('/[\\x00-\\x1f\\x7f]/', $value)) {
            throw new HttpException(400, 'Invalid webhook event fields.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function messageId(array $payload): ?string
    {
        $id = data_get($payload, 'data.email_id') ?? data_get($payload, 'data.message_id');
        if ($id === null && is_string($payload['type'] ?? null) && str_starts_with($payload['type'], 'email.')) {
            $id = data_get($payload, 'data.id');
        }

        return $this->identifier($id);
    }

    /** @param array<string, mixed> $payload */
    private function occurredAt(array $payload): ?CarbonImmutable
    {
        $value = $payload['created_at'] ?? data_get($payload, 'data.created_at');
        if (! is_string($value) || ! preg_match('/\\A(\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2})(?:\\.(\\d{1,6}))?(Z|[+-](?:[01]\\d|2[0-3]):[0-5]\\d)\\z/', $value, $match)) {
            return null;
        }
        $normalized = $match[1].'.'.str_pad($match[2] ?? '', 6, '0').($match[3] === 'Z' ? '+00:00' : $match[3]);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s.uP', $normalized, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\\TH:i:s.uP') !== $normalized) {
            return null;
        }

        $utc = CarbonImmutable::instance($date)->utc();

        return $utc->year >= 1000 && $utc->year <= 9999 ? $utc : null;
    }

    private function status(mixed $type): ?EmailStatus
    {
        if (! is_string($type) || ! str_starts_with($type, 'email.')) {
            return null;
        }
        $status = EmailStatus::tryFrom(substr($type, 6));

        return in_array($status, [null, EmailStatus::Queued, EmailStatus::Processing], true) ? null : $status;
    }

    /** @param array<string, mixed> $payload */
    private function errorMessage(array $payload): ?string
    {
        foreach (['data.error.message', 'data.error', 'data.bounce.message', 'data.reason', 'data.message', 'data.suppressed.reason'] as $key) {
            $value = data_get($payload, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
