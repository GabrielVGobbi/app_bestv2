<?php

declare(strict_types=1);

namespace App\Modules\Email\Webhooks;

use App\Modules\Email\Contracts\WebhookHandlerInterface;
use App\Modules\Email\Models\EmailLog;
use CurlHandle;
use DateTimeImmutable;
use DateTimeZone;
use ErrorException;
use GuzzleHttp\Handler\CurlHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use ValueError;

/**
 * Authenticates SES notifications using the signed SNS envelope.
 *
 * @see https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message-verify-message-signature.html
 */
class SesWebhookHandler implements WebhookHandlerInterface
{
    private const AUTHENTICATED_ENVELOPE = 'ses.authenticated_sns_envelope';

    private const MAX_ENVELOPE_BYTES = 2097152;

    private const MAX_MESSAGE_BYTES = 262144;

    private const MAX_RESPONSE_BYTES = 65536;

    public function verifySignature(Request $request): bool
    {
        $request->attributes->remove(self::AUTHENTICATED_ENVELOPE);
        $raw = $request->getContent();
        $payload = $this->decodeEnvelope($raw);
        if ($payload === null) {
            return false;
        }
        $host = $this->topicHost($payload['TopicArn']);
        if ($host === null || ! $this->certificateUrlIsValid($payload['SigningCertURL'], $host)) {
            return false;
        }
        if ($payload['Type'] === 'SubscriptionConfirmation' && ! $this->subscriptionUrlIsValid($payload, $host)) {
            return false;
        }
        if (! $this->runtimeAvailable()) {
            $this->unavailable();
        }

        $certificate = $this->fetch($payload['SigningCertURL'], $host);
        if (! $this->signatureIsValid($payload, $certificate)) {
            return false;
        }
        $request->attributes->set(self::AUTHENTICATED_ENVELOPE, [
            'handler' => $this,
            'raw_hash' => hash('sha256', $raw),
            'payload' => $payload,
        ]);

        return true;
    }

    public function handle(Request $request): void
    {
        $proof = $request->attributes->get(self::AUTHENTICATED_ENVELOPE);
        $raw = $request->getContent();
        if (! is_array($proof) || ($proof['handler'] ?? null) !== $this || ! is_string($proof['raw_hash'] ?? null)
            || ! is_array($proof['payload'] ?? null) || strlen($raw) > self::MAX_ENVELOPE_BYTES
            || ! hash_equals($proof['raw_hash'], hash('sha256', $raw))) {
            throw new HttpException(401, 'Invalid SNS authentication.');
        }
        $payload = $proof['payload'];
        $host = $this->topicHost($payload['TopicArn'] ?? '');
        if ($host === null) {
            throw new HttpException(401, 'Invalid SNS authentication.');
        }
        if ($payload['Type'] === 'SubscriptionConfirmation') {
            $query = http_build_query([
                'Action' => 'ConfirmSubscription',
                'TopicArn' => $payload['TopicArn'],
                'Token' => $payload['Token'],
                'Version' => '2010-03-31',
            ], '', '&', PHP_QUERY_RFC3986);
            $this->fetch('https://'.$host.'/?'.$query, $host);

            return;
        }

        try {
            $message = json_decode($payload['Message'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }
        if (! is_array($message) || ! is_array($message['mail'] ?? null)) {
            return;
        }
        $eventType = $message['eventType'] ?? $message['notificationType'] ?? null;
        $messageId = $message['mail']['messageId'] ?? null;
        if (! is_string($messageId) || $messageId === '' || ! in_array($eventType, ['Delivery', 'Bounce', 'Complaint'], true)) {
            return;
        }

        $log = EmailLog::where('message_id', $messageId)->first();
        if ($log === null) {
            return;
        }
        match ($eventType) {
            'Delivery' => $log->markAsDelivered($message),
            'Bounce', 'Complaint' => $log->markAsBounced($message),
        };
    }

    /** @return array<string, string>|null */
    private function decodeEnvelope(string $raw): ?array
    {
        if ($raw === '' || strlen($raw) > self::MAX_ENVELOPE_BYTES) {
            return null;
        }
        try {
            $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (! is_array($payload) || array_is_list($payload)) {
            return null;
        }
        foreach (['Type', 'MessageId', 'TopicArn', 'Message', 'Timestamp', 'SignatureVersion', 'SigningCertURL', 'Signature'] as $field) {
            if (! is_string($payload[$field] ?? null) || $payload[$field] === '') {
                return null;
            }
        }
        if (! in_array($payload['Type'], ['Notification', 'SubscriptionConfirmation'], true)
            || ! in_array($payload['SignatureVersion'], ['1', '2'], true)
            || strlen($payload['Message']) > self::MAX_MESSAGE_BYTES
            || ! $this->timestampIsValid($payload['Timestamp'])) {
            return null;
        }
        if (array_key_exists('Subject', $payload) && ! is_string($payload['Subject'])) {
            return null;
        }
        if ($payload['Type'] === 'SubscriptionConfirmation') {
            foreach (['SubscribeURL', 'Token'] as $field) {
                if (! is_string($payload[$field] ?? null) || $payload[$field] === '') {
                    return null;
                }
            }
        }
        $signature = base64_decode($payload['Signature'], true);
        if ($signature === false || $signature === '' || base64_encode($signature) !== $payload['Signature']) {
            return null;
        }

        $fields = ['Type', 'MessageId', 'TopicArn', 'Message', 'Timestamp', 'SignatureVersion', 'SigningCertURL', 'Signature', 'Subject', 'SubscribeURL', 'Token'];
        $envelope = [];
        foreach ($fields as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $envelope[$field] = $payload[$field];
            }
        }

        return $envelope;
    }

    private function timestampIsValid(string $timestamp): bool
    {
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?Z\z/', $timestamp) !== 1) {
            return false;
        }
        $format = str_contains($timestamp, '.') ? '!Y-m-d\TH:i:s.u\Z' : '!Y-m-d\TH:i:s\Z';
        $date = DateTimeImmutable::createFromFormat($format, $timestamp, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function topicHost(string $topic): ?string
    {
        $allowed = config('services.ses.allowed_topic_arns', []);
        if (! is_array($allowed) || ! in_array($topic, $allowed, true)
            || preg_match('/\Aarn:aws:sns:([a-z]{2}-[a-z]+-[0-9]+):[0-9]{12}:([A-Za-z0-9_-]+(?:\.fifo)?)\z/', $topic, $match) !== 1
            || strlen($match[2]) > 256) {
            return null;
        }

        return 'sns.'.$match[1].'.amazonaws.com';
    }

    private function certificateUrlIsValid(string $url, string $host): bool
    {
        return preg_match('/\Ahttps:\/\/'.preg_quote($host, '/').'\/SimpleNotificationService-[a-f0-9]+\.pem\z/', $url) === 1;
    }

    /** @param array<string, string> $payload */
    private function subscriptionUrlIsValid(array $payload, string $host): bool
    {
        $url = $payload['SubscribeURL'];
        $prefix = 'https://'.$host.'/?';
        if (! str_starts_with($url, $prefix) || str_contains($url, '#')) {
            return false;
        }
        $query = [];
        foreach (explode('&', substr($url, strlen($prefix))) as $pair) {
            if (! str_contains($pair, '=')) {
                return false;
            }
            [$key, $value] = explode('=', $pair, 2);
            if (preg_match('/%(?![a-fA-F0-9]{2})/', $key.$value) === 1) {
                return false;
            }
            $key = urldecode($key);
            $value = urldecode($value);
            if (! in_array($key, ['Action', 'TopicArn', 'Token', 'Version'], true) || array_key_exists($key, $query)) {
                return false;
            }
            $query[$key] = $value;
        }

        return ($query['Action'] ?? null) === 'ConfirmSubscription'
            && ($query['TopicArn'] ?? null) === $payload['TopicArn']
            && ($query['Token'] ?? null) === $payload['Token']
            && (! array_key_exists('Version', $query) || $query['Version'] === '2010-03-31');
    }

    /** @param array<string, string> $payload */
    private function signatureIsValid(array $payload, string $certificate): bool
    {
        $signature = base64_decode($payload['Signature'], true);
        if ($signature === false || $signature === '') {
            return false;
        }
        $fields = $payload['Type'] === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        $canonical = '';
        foreach ($fields as $field) {
            if (array_key_exists($field, $payload)) {
                $canonical .= $field."\n".$payload[$field]."\n";
            }
        }

        set_error_handler(static function (int $severity): never {
            throw new ErrorException('Invalid SNS signing certificate.', 0, $severity);
        });
        try {
            $x509 = openssl_x509_read($certificate);
            if ($x509 === false) {
                return false;
            }
            $details = openssl_x509_parse($x509);
            $now = time();
            if (! is_array($details) || ! is_int($details['validFrom_time_t'] ?? null) || ! is_int($details['validTo_time_t'] ?? null)
                || $details['validFrom_time_t'] > $now || $details['validTo_time_t'] < $now) {
                return false;
            }
            $key = openssl_pkey_get_public($x509);
            $keyDetails = $key === false ? false : openssl_pkey_get_details($key);
            if ($key === false || ! is_array($keyDetails) || ($keyDetails['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
                return false;
            }

            return openssl_verify($canonical, $signature, $key, $payload['SignatureVersion'] === '1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256) === 1;
        } catch (ErrorException|ValueError) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    /** @return list<string> */
    protected function resolveAddresses(string $host): array
    {
        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : $addresses;
    }

    private function publicAddress(string $host): string
    {
        try {
            $addresses = $this->resolveAddresses($host);
        } catch (Throwable) {
            $this->unavailable();
        }
        if ($addresses === []) {
            $this->unavailable();
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE) === false
                || preg_match('/\A(?:22[4-9]|23[0-9])\./', $address) === 1) {
                throw new HttpException(401, 'Invalid SNS destination.');
            }
        }

        return $addresses[0];
    }

    private function runtimeAvailable(): bool
    {
        $internalFunctions = get_defined_functions()['internal'];
        foreach (['curl_exec', 'openssl_x509_read', 'openssl_x509_parse', 'openssl_pkey_get_public', 'openssl_pkey_get_details', 'openssl_verify'] as $function) {
            if (! in_array($function, $internalFunctions, true)) {
                return false;
            }
        }
        foreach (['CURLOPT_RESOLVE', 'CURLOPT_PROXY', 'CURLOPT_NOPROXY', 'CURLOPT_IPRESOLVE', 'CURL_IPRESOLVE_V4', 'CURLOPT_FRESH_CONNECT', 'CURLOPT_FORBID_REUSE', 'CURLOPT_NOPROGRESS', 'CURLOPT_PROGRESSFUNCTION', 'FILTER_FLAG_GLOBAL_RANGE', 'OPENSSL_KEYTYPE_RSA', 'OPENSSL_ALGO_SHA1', 'OPENSSL_ALGO_SHA256'] as $constant) {
            if (! defined($constant)) {
                return false;
            }
        }

        return extension_loaded('openssl') && extension_loaded('curl');
    }

    private function fetch(string $url, string $host): string
    {
        if (! $this->runtimeAvailable()) {
            $this->unavailable();
        }
        $address = $this->publicAddress($host);
        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withoutRedirecting()
                ->setHandler(new CurlHandler)
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->withOptions([
                    'verify' => true,
                    'proxy' => '',
                    'stream' => false,
                    'decode_content' => false,
                    'curl' => [
                        CURLOPT_RESOLVE => [$host.':443:'.$address],
                        CURLOPT_PROXY => '',
                        CURLOPT_NOPROXY => '*',
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_FRESH_CONNECT => true,
                        CURLOPT_FORBID_REUSE => true,
                        CURLOPT_NOPROGRESS => false,
                        CURLOPT_PROGRESSFUNCTION => static function (CurlHandle $handle, float $downloadTotal, float $downloaded, float $uploadTotal, float $uploaded): int {
                            return $downloadTotal > self::MAX_RESPONSE_BYTES || $downloaded > self::MAX_RESPONSE_BYTES ? 1 : 0;
                        },
                    ],
                    'on_headers' => static function (ResponseInterface $response): void {
                        $length = $response->getHeaderLine('Content-Length');
                        if ($length !== '' && (preg_match('/\A[0-9]+\z/', $length) !== 1 || (float) $length > self::MAX_RESPONSE_BYTES)) {
                            throw new RuntimeException('SNS response exceeds the permitted size.');
                        }
                    },
                ])->get($url);
            $body = $response->body();
            if (! $response->successful() || strlen($body) > self::MAX_RESPONSE_BYTES) {
                $this->unavailable();
            }

            return $body;
        } catch (Throwable) {
            $this->unavailable();
        }
    }

    private function unavailable(): never
    {
        throw new HttpException(503, 'SNS verification is temporarily unavailable.');
    }
}
