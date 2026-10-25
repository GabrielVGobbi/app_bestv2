<?php

namespace Tests\Feature\Email;

use App\Modules\Email\Actions\ResendEmailAction;
use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Mail\DynamicMailable;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class EmailPersistedContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00', 'UTC'));
        config(['queue.default' => 'scheduled', 'queue.connections.scheduled' => ['driver' => 'database']]);
        $directory = $this->testEnvironment->path.'/mail003-views';
        mkdir($directory);
        file_put_contents($directory.'/delivery.blade.php', '<p>Owned delivery fixture</p>');
        view()->addNamespace('mail003', $directory);
    }

    public function test_queue_persists_options_and_delays_the_serialized_job(): void
    {
        $scheduled = now()->addHour();
        config(['email.to_override' => 'override@example.test']);
        $log = app(EmailServiceInterface::class)->queue($this->dto('scheduled', $scheduled));
        $this->assertSame('override@example.test', $log->to_email);
        $this->assertSame('reply@example.test', $log->reply_to);
        $this->assertNotNull($log->scheduled_at);
        $this->assertTrue($scheduled->equalTo($log->scheduled_at));
        $this->assertSame('UTC', $log->scheduled_at->timezoneName);
        $this->assertSame($scheduled->utc()->format('Y-m-d H:i:s.u'), $log->getRawOriginal('scheduled_at'));
        Queue::assertPushed(ProcessEmailJob::class, function (ProcessEmailJob $job) use ($scheduled): bool {
            $restored = unserialize(serialize($job));
            $this->assertTrue($scheduled->equalTo($restored->delay));
            $this->assertSame('reply@example.test', $restored->emailLog->reply_to);

            return true;
        });
        Mail::assertNothingOutgoing();
    }

    #[DataProvider('unsupportedQueues')]
    public function test_future_submission_refuses_unsupported_queues_without_persistence(string $method, string $driver): void
    {
        config(['queue.connections.scheduled.driver' => $driver]);
        try {
            app(EmailServiceInterface::class)->{$method}($this->dto('unsafe', now()->addHour()));
            $this->fail('A future email requires a supported durable delayed queue.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('durable', $exception->getMessage());
        }
        $this->assertDatabaseCount('email_logs', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public static function unsupportedQueues(): iterable
    {
        foreach (['queue', 'sendNow'] as $method) {
            foreach (['sync', 'null', 'deferred', 'background', 'failover', 'unknown', 'sqs'] as $driver) {
                yield $method.'-'.$driver => [$method, $driver];
            }
        }
    }

    public function test_send_now_future_date_is_queued_and_an_early_job_cannot_claim(): void
    {
        $log = app(EmailServiceInterface::class)->sendNow($this->dto('future-sync', now()->addHour()));
        $this->assertSame(EmailStatus::Queued, $log->status);
        Queue::assertPushed(ProcessEmailJob::class, fn (ProcessEmailJob $job): bool => $job->delay->equalTo($log->scheduled_at));
        $job = unserialize(serialize(new ProcessEmailJob($log)))->withFakeQueueInteractions();
        $before = $log->fresh()->getRawOriginal();
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $job->assertReleased(3600);
        Mail::assertNothingOutgoing();
        $this->travelTo(now()->addHour());
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
        $this->assertSame(1, $log->fresh()->attempts);
        Mail::assertSentCount(1);
    }

    public function test_recovery_and_resend_use_the_persisted_delay(): void
    {
        $log = $this->log(['scheduled_at' => now()->addHour(), 'reply_to' => 'persisted@example.test']);
        $this->artisan('email:recover', [
            'uuid' => $log->uuid, 'token' => $log->dispatch_token, '--outcome' => 'queued',
            '--evidence' => 'MAIL003-RECOVER', '--workers-stopped' => true,
        ])->assertSuccessful();
        Queue::assertPushed(ProcessEmailJob::class, fn (ProcessEmailJob $job): bool => $job->delay->equalTo($log->scheduled_at));
        Queue::fake();
        $log->update(['status' => EmailStatus::Failed, 'retry_safe' => true]);
        $resent = app(ResendEmailAction::class)->handle($log);
        $this->assertSame('persisted@example.test', $resent->reply_to);
        Queue::assertPushed(ProcessEmailJob::class, fn (ProcessEmailJob $job): bool => $job->delay->equalTo($log->scheduled_at));
        Mail::assertNothingOutgoing();
        $this->travelTo($log->scheduled_at);
        Queue::assertPushed(ProcessEmailJob::class, function (ProcessEmailJob $job): bool {
            unserialize(serialize($job))->handle(app(EmailProviderMessageIdResolver::class));

            return true;
        });
        Mail::assertSent(DynamicMailable::class, function ($mail): bool {
            $mail->render();

            return $mail->hasReplyTo('persisted@example.test');
        });
    }

    public function test_retry_keeps_original_options_when_the_new_dto_and_default_differ(): void
    {
        $log = $this->log([
            'status' => EmailStatus::Failed, 'retry_safe' => true, 'idempotency_key' => 'retry',
            'scheduled_at' => now()->addHour(), 'reply_to' => 'original@example.test',
        ]);
        config(['mail.default' => 'changed', 'mail.mailers.changed' => ['transport' => 'array']]);
        $retried = app(EmailServiceInterface::class)->sendNow($this->dto('retry', null));
        $this->assertSame($log->id, $retried->id);
        $this->assertSame('array', $retried->mailer);
        $this->assertSame('original@example.test', $retried->reply_to);
        $this->assertTrue($log->scheduled_at->equalTo($retried->scheduled_at));
        Queue::assertPushed(ProcessEmailJob::class, fn (ProcessEmailJob $job): bool => $job->delay->equalTo($log->scheduled_at));
        Mail::assertNothingOutgoing();
    }

    public function test_real_array_message_uses_persisted_reply_to_sender_and_mailer_after_default_change(): void
    {
        $log = app(EmailServiceInterface::class)->queue($this->dto('payload', null));
        config(['mail.default' => 'changed', 'mail.mailers.changed' => ['transport' => 'array'], 'mail.from.address' => 'changed@example.test']);
        $manager = new MailManager($this->app);
        Mail::swap($manager);
        $transport = $manager->mailer('array')->getSymfonyTransport();
        $other = $manager->mailer('changed')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertInstanceOf(ArrayTransport::class, $other);
        $payload = (new ProcessEmailJob($log))->__serialize();
        $payload['replyTo'] = 'stale-job@example.test';
        $job = (new ReflectionClass(ProcessEmailJob::class))->newInstanceWithoutConstructor();
        $job->__unserialize($payload);
        $job = unserialize(serialize($job));
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $this->assertCount(1, $transport->messages());
        $this->assertCount(0, $other->messages());
        $message = $transport->messages()->first()->getOriginalMessage();
        $this->assertSame('reply@example.test', $message->getReplyTo()[0]->getAddress());
        $this->assertSame('tests@example.test', $message->getFrom()[0]->getAddress());
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
    }

    #[DataProvider('unconfiguredMailers')]
    public function test_missing_persisted_mailer_fails_before_transport(?string $mailer): void
    {
        $log = $this->log(['mailer' => $mailer]);
        $job = new ProcessEmailJob($log);
        try {
            $job->handle(app(EmailProviderMessageIdResolver::class));
            $this->fail('Unknown persisted mailer must not default silently.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('mailer', $exception->getMessage());
            $job->failed($exception);
        }
        $this->assertSame(EmailStatus::Failed, $log->fresh()->status);
        $this->assertTrue($log->fresh()->retry_safe);
        Mail::assertNothingOutgoing();
    }

    public static function unconfiguredMailers(): iterable
    {
        yield 'legacy-null' => [null];
        yield 'legacy-empty' => [''];
        yield 'removed-alias' => ['no-longer-configured'];
    }

    public function test_database_queue_cannot_pop_the_job_early_and_executes_the_serialized_payload_when_due(): void
    {
        config(['queue.connections.scheduled' => [
            'driver' => 'database', 'connection' => 'sqlite', 'table' => 'jobs',
            'queue' => 'default', 'retry_after' => 90, 'after_commit' => true,
        ]]);
        Queue::swap(Queue::getFacadeRoot()->queue);
        $date = Carbon::parse('2026-10-18 14:30:00', 'Europe/Paris');
        $log = app(EmailServiceInterface::class)->sendNow($this->dto('real-queue', $date));
        $this->assertSame('2026-10-18 12:30:00.000000', $log->getRawOriginal('scheduled_at'));
        $this->assertSame($date->timestamp, DB::table('jobs')->value('available_at'));
        $this->assertNull(Queue::connection()->pop());
        $this->assertSame(0, $log->fresh()->attempts);
        Mail::assertNothingOutgoing();
        $this->travelTo($date);
        $job = Queue::connection()->pop();
        $this->assertNotNull($job);
        $job->fire();
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
        $this->assertSame(1, $log->fresh()->attempts);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertSentCount(1);
    }

    public function test_subsecond_schedule_does_not_claim_early(): void
    {
        $due = now()->addMicroseconds(900000);
        $log = $this->log(['scheduled_at' => $due]);
        $job = (new ProcessEmailJob($log))->withFakeQueueInteractions();
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $job->assertReleased(1);
        $this->assertSame(0, $log->fresh()->attempts);
        Mail::assertNothingOutgoing();
        $this->travelTo($due);
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
        Mail::assertSentCount(1);
    }

    public function test_unsupported_retry_and_resend_leave_the_original_generation_unchanged(): void
    {
        $log = $this->log([
            'status' => EmailStatus::Failed, 'retry_safe' => true,
            'scheduled_at' => now()->addHour(), 'idempotency_key' => 'unsafe-retry',
        ]);
        $before = $log->fresh()->getRawOriginal();
        config(['queue.default' => 'sync']);
        foreach ([
            fn () => app(EmailServiceInterface::class)->sendNow($this->dto('unsafe-retry', null)),
            fn () => app(ResendEmailAction::class)->handle($log),
        ] as $submit) {
            try {
                $submit();
                $this->fail('Unsupported scheduling must be refused.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('durable', $exception->getMessage());
            }
            $this->assertSame($before, $log->fresh()->getRawOriginal());
        }
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_recovery_refuses_a_future_sqs_schedule_without_mutating_the_row(): void
    {
        $log = $this->log(['scheduled_at' => now()->addHour()]);
        $before = $log->fresh()->getRawOriginal();
        config(['queue.connections.scheduled.driver' => 'sqs']);
        $this->artisan('email:recover', [
            'uuid' => $log->uuid, 'token' => $log->dispatch_token, '--outcome' => 'queued',
            '--evidence' => 'MAIL003-UNSUPPORTED', '--workers-stopped' => true,
        ])->assertFailed();
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_claim_rechecks_a_schedule_changed_after_the_initial_read(): void
    {
        $log = $this->log(['scheduled_at' => now()->subMinute()]);
        $changed = false;
        DB::listen(function ($query) use ($log, &$changed): void {
            if (! $changed && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"scheduled_at"') && str_contains($query->sql, 'email_logs')) {
                $changed = true;
                $log->update(['scheduled_at' => now()->addHour()]);
            }
        });
        (new ProcessEmailJob($log))->handle(app(EmailProviderMessageIdResolver::class));
        $this->assertTrue($changed);
        $this->assertSame(0, $log->fresh()->attempts);
        $this->assertSame(EmailStatus::Queued, $log->fresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_delivery_options_migration_preserves_legacy_payload_and_rolls_back_without_sending(): void
    {
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_18_104742_add_delivery_options_to_email_logs_table.php';
        $migration->down();
        try {
            $legacy = $this->log(['status' => EmailStatus::Failed, 'retry_safe' => false]);
            $before = $legacy->fresh()->getRawOriginal();
        } finally {
            $migration->up();
        }
        $current = $legacy->fresh();
        $this->assertNull($current->reply_to);
        $this->assertNull($current->scheduled_at);
        $this->assertSame($before, array_intersect_key($current->getRawOriginal(), $before));
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_past_schedule_is_synchronous_and_rollback_discards_future_dispatch(): void
    {
        config(['queue.default' => 'sync']);
        $log = app(EmailServiceInterface::class)->sendNow($this->dto('past', now()->subMinute()));
        $this->assertSame(EmailStatus::Sent, $log->status);
        Mail::assertSentCount(1);
        config(['queue.default' => 'scheduled']);
        DB::beginTransaction();
        app(EmailServiceInterface::class)->queue($this->dto('rollback', now()->addHour()));
        Queue::assertNothingPushed();
        DB::rollBack();
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('email_logs', ['idempotency_key' => 'rollback']);
    }

    private function dto(string $key, ?CarbonInterface $scheduled): SendEmailDTO
    {
        return new SendEmailDTO(
            to: 'recipient@example.test', subject: 'Persisted contract', template: 'mail003::delivery',
            category: EmailCategory::System, replyTo: 'reply@example.test', idempotencyKey: $key, scheduledAt: $scheduled?->toMutable(),
        );
    }

    /** @param array<string, mixed> $attributes */
    private function log(array $attributes = []): EmailLog
    {
        return EmailLog::factory()->queued()->create(array_merge([
            'dispatch_token' => (string) Str::uuid(), 'attempts' => 0, 'mailer' => 'array',
            'template' => 'mail003::delivery', 'sent_at' => null, 'message_id' => null,
        ], $attributes));
    }
}
