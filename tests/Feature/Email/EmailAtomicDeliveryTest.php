<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Actions\ResendEmailAction;
use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Email\DTOs\SendEmailDTO;
use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Exceptions\TemplateNotFoundException;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Mail\DynamicMailable;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use App\Supports\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EmailAtomicDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSettings::set('email', 'enabled', true);
        AppSettings::set('email', 'export_enabled', true);
        $directory = $this->testEnvironment->path.'/email-views';
        mkdir($directory);
        file_put_contents($directory.'/valid.blade.php', '<p>Hello {{ $name }}</p>');
        view()->addNamespace('atomic-email', $directory);
    }

    #[DataProvider('nonFailedStatuses')]
    public function test_queue_never_reuses_an_existing_non_failed_delivery(EmailStatus $status): void
    {
        $log = $this->log($status, ['idempotency_key' => 'same-delivery', 'retry_safe' => true]);
        $before = $log->fresh()->getRawOriginal();
        $this->assertNull(app(EmailServiceInterface::class)->queue($this->dto('same-delivery')));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertDatabaseCount('email_logs', 1);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_failed_delivery_requires_explicit_local_failure_evidence_to_retry(): void
    {
        $log = EmailLog::factory()->failed()->create(['idempotency_key' => 'unknown-failure']);
        $this->assertFalse($log->fresh()->retry_safe);
        $before = $log->fresh()->getRawOriginal();
        $this->assertNull(app(EmailServiceInterface::class)->queue($this->dto('unknown-failure')));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        try {
            app(ResendEmailAction::class)->handle($log);
            $this->fail('An unproven failed delivery must not be resent.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('log', $exception->errors());
        }
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        Queue::assertNothingPushed();
    }

    #[DataProvider('providerAcceptanceEvidence')]
    public function test_retry_safe_flag_cannot_override_provider_acceptance_evidence(?string $messageId, bool $sent): void
    {
        $log = $this->log(EmailStatus::Failed, [
            'idempotency_key' => 'accepted-before-failure', 'retry_safe' => true,
            'sent_at' => $sent ? now() : null, 'message_id' => $messageId,
        ]);
        $before = $log->fresh()->getRawOriginal();
        $this->assertNull(app(EmailServiceInterface::class)->queue($this->dto('accepted-before-failure')));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        try {
            app(ResendEmailAction::class)->handle($log);
            $this->fail('A delivery accepted by the provider must not be resent.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('log', $exception->errors());
        }
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        Queue::assertNothingPushed();
    }

    public function test_safe_local_failure_is_requeued_once_with_a_new_dispatch_token(): void
    {
        $log = $this->log(EmailStatus::Failed, [
            'idempotency_key' => 'safe-local-failure', 'retry_safe' => true,
            'error_message' => 'Template unavailable', 'failed_at' => now(),
        ]);
        $oldToken = $log->dispatch_token;
        $service = app(EmailServiceInterface::class);
        $requeued = $service->queue($this->dto('safe-local-failure'));
        $this->assertNotNull($requeued);
        $this->assertTrue($log->is($requeued));
        $this->assertSame(EmailStatus::Queued, $requeued->status);
        $this->assertFalse($requeued->retry_safe);
        $this->assertNull($requeued->failed_at);
        $this->assertNull($requeued->error_message);
        $this->assertTrue(Str::isUuid($requeued->dispatch_token));
        $this->assertNotSame($oldToken, $requeued->dispatch_token);
        $this->assertNull($service->queue($this->dto('safe-local-failure')));
        $this->assertDatabaseCount('email_logs', 1);
    }

    public function test_resend_rejects_a_stale_failed_model_after_another_request_requeues_it(): void
    {
        $log = $this->log(EmailStatus::Failed, ['retry_safe' => true]);
        $stale = $log->fresh();
        $action = app(ResendEmailAction::class);
        $requeued = $action->handle($log);
        $before = $requeued->fresh()->getRawOriginal();
        try {
            $action->handle($stale);
            $this->fail('A stale failed model must not enqueue a second delivery.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('log', $exception->errors());
        }
        $this->assertSame($before, $requeued->fresh()->getRawOriginal());
        $this->assertDatabaseCount('email_logs', 1);
        Mail::assertNothingOutgoing();
    }

    #[DataProvider('completedStatuses')]
    public function test_jobs_and_late_failure_callbacks_cannot_reopen_completed_deliveries(EmailStatus $status): void
    {
        $log = $this->log($status, ['attempts' => 3]);
        $before = $log->fresh()->getRawOriginal();
        $job = new ProcessEmailJob($log);
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $job->failed(new RuntimeException('Late worker failure'));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        Mail::assertNothingOutgoing();
    }

    public function test_processing_redelivery_cannot_send_or_increment_attempts(): void
    {
        $log = $this->log(EmailStatus::Processing, ['attempts' => 1]);
        $job = new ProcessEmailJob($log);
        $before = $log->fresh()->getRawOriginal();
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $job->failed(new RuntimeException('Timeout after provider entry'));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        Mail::assertNothingOutgoing();
    }

    #[DataProvider('interveningStatuses')]
    public function test_webhook_applied_during_rendering_aborts_before_entering_the_mailer(EmailStatus $status): void
    {
        $log = $this->log(EmailStatus::Queued);
        $afterWebhook = null;
        view()->composer('atomic-email::valid', function () use ($log, $status, &$afterWebhook): void {
            $log->markFromWebhook($status, ['intervened' => true]);
            $afterWebhook = $log->fresh()->getRawOriginal();
        });

        (new ProcessEmailJob($log))->handle(app(EmailProviderMessageIdResolver::class));

        $this->assertNotNull($afterWebhook);
        $this->assertSame($afterWebhook, $log->fresh()->getRawOriginal());
        $this->assertSame($status, $log->fresh()->status);
        $this->assertSame(1, $log->fresh()->attempts);
        Mail::assertNothingOutgoing();
    }

    /** @return iterable<string, array{EmailStatus}> */
    public static function interveningStatuses(): iterable
    {
        foreach ([EmailStatus::Suppressed, EmailStatus::Delivered, EmailStatus::Scheduled, EmailStatus::DeliveryDelayed, EmailStatus::Failed] as $status) {
            yield $status->value => [$status];
        }
    }

    public function test_legacy_job_without_dispatch_token_does_not_send(): void
    {
        $log = $this->log(EmailStatus::Queued, ['dispatch_token' => null]);
        $before = $log->fresh()->getRawOriginal();
        $job = new ProcessEmailJob($log);
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $job->failed(new RuntimeException('Legacy job failure'));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        Mail::assertNothingOutgoing();
    }

    public function test_serialized_job_keeps_original_token_after_the_log_is_requeued(): void
    {
        $log = $this->log(EmailStatus::Queued, ['idempotency_key' => 'old-worker']);
        $oldToken = $log->dispatch_token;
        $serialized = serialize(new ProcessEmailJob($log));
        $log->update(['status' => EmailStatus::Failed, 'retry_safe' => true]);
        $requeued = app(EmailServiceInterface::class)->queue($this->dto('old-worker'));
        $this->assertNotNull($requeued);
        $this->assertNotSame($oldToken, $requeued->dispatch_token);
        $oldJob = unserialize($serialized);
        $this->assertInstanceOf(ProcessEmailJob::class, $oldJob);
        $this->assertSame($oldToken, $oldJob->dispatchToken);
        $this->assertSame($requeued->dispatch_token, $oldJob->emailLog->dispatch_token);
        $before = $requeued->fresh()->getRawOriginal();
        $oldJob->handle(app(EmailProviderMessageIdResolver::class));
        $oldJob->failed(new RuntimeException('Failure from previous dispatch'));
        $this->assertSame($before, $requeued->fresh()->getRawOriginal());
        Mail::assertNothingOutgoing();
        (new ProcessEmailJob($requeued))->handle(app(EmailProviderMessageIdResolver::class));
        $this->assertSame(EmailStatus::Sent, $requeued->fresh()->status);
        $this->assertSame(1, $requeued->fresh()->attempts);
        Mail::assertSent(DynamicMailable::class, 1);
    }

    public function test_provider_acceptance_followed_by_local_failure_cannot_resend(): void
    {
        $log = $this->log(EmailStatus::Queued, ['idempotency_key' => 'ambiguous-result']);
        $resolver = $this->createMock(EmailProviderMessageIdResolver::class);
        $resolver->expects($this->once())->method('resolve')->willThrowException(new RuntimeException('Provider response unavailable'));
        $job = new ProcessEmailJob($log);
        try {
            $job->handle($resolver);
            $this->fail('Post-send failure must be surfaced to the worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Provider response unavailable', $exception->getMessage());
        }
        $fresh = $log->fresh();
        $this->assertSame(EmailStatus::Processing, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertFalse($fresh->retry_safe);
        $this->assertNotNull($fresh->error_message);
        Mail::assertSent(DynamicMailable::class, 1);
        $before = $fresh->getRawOriginal();
        $job->failed(new RuntimeException('Attempts exhausted after acceptance'));
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $this->assertNull(app(EmailServiceInterface::class)->queue($this->dto('ambiguous-result')));
        $this->assertSame($before, $log->fresh()->getRawOriginal());
        Mail::assertSent(DynamicMailable::class, 1);
    }

    public function test_invalid_template_stays_queued_until_terminal_callback_marks_failure_safe(): void
    {
        $log = $this->log(EmailStatus::Queued, ['template' => 'atomic-email::missing']);
        $job = new ProcessEmailJob($log);
        try {
            $job->handle(app(EmailProviderMessageIdResolver::class));
            $this->fail('Template validation must happen before entering the provider.');
        } catch (TemplateNotFoundException $exception) {
            $this->assertStringContainsString('atomic-email::missing', $exception->getMessage());
        }
        $fresh = $log->fresh();
        $this->assertSame(EmailStatus::Queued, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertNotNull($fresh->error_message);
        $this->assertNull($fresh->sent_at);
        $this->assertNull($fresh->message_id);
        Mail::assertNothingOutgoing();
        $job->failed(new RuntimeException('Template retries exhausted'));
        $fresh = $log->fresh();
        $this->assertSame(EmailStatus::Failed, $fresh->status);
        $this->assertTrue($fresh->retry_safe);
        $this->assertNotNull($fresh->failed_at);
        $this->assertSame('Template retries exhausted', $fresh->error_message);
    }

    public function test_successful_delivery_is_sent_once_and_preserves_reply_to(): void
    {
        $log = $this->log(EmailStatus::Queued, ['reply_to' => 'reply@example.test']);
        $job = new ProcessEmailJob($log);
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $job->handle(app(EmailProviderMessageIdResolver::class));
        $job->failed(new RuntimeException('Late failure after success'));
        $fresh = $log->fresh();
        $this->assertSame(EmailStatus::Sent, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertNotNull($fresh->sent_at);
        $this->assertFalse($fresh->retry_safe);
        Mail::assertSent(DynamicMailable::class, function (DynamicMailable $mail): bool {
            $this->assertStringContainsString('Hello Recipient', $mail->render());

            return $mail->hasReplyTo('reply@example.test');
        });
        Mail::assertSent(DynamicMailable::class, 1);
    }

    #[DataProvider('helperTransitions')]
    public function test_public_helpers_obey_the_shared_delivery_transition_contract(string $method, EmailStatus $initial, EmailStatus $expected): void
    {
        $log = $this->log($initial, ['metadata' => ['original' => true]]);
        $before = $log->fresh()->getRawOriginal();

        match ($method) {
            'sent' => $log->markAsSent('provider-message-123'),
            'failed' => $log->markAsFailed('Local template error'),
            'delivered' => $log->markAsDelivered(['provider' => 'delivered']),
            'bounced' => $log->markAsBounced(['provider' => 'bounced']),
        };

        $fresh = $log->fresh();
        $this->assertSame($expected, $fresh->status);
        if ($initial === $expected) {
            $this->assertSame($before, $fresh->getRawOriginal());
        } elseif ($method === 'failed') {
            $this->assertTrue($fresh->retry_safe);
            $this->assertNotNull($fresh->failed_at);
        } else {
            $this->assertFalse($fresh->retry_safe);
        }
    }

    #[DataProvider('webhookTransitions')]
    public function test_webhook_transition_is_monotonic_and_does_not_mutate_repeated_events(EmailStatus $initial, EmailStatus $incoming, EmailStatus $expected): void
    {
        $log = $this->log($initial, ['metadata' => ['original' => true]]);
        $before = $log->fresh()->getRawOriginal();

        $log->markFromWebhook($incoming, ['incoming' => true], 'Provider event');

        $fresh = $log->fresh();
        $this->assertSame($expected, $fresh->status);
        $this->assertFalse($fresh->retry_safe);
        if ($initial === $expected) {
            $this->assertSame($before, $fresh->getRawOriginal());
        } else {
            $this->assertSame(['incoming' => true], $fresh->metadata);
        }
    }

    public function test_late_provider_acceptance_revokes_permission_to_retry_a_local_failure(): void
    {
        $log = $this->log(EmailStatus::Queued, ['idempotency_key' => 'provider-failed']);
        $log->markAsFailed('Local failure');
        $this->assertTrue($log->fresh()->retry_safe);

        $log->markFromWebhook(EmailStatus::Sent, ['provider' => 'accepted']);

        $fresh = $log->fresh();
        $this->assertSame(EmailStatus::Sent, $fresh->status);
        $this->assertFalse($fresh->retry_safe);
        $this->assertNull(app(EmailServiceInterface::class)->queue($this->dto('provider-failed')));
        Mail::assertNothingOutgoing();
    }

    public function test_stale_processing_model_cannot_overwrite_a_webhook_that_already_delivered(): void
    {
        $log = $this->log(EmailStatus::Processing);
        $stale = $log->fresh();
        $log->markAsDelivered(['provider' => 'delivered']);
        $before = $log->fresh()->getRawOriginal();

        $stale->markAsSent('late-provider-message');
        $stale->markAsFailed('Late local error');
        $stale->markFromWebhook(EmailStatus::Sent, ['late' => true]);

        $this->assertSame($before, $log->fresh()->getRawOriginal());
    }

    /** @return iterable<string, array{string, EmailStatus, EmailStatus}> */
    public static function helperTransitions(): iterable
    {
        yield 'local sent after claim' => ['sent', EmailStatus::Processing, EmailStatus::Sent];
        yield 'local sent cannot bypass claim' => ['sent', EmailStatus::Queued, EmailStatus::Queued];
        yield 'local sent cannot reopen failure' => ['sent', EmailStatus::Failed, EmailStatus::Failed];
        yield 'local sent preserves delivery' => ['sent', EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'local sent preserves bounce' => ['sent', EmailStatus::Bounced, EmailStatus::Bounced];
        yield 'local sent preserves complaint' => ['sent', EmailStatus::Complained, EmailStatus::Complained];
        yield 'local failed before provider' => ['failed', EmailStatus::Queued, EmailStatus::Failed];
        yield 'local failed ambiguous provider' => ['failed', EmailStatus::Processing, EmailStatus::Processing];
        yield 'local failed preserves accepted send' => ['failed', EmailStatus::Sent, EmailStatus::Sent];
        yield 'local failed preserves delivery' => ['failed', EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'local failed preserves open' => ['failed', EmailStatus::Opened, EmailStatus::Opened];
        yield 'local failed preserves click' => ['failed', EmailStatus::Clicked, EmailStatus::Clicked];
        yield 'delivery progresses accepted send' => ['delivered', EmailStatus::Sent, EmailStatus::Delivered];
        yield 'late delivery resolves bounce' => ['delivered', EmailStatus::Bounced, EmailStatus::Delivered];
        yield 'delivery preserves click' => ['delivered', EmailStatus::Clicked, EmailStatus::Clicked];
        yield 'delivery preserves complaint' => ['delivered', EmailStatus::Complained, EmailStatus::Complained];
        yield 'bounce after send' => ['bounced', EmailStatus::Sent, EmailStatus::Bounced];
        yield 'bounce preserves delivery' => ['bounced', EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'bounce preserves open' => ['bounced', EmailStatus::Opened, EmailStatus::Opened];
        yield 'bounce preserves suppression' => ['bounced', EmailStatus::Suppressed, EmailStatus::Suppressed];
    }

    /** @return iterable<string, array{EmailStatus, EmailStatus, EmailStatus}> */
    public static function webhookTransitions(): iterable
    {
        yield 'queued accepts send evidence' => [EmailStatus::Queued, EmailStatus::Sent, EmailStatus::Sent];
        yield 'processing accepts delivery' => [EmailStatus::Processing, EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'scheduled accepts delivery' => [EmailStatus::Scheduled, EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'delayed accepts delivery' => [EmailStatus::DeliveryDelayed, EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'sent progresses delivery' => [EmailStatus::Sent, EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'delivery progresses open' => [EmailStatus::Delivered, EmailStatus::Opened, EmailStatus::Opened];
        yield 'open progresses click' => [EmailStatus::Opened, EmailStatus::Clicked, EmailStatus::Clicked];
        yield 'late acceptance cannot regress unproven provider failure' => [EmailStatus::Failed, EmailStatus::Sent, EmailStatus::Failed];
        yield 'late delivery resolves bounce' => [EmailStatus::Bounced, EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'delivery cannot regress sent' => [EmailStatus::Delivered, EmailStatus::Sent, EmailStatus::Delivered];
        yield 'delivery cannot regress delayed' => [EmailStatus::Delivered, EmailStatus::DeliveryDelayed, EmailStatus::Delivered];
        yield 'delivery cannot regress scheduled' => [EmailStatus::Delivered, EmailStatus::Scheduled, EmailStatus::Delivered];
        yield 'delivery cannot regress failed' => [EmailStatus::Delivered, EmailStatus::Failed, EmailStatus::Delivered];
        yield 'delivery cannot regress bounced' => [EmailStatus::Delivered, EmailStatus::Bounced, EmailStatus::Delivered];
        yield 'open cannot regress delivered' => [EmailStatus::Opened, EmailStatus::Delivered, EmailStatus::Opened];
        yield 'click cannot regress open' => [EmailStatus::Clicked, EmailStatus::Opened, EmailStatus::Clicked];
        yield 'click accepts suppression' => [EmailStatus::Clicked, EmailStatus::Suppressed, EmailStatus::Suppressed];
        yield 'failure accepts suppression' => [EmailStatus::Failed, EmailStatus::Suppressed, EmailStatus::Suppressed];
        yield 'bounce accepts complaint' => [EmailStatus::Bounced, EmailStatus::Complained, EmailStatus::Complained];
        yield 'suppression accepts complaint' => [EmailStatus::Suppressed, EmailStatus::Complained, EmailStatus::Complained];
        yield 'suppression blocks delivery' => [EmailStatus::Suppressed, EmailStatus::Delivered, EmailStatus::Suppressed];
        yield 'complaint blocks delivery' => [EmailStatus::Complained, EmailStatus::Delivered, EmailStatus::Complained];
        yield 'complaint blocks suppression' => [EmailStatus::Complained, EmailStatus::Suppressed, EmailStatus::Complained];
        yield 'received remains separate' => [EmailStatus::Received, EmailStatus::Sent, EmailStatus::Received];
        yield 'repeated delivery is immutable' => [EmailStatus::Delivered, EmailStatus::Delivered, EmailStatus::Delivered];
        yield 'repeated failure is immutable' => [EmailStatus::Failed, EmailStatus::Failed, EmailStatus::Failed];
        yield 'repeated complaint is immutable' => [EmailStatus::Complained, EmailStatus::Complained, EmailStatus::Complained];
    }

    /** @return iterable<string, array{EmailStatus}> */
    public static function nonFailedStatuses(): iterable
    {
        foreach (EmailStatus::cases() as $status) {
            if ($status !== EmailStatus::Failed) {
                yield $status->value => [$status];
            }
        }
    }

    /** @return iterable<string, array{EmailStatus}> */
    public static function completedStatuses(): iterable
    {
        foreach (EmailStatus::cases() as $status) {
            if (! in_array($status, [EmailStatus::Queued, EmailStatus::Processing], true)) {
                yield $status->value => [$status];
            }
        }
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function providerAcceptanceEvidence(): iterable
    {
        yield 'provider identifier only' => ['provider-accepted-123', false];
        yield 'sent timestamp only' => [null, true];
        yield 'both acceptance markers' => ['provider-accepted-123', true];
    }

    /** @param array<string, mixed> $attributes */
    private function log(EmailStatus $status, array $attributes = []): EmailLog
    {
        return EmailLog::factory()->create(array_merge([
            'status' => $status, 'attempts' => 0, 'dispatch_token' => (string) Str::uuid(),
            'retry_safe' => false, 'template' => 'atomic-email::valid',
            'variables' => ['name' => 'Recipient'], 'sent_at' => null, 'message_id' => null,
        ], $attributes));
    }

    private function dto(string $idempotencyKey): SendEmailDTO
    {
        return new SendEmailDTO(
            to: 'recipient@example.test', subject: 'Atomic delivery',
            template: 'atomic-email::valid', category: EmailCategory::Export,
            variables: ['name' => 'Recipient'], idempotencyKey: $idempotencyKey,
        );
    }
}
