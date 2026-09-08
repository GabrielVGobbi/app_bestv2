<?php

namespace Tests\Feature\Email;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Models\EmailWebhookEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EmailWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.resend.webhook_secret' => 'whsec_'.base64_encode('secret')]);
        Http::preventStrayRequests();
    }

    /** @param array<string, mixed> $payload */
    protected function postResend(array $payload): TestResponse
    {
        $id = $this->defaultHeaders['svix-id'] ?? (string) Str::uuid();
        $timestamp = (string) now()->timestamp;
        $body = json_encode($payload);
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", 'secret', true));

        return $this->postJson('/api/email/webhook/resend', $payload, [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => $signature,
        ]);
    }

    public function test_resend_webhook_marks_delivered(): void
    {
        $log = EmailLog::factory()->create([
            'status' => EmailStatus::Sent->value,
            'message_id' => 'msg-abc123',
        ]);

        $this->postResend([
            'type' => 'email.delivered',
            'data' => ['email_id' => 'msg-abc123'],
        ])->assertNoContent();

        $this->assertDatabaseHas('email_logs', [
            'uuid' => $log->uuid,
            'status' => 'delivered',
        ]);

        $this->assertDatabaseHas('email_webhook_events', [
            'provider' => 'resend',
            'event_type' => 'email.delivered',
            'message_id' => 'msg-abc123',
            'email_log_id' => $log->id,
        ]);
    }

    public function test_resend_webhook_marks_bounced(): void
    {
        $log = EmailLog::factory()->create([
            'status' => EmailStatus::Sent->value,
            'message_id' => 'msg-bounce-xyz',
        ]);

        $this->postResend([
            'type' => 'email.bounced',
            'data' => [
                'email_id' => 'msg-bounce-xyz',
                'reason' => 'mailbox_not_found',
            ],
        ])->assertNoContent();

        $this->assertDatabaseHas('email_logs', [
            'uuid' => $log->uuid,
            'status' => 'bounced',
        ]);
    }

    public function test_resend_webhook_stores_and_maps_all_email_event_types(): void
    {
        $eventTypes = [
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
        ];

        foreach ($eventTypes as $eventType => $status) {
            $messageId = 'msg-'.str_replace(['email.', '_'], ['', '-'], $eventType);
            $log = EmailLog::factory()->create([
                'status' => EmailStatus::Sent->value,
                'message_id' => $messageId,
            ]);

            $this->withHeaders(['svix-id' => 'evt-'.$messageId])
                ->postResend([
                    'type' => $eventType,
                    'created_at' => '2026-05-20T01:30:00.000Z',
                    'data' => [
                        'email_id' => $messageId,
                        'reason' => 'provider reason',
                    ],
                ])->assertNoContent();

            $this->assertDatabaseHas('email_logs', [
                'uuid' => $log->uuid,
                'status' => $status->value,
            ]);

            $this->assertDatabaseHas('email_webhook_events', [
                'provider' => 'resend',
                'event_id' => 'evt-'.$messageId,
                'event_type' => $eventType,
                'event_family' => 'email',
                'message_id' => $messageId,
                'email_log_id' => $log->id,
            ]);
        }
    }

    public function test_resend_webhook_deduplicates_by_svix_id(): void
    {
        $log = EmailLog::factory()->create([
            'status' => EmailStatus::Sent->value,
            'message_id' => 'msg-duplicate',
        ]);

        $payload = [
            'type' => 'email.delivered',
            'data' => ['email_id' => 'msg-duplicate'],
        ];

        $this->withHeaders(['svix-id' => 'evt-duplicate'])
            ->postResend($payload)
            ->assertNoContent();

        $this->withHeaders(['svix-id' => 'evt-duplicate'])
            ->postResend($payload)
            ->assertNoContent();

        $this->assertSame(1, EmailWebhookEvent::where('event_id', 'evt-duplicate')->count());
        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
    }

    public function test_resend_webhook_stores_domain_and_contact_events_without_email_log(): void
    {
        $this->withHeaders(['svix-id' => 'evt-domain-created'])
            ->postResend([
                'type' => 'domain.created',
                'created_at' => '2026-05-20T01:30:00.000Z',
                'data' => ['id' => 'domain_123', 'name' => 'example.com'],
            ])->assertNoContent();

        $this->withHeaders(['svix-id' => 'evt-contact-created'])
            ->postResend([
                'type' => 'contact.created',
                'created_at' => '2026-05-20T01:31:00.000Z',
                'data' => ['id' => 'contact_123', 'email' => 'user@example.com'],
            ])->assertNoContent();

        $this->assertDatabaseHas('email_webhook_events', [
            'provider' => 'resend',
            'event_id' => 'evt-domain-created',
            'event_type' => 'domain.created',
            'event_family' => 'domain',
            'email_log_id' => null,
        ]);

        $this->assertDatabaseHas('email_webhook_events', [
            'provider' => 'resend',
            'event_id' => 'evt-contact-created',
            'event_type' => 'contact.created',
            'event_family' => 'contact',
            'email_log_id' => null,
        ]);
    }

    public function test_resend_webhook_accepts_valid_svix_signature(): void
    {
        config(['services.resend.webhook_secret' => 'whsec_'.base64_encode('secret')]);

        $log = EmailLog::factory()->create([
            'status' => EmailStatus::Sent->value,
            'message_id' => 'msg-signed',
        ]);

        $payload = json_encode([
            'type' => 'email.delivered',
            'data' => ['email_id' => 'msg-signed'],
        ]);

        $svixId = 'msg_123';
        $svixTimestamp = (string) now()->timestamp;
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', "{$svixId}.{$svixTimestamp}.{$payload}", 'secret', true));

        $this->withHeaders([
            'svix-id' => $svixId,
            'svix-timestamp' => $svixTimestamp,
            'svix-signature' => $signature,
        ])->postJson('/api/email/webhook/resend', json_decode($payload, true))
            ->assertNoContent();

        $this->assertDatabaseHas('email_logs', [
            'uuid' => $log->uuid,
            'status' => 'delivered',
        ]);
    }

    public function test_resend_webhook_rejects_invalid_svix_signature(): void
    {
        config(['services.resend.webhook_secret' => 'whsec_'.base64_encode('secret')]);

        $this->withHeaders([
            'svix-id' => 'msg_123',
            'svix-timestamp' => (string) now()->timestamp,
            'svix-signature' => 'v1,invalid',
        ])->postJson('/api/email/webhook/resend', [
            'type' => 'email.delivered',
            'data' => ['email_id' => 'msg-signed'],
        ])->assertUnauthorized();
    }

    public function test_webhook_ignores_unknown_message_id(): void
    {
        $this->postResend([
            'type' => 'email.delivered',
            'data' => ['email_id' => 'nonexistent-id'],
        ])->assertNoContent();

        $this->assertDatabaseHas('email_webhook_events', [
            'provider' => 'resend',
            'event_type' => 'email.delivered',
            'message_id' => 'nonexistent-id',
            'email_log_id' => null,
        ]);
    }

    public function test_webhook_returns_404_for_unknown_provider(): void
    {
        $this->postJson('/api/email/webhook/unknownprovider', [])
            ->assertNotFound();
    }

    public function test_ses_webhook_handles_delivery_notification(): void
    {
        $log = EmailLog::factory()->create([
            'status' => EmailStatus::Sent->value,
            'message_id' => 'ses-msg-001',
        ]);

        $this->postJson('/api/email/webhook/ses', $this->signedSns([
            'Type' => 'Notification',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:MyTopic',
            'Message' => json_encode([
                'eventType' => 'Delivery',
                'mail' => ['messageId' => 'ses-msg-001'],
            ]),
        ]))->assertNoContent();

        $this->assertDatabaseHas('email_logs', [
            'uuid' => $log->uuid,
            'status' => 'delivered',
        ]);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function signedSns(array $overrides = [], bool $fakeCertificate = true): array
    {
        config(['services.ses.webhook_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:MyTopic']);
        $payload = array_replace([
            'Type' => 'Notification',
            'MessageId' => 'sns-event-001',
            'TopicArn' => config('services.ses.webhook_topic_arn'),
            'Message' => '{}',
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '2',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test123.pem',
        ], $overrides);
        $fields = $payload['Type'] === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        $content = '';

        foreach ($fields as $field) {
            if (array_key_exists($field, $payload)) {
                $content .= $field."\n".$payload[$field]."\n";
            }
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $csr = openssl_csr_new(['commonName' => 'sns.us-east-1.amazonaws.com'], $key);
        $this->assertNotFalse($csr);
        $certificate = openssl_csr_sign($csr, null, $key, 1);
        $this->assertNotFalse($certificate);
        openssl_x509_export($certificate, $pem);
        openssl_sign($content, $signature, $key, $payload['SignatureVersion'] === '1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256);
        $payload['Signature'] = base64_encode($signature);
        if ($fakeCertificate) {
            Http::fake([$payload['SigningCertURL'] => Http::response($pem)]);
        }

        return $payload;
    }

    public function test_resend_rejects_missing_or_malformed_configuration_without_recording_events(): void
    {
        foreach ([null, '', 'whsec_***', 'whsec_', 'not-a-secret'] as $secret) {
            config(['services.resend.webhook_secret' => $secret]);
            $this->postResend(['type' => 'email.delivered'])->assertUnauthorized();
        }

        $this->assertDatabaseCount('email_webhook_events', 0);
        Http::assertNothingSent();
    }

    public function test_resend_rejects_unsigned_expired_future_and_tampered_requests(): void
    {
        $payload = ['type' => 'email.delivered', 'data' => ['email_id' => 'protected-message']];
        $log = EmailLog::factory()->create(['message_id' => 'protected-message', 'status' => EmailStatus::Sent]);

        $this->postJson('/api/email/webhook/resend', $payload)->assertUnauthorized();

        foreach ([(string) (now()->timestamp - 301), (string) (now()->timestamp + 301), 'invalid'] as $timestamp) {
            $signature = 'v1,'.base64_encode(hash_hmac('sha256', 'event.'.$timestamp.'.'.json_encode($payload), 'secret', true));
            $this->postJson('/api/email/webhook/resend', $payload, [
                'svix-id' => 'event', 'svix-timestamp' => $timestamp, 'svix-signature' => $signature,
            ])->assertUnauthorized();
        }

        $timestamp = (string) now()->timestamp;
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', 'event.'.$timestamp.'.'.json_encode($payload), 'secret', true));
        $payload['type'] = 'email.bounced';
        $this->postJson('/api/email/webhook/resend', $payload, [
            'svix-id' => 'event', 'svix-timestamp' => $timestamp, 'svix-signature' => $signature,
        ])->assertUnauthorized();

        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
        $this->assertDatabaseCount('email_webhook_events', 0);
    }

    public function test_postmark_requires_configured_token_and_only_updates_authenticated_events(): void
    {
        $log = EmailLog::factory()->create(['message_id' => 'postmark-message', 'status' => EmailStatus::Sent]);
        $payload = ['RecordType' => 'Delivery', 'MessageID' => 'postmark-message'];

        config(['services.postmark.webhook_token' => null]);
        $this->postJson('/api/email/webhook/postmark', $payload)->assertUnauthorized();
        config(['services.postmark.webhook_token' => 'postmark-secret']);
        $this->postJson('/api/email/webhook/postmark', $payload)->assertUnauthorized();
        $this->postJson('/api/email/webhook/postmark', $payload, ['X-Postmark-Webhook-Token' => 'wrong'])->assertUnauthorized();
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);

        $this->postJson('/api/email/webhook/postmark', $payload, ['X-Postmark-Webhook-Token' => 'postmark-secret'])->assertOk();
        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
    }

    public function test_ses_rejects_unconfigured_unsigned_and_untrusted_topics_without_http_requests(): void
    {
        $payload = ['Type' => 'SubscriptionConfirmation', 'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:MyTopic',
            'Message' => '{}', 'SubscribeURL' => 'http://127.0.0.1/internal'];
        config(['services.ses.webhook_topic_arn' => null]);
        $this->postJson('/api/email/webhook/ses', $payload)->assertUnauthorized();
        config(['services.ses.webhook_topic_arn' => $payload['TopicArn']]);
        $this->postJson('/api/email/webhook/ses', $payload)->assertUnauthorized();
        $payload['TopicArn'] = 'arn:aws:sns:us-east-1:999999999999:MyTopic';
        $this->postJson('/api/email/webhook/ses', $payload)->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_ses_rejects_untrusted_certificate_urls_without_fetching_them(): void
    {
        $payload = $this->signedSns();

        foreach ([
            'http://sns.us-east-1.amazonaws.com/SimpleNotificationService-test123.pem',
            'https://sns.us-east-1.amazonaws.com.attacker.test/SimpleNotificationService-test123.pem',
            'https://sns.us-east-1.amazonaws.com@127.0.0.1/SimpleNotificationService-test123.pem',
            'https://127.0.0.1/SimpleNotificationService-test123.pem',
            'https://sns.us-east-1.amazonaws.com:444/SimpleNotificationService-test123.pem',
            'https://sns.us-east-1.amazonaws.com/../internal',
            'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test123.pem?redirect=1',
            'https://sns.eu-west-1.amazonaws.com/SimpleNotificationService-test123.pem',
        ] as $url) {
            $payload['SigningCertURL'] = $url;
            $this->postJson('/api/email/webhook/ses', $payload)->assertUnauthorized();
        }

        Http::assertNothingSent();
    }

    public function test_ses_rejects_tampered_messages_and_malformed_signatures_without_updating_logs(): void
    {
        $log = EmailLog::factory()->create(['message_id' => 'protected-ses', 'status' => EmailStatus::Sent]);
        $payload = $this->signedSns(['Message' => json_encode(['eventType' => 'Delivery', 'mail' => ['messageId' => 'protected-ses']])]);
        $payload['Message'] = str_replace('Delivery', 'Bounce', $payload['Message']);
        $this->postJson('/api/email/webhook/ses', $payload)->assertUnauthorized();
        $payload['Signature'] = '***';
        $this->postJson('/api/email/webhook/ses', $payload)->assertUnauthorized();
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
    }

    public function test_ses_accepts_legacy_signature_and_optional_subject(): void
    {
        $payload = $this->signedSns(['SignatureVersion' => '1', 'Subject' => 'Delivery status']);
        $this->postJson('/api/email/webhook/ses', $payload)->assertNoContent();
    }

    public function test_ses_rejects_certificate_redirect_failure_and_invalid_certificate(): void
    {
        $payload = $this->signedSns(fakeCertificate: false);
        Http::fake([$payload['SigningCertURL'] => Http::sequence()
            ->push('', 302, ['Location' => 'http://127.0.0.1/private'])
            ->push('', 500)
            ->push('invalid certificate')]);

        foreach ([401, 503, 401] as $status) {
            $this->postJson('/api/email/webhook/ses', $payload)->assertStatus($status);
        }

        Http::assertSentCount(3);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
    }

    public function test_ses_returns_retryable_error_when_certificate_connection_fails(): void
    {
        $payload = $this->signedSns(fakeCertificate: false);
        Http::fake([$payload['SigningCertURL'] => Http::failedConnection()]);

        $this->postJson('/api/email/webhook/ses', $payload)->assertStatus(503);
        $this->assertDatabaseCount('email_webhook_events', 0);
    }

    public function test_ses_confirms_authenticated_subscription_using_only_the_configured_aws_endpoint(): void
    {
        $payload = $this->signedSns([
            'Type' => 'SubscriptionConfirmation',
            'Token' => 'signed-token',
            'SubscribeURL' => 'http://127.0.0.1/untrusted',
        ]);
        Http::fake(['https://sns.us-east-1.amazonaws.com?*' => Http::response('<ConfirmSubscriptionResponse/>')]);

        $this->postJson('/api/email/webhook/ses', $payload)->assertNoContent();
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://sns.us-east-1.amazonaws.com?')
            && $request['Action'] === 'ConfirmSubscription'
            && $request['Token'] === 'signed-token'
            && $request['TopicArn'] === config('services.ses.webhook_topic_arn'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
    }
}
