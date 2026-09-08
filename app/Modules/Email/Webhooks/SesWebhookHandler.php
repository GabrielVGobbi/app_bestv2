<?php

declare(strict_types=1);

namespace App\Modules\Email\Webhooks;

use App\Modules\Email\Contracts\WebhookHandlerInterface;
use App\Modules\Email\Models\EmailLog;
use Illuminate\Http\Client\ConnectionException;
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
        $topicArn = config('services.ses.webhook_topic_arn');

        if (! is_string($topicArn) || $topicArn === '' || ($payload['TopicArn'] ?? null) !== $topicArn) {
            return false;
        }

        $endpoint = $this->snsEndpoint($topicArn);
        $certificateUrl = $payload['SigningCertURL'] ?? null;

        if ($endpoint === null || ! is_string($certificateUrl)
            || ! preg_match('~^'.preg_quote($endpoint, '~').'/SimpleNotificationService-[a-zA-Z0-9]+\.pem$~D', $certificateUrl)) {
            return false;
        }

        $fields = match ($type) {
            'Notification' => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
            default => [],
        };
        $algorithm = match ($payload['SignatureVersion'] ?? null) {
            '1' => OPENSSL_ALGO_SHA1,
            '2' => OPENSSL_ALGO_SHA256,
            default => null,
        };

        if ($fields === [] || $algorithm === null || ! is_string($payload['Signature'] ?? null)) {
            return false;
        }

        $signature = base64_decode($payload['Signature'], true);

        if ($signature === false || $signature === '') {
            return false;
        }

        $signedContent = '';

        foreach ($fields as $field) {
            if ($field === 'Subject' && ! array_key_exists($field, $payload)) {
                continue;
            }

            if (! is_string($payload[$field] ?? null)) {
                return false;
            }

            $signedContent .= $field."\n".$payload[$field]."\n";
        }

        try {
            $response = Http::withoutRedirecting()->connectTimeout(3)->timeout(5)->get($certificateUrl);
        } catch (ConnectionException) {
            abort(503, 'SNS signing certificate temporarily unavailable.');
        }

        abort_if($response->serverError() || $response->status() === 429, 503, 'SNS signing certificate temporarily unavailable.');

        if (! $response->successful()) {
            return false;
        }

        $certificate = @openssl_x509_read($response->body());
        $publicKey = $certificate === false ? false : openssl_pkey_get_public($certificate);

        return $publicKey !== false && openssl_verify($signedContent, $signature, $publicKey, $algorithm) === 1;
    }

    private function snsEndpoint(string $topicArn): ?string
    {
        if (! preg_match('/^arn:(aws|aws-cn|aws-us-gov):sns:([a-z0-9-]+):[0-9]{12}:[a-zA-Z0-9_-]+(?:\.fifo)?$/D', $topicArn, $matches)) {
            return null;
        }

        $domain = $matches[1] === 'aws-cn' ? 'amazonaws.com.cn' : 'amazonaws.com';

        return 'https://sns.'.$matches[2].'.'.$domain;
    }

    public function handle(Request $request): void
    {
        $payload = $request->json()->all();

        if (($payload['Type'] ?? null) === 'SubscriptionConfirmation') {
            $endpoint = $this->snsEndpoint($payload['TopicArn']);

            if ($endpoint !== null) {
                $confirmation = Http::withoutRedirecting()->connectTimeout(3)->timeout(5)->get($endpoint, [
                    'Action' => 'ConfirmSubscription',
                    'TopicArn' => $payload['TopicArn'],
                    'Token' => $payload['Token'],
                ])->throw();

                abort_unless($confirmation->successful(), 502);
            }

            return;
        }

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
