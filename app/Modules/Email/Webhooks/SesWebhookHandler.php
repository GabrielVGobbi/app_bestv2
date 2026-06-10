<?php

declare(strict_types=1);

namespace App\Modules\Email\Webhooks;

use App\Modules\Email\Contracts\WebhookHandlerInterface;
use App\Modules\Email\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Handles Amazon SES delivery notifications via SNS.
 *
 * SES → SNS → HTTP endpoint.
 * The notification type is in the 'Type' field of the SNS message.
 *
 * @see https://docs.aws.amazon.com/ses/latest/dg/event-publishing-retrieving-sns.html
 */
class SesWebhookHandler implements WebhookHandlerInterface
{
    public function verifySignature(Request $request): bool
    {
        $payload = $request->json()->all();
        $type = $payload['Type'] ?? null;

        // Handle SNS subscription confirmation
        if ($type === 'SubscriptionConfirmation') {
            $url = $payload['SubscribeURL'] ?? null;
            if ($url) {
                Http::get($url); // Auto-confirm subscription
            }

            return true;
        }

        // Basic verification: check for required SNS fields
        return isset($payload['Type'], $payload['TopicArn'], $payload['Message']);
    }

    public function handle(Request $request): void
    {
        $payload = $request->json()->all();

        if (($payload['Type'] ?? null) !== 'Notification') {
            return;
        }

        $message = json_decode($payload['Message'] ?? '{}', true);
        $eventType = $message['eventType'] ?? $message['notificationType'] ?? null;
        $messageId = $message['mail']['messageId'] ?? null;

        if (! $messageId) {
            return;
        }

        $log = EmailLog::where('message_id', $messageId)->first();

        if (! $log) {
            return;
        }

        match ($eventType) {
            'Delivery' => $log->markAsDelivered($message),
            'Bounce' => $log->markAsBounced($message),
            'Complaint' => $log->markAsBounced($message),
            default => null,
        };
    }
}
