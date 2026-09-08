<?php

declare(strict_types=1);

namespace App\Modules\Email\Webhooks;

use App\Modules\Email\Contracts\WebhookHandlerInterface;
use App\Modules\Email\Models\EmailLog;
use Illuminate\Http\Request;

/**
 * Handles Postmark delivery webhooks.
 *
 * Set POSTMARK_WEBHOOK_TOKEN in .env and configure the token
 * in your Postmark server settings under Webhooks.
 *
 * @see https://postmarkapp.com/developer/webhooks/webhooks-overview
 */
class PostmarkWebhookHandler implements WebhookHandlerInterface
{
    public function verifySignature(Request $request): bool
    {
        $token = config('services.postmark.webhook_token');

        if (! is_string($token) || $token === '') {
            return false;
        }

        $providedToken = $request->header('X-Postmark-Webhook-Token');

        return is_string($providedToken) && hash_equals($token, $providedToken);
    }

    public function handle(Request $request): void
    {
        $payload = $request->json()->all();
        $recordType = $payload['RecordType'] ?? null;
        $messageId = $payload['MessageID'] ?? null;

        if (! $messageId) {
            return;
        }

        $log = EmailLog::where('message_id', $messageId)->first();

        if (! $log) {
            return;
        }

        match ($recordType) {
            'Delivery' => $log->markAsDelivered($payload),
            'Bounce' => $log->markAsBounced($payload),
            'SpamComplaint' => $log->markAsBounced($payload),
            default => null,
        };
    }
}
