<?php

declare(strict_types=1);

namespace App\Modules\Email\Webhooks;

use App\Modules\Email\Contracts\WebhookHandlerInterface;
use App\Modules\Email\Services\EmailWebhookEventRecorder;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        if (! is_string($secret) || $secret === '') {
            return false;
        }
        $encodedSecret = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $secretBytes = base64_decode($encodedSecret, true);
        if ($secretBytes === false || $secretBytes === '' || base64_encode($secretBytes) !== $encodedSecret) {
            return false;
        }

        $svixId = $request->header('svix-id');
        $svixTimestamp = $request->header('svix-timestamp');
        $svixSignature = $request->header('svix-signature');

        if (! is_string($svixId) || $svixId === '' || ! is_string($svixTimestamp) || ! is_string($svixSignature) || $svixSignature === ''
            || preg_match('/\A[1-9][0-9]*\z/', $svixTimestamp) !== 1) {
            return false;
        }
        $timestamp = filter_var($svixTimestamp, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $now = now()->timestamp;
        if ($timestamp === false || $timestamp < $now - 300 || $timestamp > $now + 300) {
            return false;
        }

        $signedContent = "{$svixId}.{$svixTimestamp}.{$request->getContent()}";
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
        $raw = $request->getContent();
        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpException(401, 'Invalid webhook payload.');
        }
        if (! is_array($payload) || ! str_starts_with(ltrim($raw), '{')) {
            throw new HttpException(401, 'Invalid webhook payload.');
        }

        [$event] = $this->eventRecorder->record('resend', $request, $payload);
        $this->eventRecorder->process($event);
    }
}
