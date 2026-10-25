<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Models\EmailWebhookEvent;
use App\Modules\Email\Services\EmailWebhookEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Fixtures\Email\SvixMessageFixture;
use Tests\TestCase;

class EmailWebhookInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-18 12:00:00 UTC');
        config(['services.resend.webhook_secret' => SvixMessageFixture::secret()]);
    }

    public function test_failed_application_rolls_back_delivery_but_keeps_authenticated_inbox_for_http_retry(): void
    {
        $log = $this->log('msg-rollback');
        $before = $log->fresh()->getRawOriginal();
        $failOnce = true;
        EmailWebhookEvent::updating(function (EmailWebhookEvent $event) use (&$failOnce): void {
            if ($failOnce && $event->isDirty('processed_at') && $event->processed_at !== null) {
                $failOnce = false;
                throw new RuntimeException('MAIL002_INJECTED_LOCAL_FAILURE');
            }
        });
        $payload = $this->payload('email.delivered', 'msg-rollback');

        $this->postSigned($payload, 'evt-rollback')->assertStatus(500);

        $event = $this->event('evt-rollback');
        $this->assertNull($event->processed_at);
        $this->assertNotNull($event->authenticated_at);
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertDatabaseCount('email_webhook_events', 1);

        $this->postSigned($payload, 'evt-rollback')->assertNoContent();

        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertDatabaseCount('email_webhook_events', 1);
        $this->assertNoOutboundEffects();
    }

    public function test_processed_duplicate_does_not_change_log_or_event(): void
    {
        $log = $this->log('msg-duplicate');
        $payload = $this->payload('email.delivered', 'msg-duplicate');
        $this->postSigned($payload, 'evt-duplicate')->assertNoContent();
        $before = $log->fresh()->getRawOriginal();
        $eventBefore = $this->event('evt-duplicate')->getRawOriginal();
        $this->travelTo('2026-10-18 12:01:00 UTC');

        $this->postSigned($payload, 'evt-duplicate')->assertNoContent();

        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertSame($eventBefore, $this->event('evt-duplicate')->getRawOriginal());
        $this->assertDatabaseCount('email_webhook_events', 1);
    }

    public function test_same_signed_event_id_with_a_different_body_returns_generic_conflict_without_overwrite(): void
    {
        $log = $this->log('msg-conflict');
        $this->postSigned($this->payload('email.delivered', 'msg-conflict'), 'evt-conflict')->assertNoContent();
        $eventBefore = $this->event('evt-conflict')->getRawOriginal();
        $before = $log->fresh()->getRawOriginal();
        $conflicting = $this->payload('email.failed', 'msg-conflict');
        $conflicting['data']['reason'] = 'MAIL002_CONFLICT_PRIVATE_DETAIL';

        $response = $this->postSigned($conflicting, 'evt-conflict');

        $response->assertStatus(409)->assertDontSee('MAIL002_CONFLICT_PRIVATE_DETAIL')->assertDontSee('msg-conflict');
        $this->assertSame($eventBefore, $this->event('evt-conflict')->getRawOriginal());
        $this->assertSame($before, $log->fresh()->getRawOriginal());
    }

    public function test_literal_zero_svix_id_is_preserved_and_conflicting_reuse_is_rejected(): void
    {
        $log = $this->log('msg-zero-id');
        $payload = $this->payload('email.delivered', 'msg-zero-id');
        $this->postSigned($payload, '0')->assertNoContent();
        $event = $this->event('0');
        $this->assertSame('0', $event->event_id);
        $this->assertSame('0', $event->signature_id);
        $this->assertNotNull($event->processed_at);
        $before = $event->getRawOriginal();
        $logBefore = $log->fresh()->getRawOriginal();

        $this->postSigned($this->payload('email.failed', 'msg-zero-id'), '0')->assertStatus(409);

        $this->assertSame($before, $event->fresh()->getRawOriginal());
        $this->assertSame($logBefore, $log->fresh()->getRawOriginal());
        $this->assertDatabaseCount('email_webhook_events', 1);
        $this->assertNoOutboundEffects();
    }

    public function test_inbox_persists_only_the_two_operational_svix_headers_and_discards_credentials(): void
    {
        $this->log('msg-private-headers');
        $this->withServerVariables([
            'HTTP_AUTHORIZATION' => 'Bearer MAIL002_AUTHORIZATION_SECRET',
            'HTTP_COOKIE' => 'session=MAIL002_COOKIE_SECRET',
            'HTTP_X_POSTMARK_WEBHOOK_TOKEN' => 'MAIL002_POSTMARK_SECRET',
        ]);

        $this->postSigned($this->payload('email.delivered', 'msg-private-headers'), 'evt-private-headers')->assertNoContent();

        $event = $this->event('evt-private-headers');
        $keys = array_keys($event->headers);
        sort($keys);
        $this->assertSame(['svix-id', 'svix-timestamp'], $keys);
        $this->assertSame(['evt-private-headers'], $event->headers['svix-id']);
        $this->assertSame([(string) now()->timestamp], $event->headers['svix-timestamp']);
        $persisted = json_encode($event->getAttributes(), JSON_THROW_ON_ERROR);
        foreach (['MAIL002_AUTHORIZATION_SECRET', 'MAIL002_COOKIE_SECRET', 'MAIL002_POSTMARK_SECRET'] as $secret) {
            $this->assertStringNotContainsString($secret, $persisted);
        }
        $this->assertNoOutboundEffects();
    }

    #[DataProvider('malformedEventFields')]
    public function test_malformed_signed_event_fields_return_bad_request_without_persisting_an_event(string $field, mixed $value): void
    {
        $log = $this->log('msg-malformed');
        $before = $log->fresh()->getRawOriginal();
        $payload = $this->payload('email.delivered', 'msg-malformed');
        data_set($payload, $field, $value);

        $this->postSigned($payload, 'evt-malformed')->assertStatus(400);

        $this->assertDatabaseCount('email_webhook_events', 0);
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertNoOutboundEffects();
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function malformedEventFields(): iterable
    {
        yield 'array event type' => ['type', ['email.delivered']];
        yield 'oversized event type' => ['type', str_repeat('t', 256)];
        yield 'array message identifier' => ['data.email_id', ['msg-malformed']];
        yield 'oversized message identifier' => ['data.email_id', str_repeat('m', 256)];
    }

    public function test_deleting_a_bound_log_does_not_allow_its_pending_event_to_capture_a_replacement(): void
    {
        $original = $this->log('msg-deleted-binding');
        $this->postSigned($this->payload('email.delivered', 'msg-deleted-binding', '2026-10-18T12:10:00Z'), 'evt-deleted-binding')->assertNoContent();
        $event = $this->event('evt-deleted-binding');
        $this->assertSame($original->id, $event->email_log_id);
        $this->assertNotNull($event->linked_at);
        $this->assertNull($event->processed_at);
        $original->delete();
        $this->assertNull($event->fresh()->email_log_id);
        $this->assertNotNull($event->fresh()->linked_at);
        $replacement = $this->log('msg-deleted-binding');
        $before = $replacement->fresh()->getRawOriginal();
        $this->travelTo('2026-10-18 12:11:00 UTC');

        $this->assertTrue(app(EmailWebhookEventRecorder::class)->process($event));

        $this->assertSame($before, $replacement->fresh()->getRawOriginal());
        $this->assertNull($event->fresh()->email_log_id);
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame('obsolete_generation', $event->fresh()->processing_result);
        $this->assertNoOutboundEffects();
    }

    public function test_resend_event_cannot_mutate_a_log_bound_to_a_known_ses_transport(): void
    {
        config(['mail.mailers.ses.transport' => 'ses']);
        $log = $this->log('msg-ses-provider', ['mailer' => 'ses']);
        $before = $log->fresh()->getRawOriginal();

        $this->postSigned($this->payload('email.delivered', 'msg-ses-provider'), 'evt-provider-mismatch')->assertNoContent();

        $event = $this->event('evt-provider-mismatch');
        $this->assertNull($event->processed_at);
        $this->assertSame('provider_mismatch', $event->processing_result);
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertNoOutboundEffects();
    }

    public function test_unmatched_pending_event_is_relinked_and_applied_on_identical_signed_retry(): void
    {
        $payload = $this->payload('email.delivered', 'msg-later');
        $this->postSigned($payload, 'evt-later')->assertNoContent();
        $event = $this->event('evt-later');
        $this->assertNull($event->processed_at);
        $this->assertNull($event->email_log_id);
        $log = $this->log('msg-later');

        $this->postSigned($payload, 'evt-later')->assertNoContent();

        $this->assertSame($log->id, $event->fresh()->email_log_id);
        $this->assertSame($log->dispatch_token, $event->fresh()->dispatch_token);
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
        $this->assertDatabaseCount('email_webhook_events', 1);
    }

    public function test_reprocessing_reads_persisted_facts_instead_of_a_mutated_in_memory_model(): void
    {
        $payload = $this->payload('email.delivered', 'msg-persisted');
        $this->postSigned($payload, 'evt-persisted')->assertNoContent();
        $event = $this->event('evt-persisted');
        $this->assertNull($event->processed_at);
        $log = $this->log('msg-persisted');
        $event->payload = $this->payload('email.failed', 'msg-persisted');
        $event->event_type = 'email.failed';

        $this->assertTrue(app(EmailWebhookEventRecorder::class)->process($event));

        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
        $this->assertSame($payload, $event->fresh()->payload);
        $this->assertSame('email.delivered', $event->fresh()->event_type);
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_a_pending_event_cannot_mutate_a_new_dispatch_generation(): void
    {
        $log = $this->log('msg-old-generation');
        $this->postSigned($this->payload('email.delivered', 'msg-old-generation', '2026-10-18T12:10:00Z'), 'evt-old-generation')->assertNoContent();
        $event = $this->event('evt-old-generation');
        $this->assertNull($event->processed_at);
        $this->assertSame($log->dispatch_token, $event->dispatch_token);
        $log->update(['dispatch_token' => (string) Str::uuid(), 'status' => EmailStatus::Processing, 'message_id' => null]);
        $before = $log->fresh()->getRawOriginal();
        $this->travelTo('2026-10-18 12:11:00 UTC');

        app(EmailWebhookEventRecorder::class)->process($event);

        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertNoOutboundEffects();
    }

    public function test_ambiguous_message_id_stays_pending_without_choosing_an_arbitrary_log(): void
    {
        $first = $this->log('msg-ambiguous');
        $second = $this->log('msg-ambiguous');
        $before = [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()];

        $this->postSigned($this->payload('email.delivered', 'msg-ambiguous'), 'evt-ambiguous')->assertNoContent();

        $event = $this->event('evt-ambiguous');
        $this->assertNull($event->processed_at);
        $this->assertNull($event->email_log_id);
        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
    }

    public function test_unknown_event_type_is_completed_without_mutating_delivery(): void
    {
        $log = $this->log('msg-unknown');
        $before = $log->fresh()->getRawOriginal();

        $this->postSigned($this->payload('contact.created', 'msg-unknown'), 'evt-unknown')->assertNoContent();

        $this->assertNotNull($this->event('evt-unknown')->processed_at);
        $this->assertSame($before, $log->fresh()->getRawOriginal());
    }

    public function test_recorder_rejects_a_forged_authentication_marker_bound_to_a_different_body(): void
    {
        $payload = $this->payload('email.delivered', 'msg-forged');
        $raw = SvixMessageFixture::raw($payload);
        $request = Request::create('/api/email/webhook/resend', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $raw);
        $request->headers->set('svix-id', 'evt-forged');
        $request->attributes->set(EmailWebhookEventRecorder::AUTHENTICATED_REQUEST, ['provider' => 'resend', 'hash' => hash('sha256', 'a different body')]);

        try {
            app(EmailWebhookEventRecorder::class)->record('resend', $request, $payload);
            $this->fail('A marker for a different body must not authorize inbox persistence.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('email_webhook_events', 0);
    }

    public function test_identical_signed_retry_can_attest_and_normalize_an_untrusted_legacy_pending_event(): void
    {
        $log = $this->log('msg-legacy');
        $payload = $this->payload('email.delivered', 'msg-legacy');
        $event = $this->legacyEvent('evt-legacy', $payload);
        $event->update(['event_type' => 'email.failed', 'message_id' => 'wrong-stored-message', 'payload' => ['type' => 'email.failed', 'data' => ['email_id' => 'wrong-stored-message']]]);

        $this->postSigned($payload, 'evt-legacy')->assertNoContent();

        $fresh = $event->fresh();
        $this->assertNotNull($fresh->authenticated_at);
        $this->assertSame(hash('sha256', SvixMessageFixture::raw($payload)), $fresh->payload_hash);
        $this->assertSame($payload, $fresh->payload);
        $this->assertSame('email.delivered', $fresh->event_type);
        $this->assertSame('msg-legacy', $fresh->message_id);
        $this->assertSame('2026-10-18 11:59:30.123456', $fresh->occurred_at->format('Y-m-d H:i:s.u'));
        $this->assertNotNull($fresh->processed_at);
        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
    }

    public function test_conflicting_legacy_raw_body_cannot_be_promoted_to_authenticated(): void
    {
        $log = $this->log('msg-legacy-conflict');
        $oldPayload = $this->payload('email.failed', 'msg-legacy-conflict');
        $event = $this->legacyEvent('evt-legacy-conflict', $oldPayload);
        $before = $log->fresh()->getRawOriginal();

        $this->postSigned($this->payload('email.delivered', 'msg-legacy-conflict'), 'evt-legacy-conflict')->assertStatus(409);

        $fresh = $event->fresh();
        $this->assertNull($fresh->authenticated_at);
        $this->assertNull($fresh->payload_hash);
        $this->assertSame($oldPayload, $fresh->payload);
        $this->assertSame(SvixMessageFixture::raw($oldPayload), $fresh->raw_payload);
        $this->assertSame($before, $log->fresh()->getRawOriginal());
    }

    public function test_reprocess_command_applies_only_trusted_pending_events_with_a_finite_limit_and_optional_uuid(): void
    {
        $firstPayload = $this->payload('email.delivered', 'msg-cli-first');
        $secondPayload = $this->payload('email.delivered', 'msg-cli-second');
        $this->postSigned($firstPayload, 'evt-cli-first')->assertNoContent();
        $this->postSigned($secondPayload, 'evt-cli-second')->assertNoContent();
        $first = $this->log('msg-cli-first');
        $second = $this->log('msg-cli-second');
        $legacyLog = $this->log('msg-cli-legacy');
        $legacy = $this->legacyEvent('evt-cli-legacy', $this->payload('email.delivered', 'msg-cli-legacy'));

        $this->artisan('email:webhooks:reprocess', ['--limit' => 1])->assertSuccessful();

        $this->assertSame(EmailStatus::Delivered, $first->fresh()->status);
        $this->assertSame(EmailStatus::Sent, $second->fresh()->status);
        $this->assertDatabaseCount('email_webhook_events', 3);
        $this->assertSame(1, EmailWebhookEvent::query()->whereNotNull('processed_at')->count());
        $this->artisan('email:webhooks:reprocess', ['uuid' => $this->event('evt-cli-second')->uuid, '--limit' => 100])->assertSuccessful();
        $this->assertSame(EmailStatus::Delivered, $second->fresh()->status);
        $this->assertSame(EmailStatus::Sent, $legacyLog->fresh()->status);
        $this->assertNull($legacy->fresh()->authenticated_at);
        $this->assertNull($legacy->fresh()->processed_at);
        $this->assertNoOutboundEffects();
    }

    #[DataProvider('validTimestamps')]
    public function test_iso_timestamp_is_normalized_to_utc_without_losing_microseconds(string $timestamp, string $expected): void
    {
        $log = $this->log('msg-precision');
        $this->postSigned($this->payload('email.delivered', 'msg-precision', $timestamp), 'evt-precision')->assertNoContent();
        $this->assertSame($expected, $this->event('evt-precision')->occurred_at->format('Y-m-d H:i:s.u'));
        $this->assertSame($expected, $log->fresh()->webhook_occurred_at->format('Y-m-d H:i:s.u'));
    }

    #[DataProvider('invalidTimestamps')]
    public function test_invalid_timestamp_is_quarantined_as_completed_without_mutating_delivery(mixed $timestamp): void
    {
        $log = $this->log('msg-invalid-time');
        $before = $log->fresh()->getRawOriginal();
        $payload = $this->payload('email.delivered', 'msg-invalid-time');
        $payload['created_at'] = $timestamp;
        $this->postSigned($payload, 'evt-invalid-time')->assertNoContent();
        $this->assertNotNull($this->event('evt-invalid-time')->processed_at);
        $this->assertNotNull($this->event('evt-invalid-time')->processing_result);
        $this->assertSame($before, $log->fresh()->getRawOriginal());
    }

    public function test_event_beyond_the_future_tolerance_stays_pending_until_its_clock_is_credible(): void
    {
        $log = $this->log('msg-future');
        $before = $log->fresh()->getRawOriginal();
        $this->postSigned($this->payload('email.delivered', 'msg-future', '2026-10-18T12:10:00.000001Z'), 'evt-future')->assertNoContent();
        $event = $this->event('evt-future');
        $this->assertNull($event->processed_at);
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->travelTo('2026-10-18 12:11:00 UTC');

        $this->assertTrue(app(EmailWebhookEventRecorder::class)->process($event));

        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_missing_timestamp_can_apply_weak_evidence_only_before_a_cursor_exists(): void
    {
        $first = $this->log('msg-missing-first', ['status' => EmailStatus::Processing]);
        $payload = $this->payload('email.sent', 'msg-missing-first');
        unset($payload['created_at']);
        $this->postSigned($payload, 'evt-missing-first')->assertNoContent();
        $this->assertSame(EmailStatus::Sent, $first->fresh()->status);
        $this->assertNull($first->fresh()->webhook_occurred_at);
        $second = $this->log('msg-missing-blocked', ['status' => EmailStatus::Processing, 'webhook_occurred_at' => '2026-10-18 11:59:30.000001']);
        $before = $second->fresh()->getRawOriginal();
        $payload['data']['email_id'] = 'msg-missing-blocked';

        $this->postSigned($payload, 'evt-missing-blocked')->assertNoContent();

        $this->assertSame($before, $second->fresh()->getRawOriginal());
        $this->assertNotNull($this->event('evt-missing-blocked')->processed_at);
    }

    #[DataProvider('weakStatuses')]
    public function test_older_weak_event_cannot_mutate_a_newer_delivery_cursor(string $type): void
    {
        $log = $this->log('msg-old-weak', ['status' => EmailStatus::Processing, 'webhook_occurred_at' => '2026-10-18 11:59:30.000001']);
        $before = $log->fresh()->getRawOriginal();

        $this->postSigned($this->payload($type, 'msg-old-weak', '2026-10-18T11:59:10.000001Z'), 'evt-old-weak')->assertNoContent();

        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertNotNull($this->event('evt-old-weak')->processed_at);
    }

    #[DataProvider('strongStatuses')]
    public function test_older_strong_evidence_may_progress_status_while_preserving_the_newer_cursor(string $type, EmailStatus $expected): void
    {
        $log = $this->log('msg-old-strong', ['webhook_occurred_at' => '2026-10-18 11:59:30.000001']);

        $this->postSigned($this->payload($type, 'msg-old-strong', '2026-10-18T11:59:10.000001Z'), 'evt-old-strong')->assertNoContent();

        $this->assertSame($expected, $log->fresh()->status);
        $this->assertSame('2026-10-18 11:59:30.000001', $log->fresh()->webhook_occurred_at->format('Y-m-d H:i:s.u'));
        $this->assertNotNull($this->event('evt-old-strong')->processed_at);
    }

    public function test_equal_microsecond_timestamp_allows_positive_progression(): void
    {
        $log = $this->log('msg-equal', ['status' => EmailStatus::Delivered, 'webhook_occurred_at' => '2026-10-18 11:59:30.123456']);
        $this->postSigned($this->payload('email.opened', 'msg-equal', '2026-10-18T11:59:30.123456Z'), 'evt-equal')->assertNoContent();
        $this->assertSame(EmailStatus::Opened, $log->fresh()->status);
        $this->assertSame('2026-10-18 11:59:30.123456', $log->fresh()->webhook_occurred_at->format('Y-m-d H:i:s.u'));
    }

    public function test_repeated_status_advances_only_cursor_and_keeps_status_metadata_and_lifecycle_timestamps(): void
    {
        $log = $this->log('msg-repeat-status', ['status' => EmailStatus::Delivered, 'metadata' => ['original' => true], 'webhook_occurred_at' => '2026-10-18 11:59:10.000001']);
        $before = $log->fresh()->getRawOriginal();
        $this->postSigned($this->payload('email.delivered', 'msg-repeat-status', '2026-10-18T11:59:30.000002Z'), 'evt-repeat-status')->assertNoContent();
        $fresh = $log->fresh();
        $this->assertSame('2026-10-18 11:59:30.000002', $fresh->webhook_occurred_at->format('Y-m-d H:i:s.u'));
        foreach (['status', 'metadata', 'sent_at', 'failed_at', 'attempts', 'dispatch_token'] as $attribute) {
            $this->assertSame($before[$attribute], $fresh->getRawOriginal($attribute));
        }
    }

    #[DataProvider('arrivalOrders')]
    public function test_status_contract_converges_for_both_arrival_orders(string $olderType, string $newerType, EmailStatus $expected, bool $reverse): void
    {
        $log = $this->log('msg-order');
        $older = [$this->payload($olderType, 'msg-order', '2026-10-18T11:59:10.000001Z'), 'evt-order-older'];
        $newer = [$this->payload($newerType, 'msg-order', '2026-10-18T11:59:30.000002Z'), 'evt-order-newer'];
        foreach ($reverse ? [$newer, $older] : [$older, $newer] as [$payload, $id]) {
            $this->postSigned($payload, $id)->assertNoContent();
        }
        $this->assertSame($expected, $log->fresh()->status);
        $this->assertSame('2026-10-18 11:59:30.000002', $log->fresh()->webhook_occurred_at->format('Y-m-d H:i:s.u'));
        $this->assertSame(2, EmailWebhookEvent::query()->whereNotNull('processed_at')->count());
        $this->assertNoOutboundEffects();
    }

    /** @return iterable<string, array{string, string}> */
    public static function validTimestamps(): iterable
    {
        yield 'UTC micros' => ['2026-10-18T11:59:30.123456Z', '2026-10-18 11:59:30.123456'];
        yield 'offset normalized' => ['2026-10-18T08:59:30.654321-03:00', '2026-10-18 11:59:30.654321'];
        yield 'seconds without fraction' => ['2026-10-18T11:59:30Z', '2026-10-18 11:59:30.000000'];
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidTimestamps(): iterable
    {
        yield 'relative expression' => ['tomorrow'];
        yield 'missing zone' => ['2026-10-18T11:59:30'];
        yield 'impossible date' => ['2026-02-30T11:59:30Z'];
        yield 'excess fractional precision' => ['2026-10-18T11:59:30.1234567Z'];
        yield 'trailing garbage' => ['2026-10-18T11:59:30Zjunk'];
        yield 'empty string' => [''];
        yield 'numeric timestamp' => [1792324770];
        yield 'array timestamp' => [['2026-10-18T11:59:30Z']];
    }

    /** @return iterable<string, array{string}> */
    public static function weakStatuses(): iterable
    {
        yield 'sent' => ['email.sent'];
        yield 'failed' => ['email.failed'];
        yield 'bounced' => ['email.bounced'];
        yield 'delayed' => ['email.delivery_delayed'];
        yield 'scheduled' => ['email.scheduled'];
    }

    /** @return iterable<string, array{string, EmailStatus}> */
    public static function strongStatuses(): iterable
    {
        yield 'delivered' => ['email.delivered', EmailStatus::Delivered];
        yield 'opened' => ['email.opened', EmailStatus::Opened];
        yield 'clicked' => ['email.clicked', EmailStatus::Clicked];
        yield 'suppressed' => ['email.suppressed', EmailStatus::Suppressed];
        yield 'complained' => ['email.complained', EmailStatus::Complained];
    }

    /** @return iterable<string, array{string, string, EmailStatus, bool}> */
    public static function arrivalOrders(): iterable
    {
        $cases = [
            'old complaint newer delivery' => ['email.complained', 'email.delivered', EmailStatus::Complained],
            'old delivery newer complaint' => ['email.delivered', 'email.complained', EmailStatus::Complained],
            'old click newer delivery' => ['email.clicked', 'email.delivered', EmailStatus::Clicked],
            'old delivery newer click' => ['email.delivered', 'email.clicked', EmailStatus::Clicked],
            'old delivery newer failure' => ['email.delivered', 'email.failed', EmailStatus::Delivered],
            'old failure newer delivery' => ['email.failed', 'email.delivered', EmailStatus::Delivered],
        ];
        foreach ($cases as $name => [$older, $newer, $expected]) {
            yield $name.' chronological arrival' => [$older, $newer, $expected, false];
            yield $name.' reverse arrival' => [$older, $newer, $expected, true];
        }
    }

    /** @param array<string, mixed> $attributes */
    private function log(string $messageId, array $attributes = []): EmailLog
    {
        return EmailLog::factory()->create(array_merge([
            'status' => EmailStatus::Sent, 'mailer' => 'resend', 'message_id' => $messageId,
            'dispatch_token' => (string) Str::uuid(), 'retry_safe' => false,
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function payload(string $type, string $messageId, string $timestamp = '2026-10-18T11:59:30.123456Z'): array
    {
        return ['type' => $type, 'created_at' => $timestamp, 'data' => ['email_id' => $messageId, 'marker' => $type]];
    }

    private function event(string $eventId): EmailWebhookEvent
    {
        return EmailWebhookEvent::query()->where('provider', 'resend')->where('event_id', $eventId)->sole();
    }

    /** @param array<string, mixed> $payload */
    private function legacyEvent(string $eventId, array $payload): EmailWebhookEvent
    {
        return EmailWebhookEvent::create([
            'provider' => 'resend', 'event_id' => $eventId, 'event_type' => $payload['type'],
            'event_family' => 'email', 'message_id' => $payload['data']['email_id'],
            'payload' => $payload, 'raw_payload' => SvixMessageFixture::raw($payload),
            'received_at' => now(), 'occurred_at' => null,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function postSigned(array $payload, string $eventId): TestResponse
    {
        $raw = SvixMessageFixture::raw($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach (SvixMessageFixture::headers($raw, $eventId, (string) now()->timestamp) as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/api/email/webhook/resend', [], [], [], $server, $raw);
    }

    private function assertNoOutboundEffects(): void
    {
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }
}
