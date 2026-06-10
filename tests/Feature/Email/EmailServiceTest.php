<?php

namespace Tests\Feature\Email;

use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use App\Supports\AppSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private EmailServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmailServiceInterface::class);
        AppSettings::set('email', 'enabled', true);
        AppSettings::set('email', 'export_enabled', true);
    }

    public function test_queue_creates_email_log_and_dispatches_job(): void
    {
        Queue::fake();

        $dto = new SendEmailDTO(
            to: 'test@example.com',
            subject: 'Hello',
            template: 'emails.exports.ready',
            category: EmailCategory::Export,
        );

        $log = $this->service->queue($dto);

        $this->assertNotNull($log);
        $this->assertInstanceOf(EmailLog::class, $log);
        $this->assertSame('test@example.com', $log->to_email);
        $this->assertSame(EmailStatus::Queued, $log->status);

        Queue::assertPushed(ProcessEmailJob::class);
        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'test@example.com',
            'status' => 'queued',
        ]);
    }

    public function test_global_kill_switch_blocks_all_emails(): void
    {
        Queue::fake();

        AppSettings::set('email', 'enabled', false);

        $dto = new SendEmailDTO(
            to: 'test@example.com',
            subject: 'Hello',
            template: 'emails.exports.ready',
            category: EmailCategory::Export,
        );

        $log = $this->service->queue($dto);

        $this->assertNull($log);
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('email_logs', ['to_email' => 'test@example.com']);
    }

    public function test_module_kill_switch_blocks_specific_category(): void
    {
        Queue::fake();

        AppSettings::set('email', 'export_enabled', false);

        $dto = new SendEmailDTO(
            to: 'test@example.com',
            subject: 'Export ready',
            template: 'emails.exports.ready',
            category: EmailCategory::Export,
        );

        $log = $this->service->queue($dto);

        $this->assertNull($log);
        Queue::assertNothingPushed();
    }

    public function test_idempotency_prevents_duplicate_emails(): void
    {
        Queue::fake();

        $dto = new SendEmailDTO(
            to: 'test@example.com',
            subject: 'Hello',
            template: 'emails.exports.ready',
            category: EmailCategory::Export,
            idempotencyKey: 'unique-key-123',
        );

        $log1 = $this->service->queue($dto);
        $log2 = $this->service->queue($dto);

        $this->assertNotNull($log1);
        $this->assertNull($log2);
        $this->assertDatabaseCount('email_logs', 1);
        Queue::assertPushed(ProcessEmailJob::class, 1);
    }

    public function test_failed_email_can_be_requeued_after_idempotency_check(): void
    {
        Queue::fake();

        EmailLog::factory()->create([
            'to_email' => 'test@example.com',
            'idempotency_key' => 'key-xyz',
            'status' => EmailStatus::Failed->value,
        ]);

        $dto = new SendEmailDTO(
            to: 'test@example.com',
            subject: 'Hello',
            template: 'emails.exports.ready',
            category: EmailCategory::Export,
            idempotencyKey: 'key-xyz',
        );

        // Failed emails do not block new sends — idempotency only blocks queued/processing/sent
        $log = $this->service->queue($dto);

        $this->assertNotNull($log);
    }

    public function test_to_override_redirects_email(): void
    {
        Queue::fake();

        config(['email.to_override' => 'dev@company.com']);

        $dto = new SendEmailDTO(
            to: 'real-user@example.com',
            subject: 'Hello',
            template: 'emails.exports.ready',
            category: EmailCategory::Export,
        );

        $log = $this->service->queue($dto);

        $this->assertNotNull($log);
        $this->assertSame('dev@company.com', $log->to_email);
    }
}
