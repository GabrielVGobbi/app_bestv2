<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Models\EmailWebhookEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class EmailWebhookEventRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: EmailWebhookEvent, 1: bool}
     */
    public function record(string $provider, Request $request, array $payload): array
    {
        $eventId = $request->header('svix-id') ?: data_get($payload, 'id');

        if ($eventId) {
            $existing = EmailWebhookEvent::query()
                ->where('provider', $provider)
                ->where('event_id', $eventId)
                ->first();

            if ($existing) {
                return [$existing, false];
            }
        }

        $eventType = data_get($payload, 'type');
        $messageId = $this->resolveMessageId($payload);
        $emailLog = $messageId ? EmailLog::query()->where('message_id', $messageId)->first() : null;

        $event = EmailWebhookEvent::create([
            'email_log_id' => $emailLog?->id,
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'event_family' => $this->eventFamily($eventType),
            'message_id' => $messageId,
            'signature_id' => $request->header('svix-id'),
            'signature_timestamp' => $request->header('svix-timestamp'),
            'headers' => $request->headers->all(),
            'payload' => $payload,
            'raw_payload' => $request->getContent(),
            'occurred_at' => $this->occurredAt($payload),
            'received_at' => now(),
        ]);

        return [$event, true];
    }

    /** @param array<string, mixed> $payload */
    private function resolveMessageId(array $payload): ?string
    {
        $messageId = data_get($payload, 'data.email_id')
            ?? data_get($payload, 'data.message_id');

        if ($messageId === null && Str::startsWith((string) data_get($payload, 'type'), 'email.')) {
            $messageId = data_get($payload, 'data.id');
        }

        return is_string($messageId) && $messageId !== '' ? $messageId : null;
    }

    private function eventFamily(mixed $eventType): ?string
    {
        if (! is_string($eventType) || $eventType === '') {
            return null;
        }

        return Str::contains($eventType, '.') ? Str::before($eventType, '.') : $eventType;
    }

    /** @param array<string, mixed> $payload */
    private function occurredAt(array $payload): ?Carbon
    {
        $timestamp = data_get($payload, 'created_at') ?? data_get($payload, 'data.created_at');

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            return Carbon::parse($timestamp);
        } catch (Throwable) {
            return null;
        }
    }
}
