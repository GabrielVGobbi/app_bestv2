<?php

declare(strict_types=1);

namespace App\Modules\Email\Webhooks;

use App\Modules\Email\Contracts\WebhookHandlerInterface;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Services\EmailWebhookEventRecorder;
use Illuminate\Http\Request;

/**
 * Handles Resend delivery webhooks.
 *
 * Resend sends a SVIX-Signature header. To verify signatures,
 * set RESEND_WEBHOOK_SECRET in your .env file.
 *
 * @see https://resend.com/docs/dashboard/webhooks/introduction
 */
class ResendWebhookHandler implements WebhookHandlerInterface
{
    public function __construct(private readonly EmailWebhookEventRecorder $eventRecorder) {}

    public function verifySignature(Request $request): bool
    {
        $secret = config('services.resend.webhook_secret');

        if (! $secret) {
            return true; // Signature verification skipped (not configured)
        }

        $svixId = $request->header('svix-id');
        $svixTimestamp = $request->header('svix-timestamp');
        $svixSignature = $request->header('svix-signature');

        if (! $svixId || ! $svixTimestamp || ! $svixSignature) {
            return false;
        }

        $signedContent = "{$svixId}.{$svixTimestamp}.{$request->getContent()}";
        $secretBytes = base64_decode(str_replace('whsec_', '', $secret));
        $computedSignature = 'v1,'.base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));

        foreach (explode(' ', $svixSignature) as $sig) {
            if (hash_equals($sig, $computedSignature)) {
                return true;
            }
        }

        return false;
    }

    public function handle(Request $request): void
    {
        $payload = $request->json()->all();

        [$event, $created] = $this->eventRecorder->record('resend', $request, $payload);

        if (! $created) {
            return;
        }

        $status = $this->statusForEvent($event->event_type);

        if ($status && $event->emailLog) {
            $event->emailLog->markFromWebhook(
                status: $status,
                metadata: $payload['data'] ?? $payload,
                errorMessage: $this->errorMessage($payload),
            );
        }

        $event->markAsProcessed();
    }

    private function statusForEvent(?string $eventType): ?EmailStatus
    {
        return match ($eventType) {
            'email.sent' => EmailStatus::Sent,
            'email.delivered' => EmailStatus::Delivered,
            'email.delivery_delayed' => EmailStatus::DeliveryDelayed,
            'email.failed' => EmailStatus::Failed,
            'email.bounced' => EmailStatus::Bounced,
            'email.complained' => EmailStatus::Complained,
            'email.opened' => EmailStatus::Opened,
            'email.clicked' => EmailStatus::Clicked,
            'email.received' => EmailStatus::Received,
            'email.scheduled' => EmailStatus::Scheduled,
            'email.suppressed' => EmailStatus::Suppressed,
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function errorMessage(array $payload): ?string
    {
        foreach ([
            'data.error.message',
            'data.error',
            'data.bounce.message',
            'data.reason',
            'data.message',
            'data.suppressed.reason',
        ] as $key) {
            $value = data_get($payload, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
