<?php

namespace Tests\Feature\Email;

use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use App\Supports\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class EmailDispatchCommitTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_does_not_reclassify_legacy_failures(): void
    {
        $this->assertTrue(Schema::hasColumn('email_logs', 'dispatch_token'));
        $this->assertTrue(Schema::hasColumn('email_logs', 'retry_safe'));
        $log = EmailLog::factory()->create(['status' => EmailStatus::Failed]);
        $this->assertFalse($log->fresh()->retry_safe);
        $this->assertNull($log->fresh()->dispatch_token);
    }

    public function test_migration_preserves_existing_delivery_states_and_does_not_infer_retry_safety(): void
    {
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_17_232046_add_delivery_claim_to_email_logs_table.php';
        $migration->down();
        $legacy = [];
        foreach ([EmailStatus::Queued, EmailStatus::Processing, EmailStatus::Failed, EmailStatus::Sent, EmailStatus::Suppressed] as $status) {
            $legacy[] = EmailLog::factory()->create(['status' => $status]);
        }
        $migration->up();
        foreach ($legacy as $log) {
            $current = $log->fresh();
            $this->assertSame($log->status, $current->status);
            $this->assertSame($log->uuid, $current->uuid);
            $this->assertSame($log->idempotency_key, $current->idempotency_key);
            $this->assertNull($current->dispatch_token);
            $this->assertFalse($current->retry_safe);
        }
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_queue_waits_for_commit_and_rollback_discards_publication(): void
    {
        AppSettings::set('email', 'enabled', true);
        DB::beginTransaction();
        $log = app(EmailServiceInterface::class)->queue($this->dto('rollback'));
        $this->assertNotNull($log);
        Queue::assertNothingPushed();
        DB::rollBack();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('email_logs', 0);

        DB::beginTransaction();
        $log = app(EmailServiceInterface::class)->queue($this->dto('commit'));
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(ProcessEmailJob::class, 1);
        $this->assertSame(EmailStatus::Queued, $log->fresh()->status);
    }

    public function test_send_now_waits_for_commit_and_uses_idempotency(): void
    {
        $directory = $this->testEnvironment->path.'/views';
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/delivery.blade.php', '<p>Isolated mail fixture</p>');
        view()->addNamespace('mail001', $directory);
        DB::beginTransaction();
        app(EmailServiceInterface::class)->sendNow($this->dto('sync-rollback'));
        Mail::assertNothingOutgoing();
        DB::rollBack();
        Mail::assertNothingOutgoing();
        $this->assertDatabaseCount('email_logs', 0);

        DB::beginTransaction();
        $log = app(EmailServiceInterface::class)->sendNow($this->dto('sync-commit'));
        Mail::assertNothingOutgoing();
        DB::commit();
        Mail::assertSentCount(1);
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
        $this->assertNull(app(EmailServiceInterface::class)->sendNow($this->dto('sync-commit')));
        Mail::assertSentCount(1);
    }

    public function test_recovery_requires_confirmation_exact_generation_and_never_sends(): void
    {
        $token = (string) Str::uuid();
        $log = EmailLog::factory()->create(['status' => EmailStatus::Processing, 'dispatch_token' => $token]);
        $args = ['uuid' => $log->uuid, 'token' => $token, '--outcome' => 'not-sent', '--evidence' => 'INC-001'];
        $this->artisan('email:recover', $args)->assertFailed();
        $this->artisan('email:recover', [...$args, 'token' => (string) Str::uuid(), '--workers-stopped' => true])->assertFailed();
        $this->assertSame(EmailStatus::Processing, $log->fresh()->status);
        $this->artisan('email:recover', [...$args, '--workers-stopped' => true])->assertSuccessful();
        $this->assertSame(EmailStatus::Failed, $log->fresh()->status);
        $this->assertTrue($log->fresh()->retry_safe);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_recovery_of_legacy_failure_assigns_a_new_identity(): void
    {
        $log = EmailLog::factory()->create(['status' => EmailStatus::Failed]);
        $this->artisan('email:recover', ['uuid' => $log->uuid, 'token' => 'legacy', '--outcome' => 'not-sent', '--evidence' => 'INC-002', '--workers-stopped' => true])->assertSuccessful();
        $this->assertTrue($log->fresh()->retry_safe);
        $this->assertTrue(Str::isUuid($log->fresh()->dispatch_token));
        Mail::assertNothingOutgoing();
    }

    public function test_recovery_confirms_sent_and_republishes_only_stranded_queued(): void
    {
        $token = (string) Str::uuid();
        $log = EmailLog::factory()->create(['status' => EmailStatus::Processing, 'dispatch_token' => $token]);
        $args = ['uuid' => $log->uuid, 'token' => $token, '--evidence' => 'INC-003', '--workers-stopped' => true];
        $this->artisan('email:recover', [...$args, '--outcome' => 'sent', '--message-id' => 'synthetic-provider-id'])->assertSuccessful();
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
        $this->assertSame('synthetic-provider-id', $log->fresh()->message_id);
        $this->artisan('email:recover', [...$args, '--outcome' => 'queued'])->assertFailed();
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();

        $queued = EmailLog::factory()->queued()->create(['dispatch_token' => $token]);
        config(['queue.default' => 'recovery', 'queue.connections.recovery' => ['driver' => 'database']]);
        $this->artisan('email:recover', [...$args, 'uuid' => $queued->uuid, '--outcome' => 'queued'])->assertSuccessful();
        Queue::assertPushed(ProcessEmailJob::class, 1);
        $this->assertSame($token, $queued->fresh()->dispatch_token);
    }

    public function test_recovery_refuses_sync_queues_legacy_jobs_and_provider_acceptance(): void
    {
        $token = (string) Str::uuid();
        $log = EmailLog::factory()->queued()->create(['dispatch_token' => $token]);
        $args = ['uuid' => $log->uuid, 'token' => $token, '--outcome' => 'queued', '--evidence' => 'INC-005', '--workers-stopped' => true];
        $this->artisan('email:recover', $args)->assertFailed();
        config(['queue.default' => 'recovery', 'queue.connections.recovery' => ['driver' => 'database']]);
        $log->update(['message_id' => 'known-provider-id']);
        $this->artisan('email:recover', $args)->assertFailed();
        $log->update(['message_id' => null, 'sent_at' => now()]);
        $this->artisan('email:recover', $args)->assertFailed();
        $log->update(['sent_at' => null, 'dispatch_token' => null]);
        $this->artisan('email:recover', [...$args, 'token' => 'legacy'])->assertFailed();
        $this->assertSame(EmailStatus::Queued, $log->fresh()->status);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_stale_retry_permission_cannot_regress_a_provider_failure(): void
    {
        $log = EmailLog::factory()->create(['status' => EmailStatus::Failed, 'retry_safe' => true, 'dispatch_token' => (string) Str::uuid()]);
        $interposed = false;
        DB::listen(function ($query) use ($log, &$interposed): void {
            if (! $interposed && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'email_logs')) {
                $interposed = true;
                EmailLog::whereKey($log->id)->update(['retry_safe' => false]);
            }
        });
        $log->markFromWebhook(EmailStatus::Sent, ['late' => true]);
        $this->assertTrue($interposed);
        $this->assertSame(EmailStatus::Failed, $log->fresh()->status);
        $this->assertFalse($log->fresh()->retry_safe);
        $this->assertNull($log->fresh()->metadata);
    }

    public function test_recovery_refuses_every_non_durable_queue_driver_before_changing_the_row(): void
    {
        $log = EmailLog::factory()->queued()->create(['dispatch_token' => (string) Str::uuid()]);
        $before = $log->fresh()->getRawOriginal();
        foreach (['sync', 'null', 'deferred', 'background', 'failover', 'unknown'] as $driver) {
            config(['queue.default' => 'unsafe-recovery', 'queue.connections.unsafe-recovery' => ['driver' => $driver]]);
            $this->artisan('email:recover', ['uuid' => $log->uuid, 'token' => $log->dispatch_token, '--outcome' => 'queued', '--evidence' => 'INC-QUEUE-001', '--workers-stopped' => true])->assertFailed();
            $this->assertSame($before, $log->fresh()->getRawOriginal());
            Queue::assertNothingPushed();
        }
        Mail::assertNothingOutgoing();
    }

    public function test_real_array_transport_acceptance_then_persistence_failure_requires_reconciliation(): void
    {
        $directory = $this->testEnvironment->path.'/mail-transport-views';
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/delivery.blade.php', '<p>Owned transport fixture</p>');
        view()->addNamespace('mail001', $directory);
        $manager = new MailManager($this->app);
        $transport = $manager->mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        Mail::swap($manager);
        $token = (string) Str::uuid();
        $log = EmailLog::factory()->queued()->create(['dispatch_token' => $token, 'mailer' => 'array', 'template' => 'mail001::delivery']);
        $injected = false;
        DB::connection()->beforeExecuting(function (string $sql, array $bindings) use (&$injected): void {
            if (! $injected && str_starts_with($sql, 'update') && str_contains($sql, 'email_logs') && in_array('sent', $bindings, true)) {
                $injected = true;
                throw new RuntimeException('Synthetic persistence failure after transport acceptance');
            }
        });
        $job = new ProcessEmailJob($log);
        try {
            $job->handle(app(EmailProviderMessageIdResolver::class));
            $this->fail('Persistence failure must surface after the owned transport accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic persistence failure after transport acceptance', $exception->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertCount(1, $transport->messages());
        $this->assertSame(EmailStatus::Processing, $log->fresh()->status);
        $this->assertFalse($log->fresh()->retry_safe);
        $job->failed(new RuntimeException('Late timeout'));
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $this->assertCount(1, $transport->messages());
        $this->artisan('email:recover', ['uuid' => $log->uuid, 'token' => $token, '--outcome' => 'sent', '--evidence' => 'INC-ARRAY-001', '--workers-stopped' => true])->assertSuccessful();
        $this->assertSame(EmailStatus::Sent, $log->fresh()->status);
        $this->assertCount(1, $transport->messages());
        Queue::assertNothingPushed();
    }

    private function dto(string $key): SendEmailDTO
    {
        return new SendEmailDTO(to: 'synthetic@example.test', subject: 'Fixture', template: 'mail001::delivery', category: EmailCategory::System, idempotencyKey: $key);
    }
}
