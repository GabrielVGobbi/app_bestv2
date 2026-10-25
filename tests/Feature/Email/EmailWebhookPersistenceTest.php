<?php

namespace Tests\Feature\Email;

use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Models\EmailWebhookEvent;
use App\Modules\Email\Services\EmailWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Fixtures\Email\SvixMessageFixture;
use Tests\TestCase;

class EmailWebhookPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exposes_trusted_processing_and_temporal_cursor_without_attesting_legacy(): void
    {
        foreach (['payload_hash', 'authenticated_at', 'dispatch_token', 'linked_at', 'processing_result'] as $column) {
            $this->assertTrue(Schema::hasColumn('email_webhook_events', $column), $column);
        }
        $this->assertTrue(Schema::hasColumn('email_logs', 'webhook_occurred_at'));
    }

    public function test_application_failure_keeps_committed_inbox_and_rolls_back_projection_then_retry_completes(): void
    {
        config(['services.resend.webhook_secret' => SvixMessageFixture::secret()]);
        $log = EmailLog::factory()->create(['status' => EmailStatus::Sent, 'mailer' => 'resend', 'message_id' => 'durable-fixture']);
        $payload = ['type' => 'email.delivered', 'created_at' => now()->subMinute()->toIso8601String(), 'data' => ['email_id' => $log->message_id]];
        $request = $this->signed($payload, 'evt-durable-fixture');
        $failed = false;
        EmailWebhookEvent::updating(function (EmailWebhookEvent $event) use (&$failed): void {
            if (! $failed && $event->isDirty('processed_at') && $event->processed_at !== null) {
                $failed = true;
                throw new RuntimeException('Synthetic failure before completion');
            }
        });
        try {
            app(EmailWebhookService::class)->handle('resend', $request);
            $this->fail('The application failure must be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic failure before completion', $exception->getMessage());
        }
        $event = EmailWebhookEvent::query()->sole();
        $this->assertTrue($failed);
        $this->assertNull($event->processed_at);
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
        $this->assertNull($log->fresh()->webhook_occurred_at);
        app(EmailWebhookService::class)->handle('resend', $this->signed($payload, 'evt-durable-fixture'));
        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
        $this->assertNotNull($event->fresh()->processed_at);
        $before = $log->fresh()->getRawOriginal();
        app(EmailWebhookService::class)->handle('resend', $this->signed($payload, 'evt-durable-fixture'));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertDatabaseCount('email_webhook_events', 1);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_utc_event_clock_and_cursor_keep_their_zone_when_the_application_uses_local_time(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/Sao_Paulo');
        try {
            config(['services.resend.webhook_secret' => SvixMessageFixture::secret()]);
            $log = EmailLog::factory()->create(['mailer' => 'resend', 'status' => EmailStatus::Sent, 'message_id' => 'utc-fixture']);
            $payload = ['type' => 'email.delivered', 'created_at' => '2026-10-17T04:00:00.123456+01:00', 'data' => ['email_id' => $log->message_id]];
            app(EmailWebhookService::class)->handle('resend', $this->signed($payload, 'evt-utc-fixture'));
            $this->assertSame('+00:00', EmailWebhookEvent::query()->sole()->occurred_at->format('P'));
            $this->assertSame('2026-10-17T03:00:00.123456+00:00', $log->fresh()->webhook_occurred_at->format('Y-m-d\TH:i:s.uP'));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    private function signed(array $payload, string $id): Request
    {
        $raw = SvixMessageFixture::raw($payload);
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw);
        foreach (SvixMessageFixture::headers($raw, $id, (string) now()->timestamp) as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }
}
