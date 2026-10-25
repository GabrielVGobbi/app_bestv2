<?php

namespace Tests\Feature\Email;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Models\EmailWebhookEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Email\SnsMessageFixture;
use Tests\Fixtures\Email\SvixMessageFixture;
use Tests\TestCase;

class EmailWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resend_webhook_marks_delivered(): void
    {
        $log = EmailLog::factory()->create([
            'status' => EmailStatus::Sent->value,
            'message_id' => 'msg-abc123',
        ]);

        $this->postSignedResend([
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

        $this->postSignedResend([
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
                'status' => EmailStatus::Processing->value,
                'message_id' => $messageId,
            ]);

            $this->postSignedResend([
                'type' => $eventType,
                'created_at' => '2026-05-20T01:30:00.000Z',
                'data' => [
                    'email_id' => $messageId,
                    'reason' => 'provider reason',
                ],
            ], 'evt-'.$messageId)->assertNoContent();

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

        $this->postSignedResend($payload, 'evt-duplicate')
            ->assertNoContent();

        $this->postSignedResend($payload, 'evt-duplicate')
            ->assertNoContent();

        $this->assertSame(1, EmailWebhookEvent::where('event_id', 'evt-duplicate')->count());
        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
    }

    public function test_resend_webhook_stores_domain_and_contact_events_without_email_log(): void
    {
        $this->postSignedResend([
            'type' => 'domain.created',
            'created_at' => '2026-05-20T01:30:00.000Z',
            'data' => ['id' => 'domain_123', 'name' => 'example.com'],
        ], 'evt-domain-created')->assertNoContent();

        $this->postSignedResend([
            'type' => 'contact.created',
            'created_at' => '2026-05-20T01:31:00.000Z',
            'data' => ['id' => 'contact_123', 'email' => 'user@example.com'],
        ], 'evt-contact-created')->assertNoContent();

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
        $log = EmailLog::factory()->create([
            'status' => EmailStatus::Sent->value,
            'message_id' => 'msg-signed',
        ]);

        $this->postSignedResend([
            'type' => 'email.delivered',
            'data' => ['email_id' => 'msg-signed'],
        ], 'msg_123')
            ->assertNoContent();

        $this->assertDatabaseHas('email_logs', [
            'uuid' => $log->uuid,
            'status' => 'delivered',
        ]);
    }

    public function test_resend_webhook_rejects_invalid_svix_signature(): void
    {
        $this->travelTo('2026-10-16 12:00:00 UTC');
        config(['services.resend.webhook_secret' => SvixMessageFixture::secret()]);

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
        $this->postSignedResend([
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

    /** @param array<string, mixed> $payload */
    private function postSignedResend(array $payload, string $id = 'evt-synthetic'): TestResponse
    {
        $this->travelTo('2026-10-16 12:00:00 UTC');
        config(['services.resend.webhook_secret' => SvixMessageFixture::secret()]);
        $raw = SvixMessageFixture::raw($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach (SvixMessageFixture::headers($raw, $id, (string) now()->timestamp) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/api/email/webhook/resend', [], [], [], $server, $raw);
    }

    public function test_ses_webhook_handles_delivery_notification(): void
    {
        $log = EmailLog::factory()->create([
            'status' => EmailStatus::Sent->value,
            'message_id' => 'ses-msg-001',
        ]);

        $this->isolateProjectFiles();
        $fixture = SnsMessageFixture::create($this->testEnvironment->path);
        config(['services.ses.allowed_topic_arns' => [SnsMessageFixture::TOPIC_ARN]]);
        SnsMessageFixture::bindResolver($this->app);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response($fixture->certificate(), 200));

        $this->postJson('/api/email/webhook/ses', $fixture->notification('ses-msg-001'))->assertNoContent();

        $this->assertDatabaseHas('email_logs', [
            'uuid' => $log->uuid,
            'status' => 'delivered',
        ]);
    }
}
