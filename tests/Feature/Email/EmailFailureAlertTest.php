<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Models\User;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EmailFailureAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['permissions.super_role' => 'developer']);
    }

    public function test_safe_failure_alerts_only_currently_authorized_operators_without_disclosing_email_content(): void
    {
        $user = $this->actor('user');
        $admin = $this->actor('admin');
        $developer = $this->actor('developer');
        $otherDeveloper = $this->actor('developer');
        $this->assertFalse(Gate::forUser($user)->allows('view-email-logs', EmailLog::class));
        $this->assertFalse(Gate::forUser($admin)->allows('view-email-logs', EmailLog::class));
        $this->assertTrue(Gate::forUser($developer)->allows('view-email-logs', EmailLog::class));
        $this->assertTrue(Gate::forUser($otherDeveloper)->allows('view-email-logs', EmailLog::class));
        $log = $this->queuedLog();
        $job = new ProcessEmailJob($log);

        $job->failed(new RuntimeException('MAIL001_RAW_ERROR recipient-alert@example.test MAIL001_SECRET_VARIABLE MAIL001_PRIVATE_TEMPLATE'));

        $fresh = $log->fresh();
        $this->assertSame(EmailStatus::Failed, $fresh->status);
        $this->assertTrue($fresh->retry_safe);
        $this->assertNotNull($fresh->failed_at);
        $this->assertSame([$developer->id, $otherDeveloper->id], AppNotification::query()->orderBy('recipient_id')->pluck('recipient_id')->all());
        $this->assertDatabaseCount('app_notifications', 2);
        foreach (AppNotification::query()->get() as $notification) {
            $this->assertSame(NotificationType::SYSTEM, $notification->type);
            $this->assertSame(NotificationCategory::SYSTEM, $notification->category);
            $this->assertStringContainsString($log->uuid, $notification->body);
            $this->assertSame(route('email-logs.index', absolute: false), $notification->link);
            $this->assertNull($notification->data);
            $this->assertNull($notification->actions);
            $payload = json_encode($notification->getAttributes(), JSON_THROW_ON_ERROR);
            foreach ([
                'recipient-alert@example.test', 'sender-alert@example.test',
                'MAIL001_PRIVATE_SUBJECT', 'MAIL001_PRIVATE_TEMPLATE',
                'MAIL001_SECRET_VARIABLE', 'MAIL001_PRIVATE_METADATA',
                'MAIL001_PRIVATE_IDEMPOTENCY', 'MAIL001_RAW_ERROR',
            ] as $secret) {
                $this->assertStringNotContainsString($secret, $payload);
            }
        }
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_repeated_failure_callback_creates_one_alert_and_preserves_the_first_failure(): void
    {
        $developer = $this->actor('developer');
        $log = $this->queuedLog();
        $job = new ProcessEmailJob($log);
        $job->failed(new RuntimeException('Original local failure'));
        $before = $log->fresh()->getRawOriginal();
        $notification = AppNotification::query()->sole()->getRawOriginal();

        $job->failed(new RuntimeException('Repeated callback with a different exception'));

        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertSame($notification, AppNotification::query()->sole()->getRawOriginal());
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $developer->id]);
        $this->assertDatabaseCount('app_notifications', 1);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    #[DataProvider('nonQueuedStatuses')]
    public function test_late_failure_callback_does_not_change_delivery_or_notify_operators(EmailStatus $status): void
    {
        $this->actor('developer');
        $log = $this->queuedLog();
        $job = new ProcessEmailJob($log);
        $log->update(['status' => $status, 'metadata' => ['provider' => 'current-event']]);
        $before = $log->fresh()->getRawOriginal();

        $job->failed(new RuntimeException('Late obsolete local error'));

        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertDatabaseCount('app_notifications', 0);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_webhook_winning_before_the_failure_callback_prevents_a_failure_alert(): void
    {
        $this->actor('developer');
        $log = $this->queuedLog();
        $job = new ProcessEmailJob($log);
        $log->markFromWebhook(EmailStatus::Delivered, ['provider' => 'delivery-confirmed']);
        $this->assertSame(EmailStatus::Delivered, $log->fresh()->status);
        $before = $log->fresh()->getRawOriginal();

        $job->failed(new RuntimeException('Local callback after provider delivery'));

        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertDatabaseCount('app_notifications', 0);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_failure_from_an_old_dispatch_token_cannot_alert_or_change_the_current_generation(): void
    {
        $this->actor('developer');
        $log = $this->queuedLog();
        $job = new ProcessEmailJob($log);
        $log->update(['dispatch_token' => (string) Str::uuid()]);
        $before = $log->fresh()->getRawOriginal();

        $job->failed(new RuntimeException('Old dispatch error'));

        $this->assertSame($before, $log->fresh()->getRawOriginal());
        $this->assertDatabaseCount('app_notifications', 0);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_no_first_account_or_admin_fallback_is_used_when_no_operator_can_read_logs(): void
    {
        $firstAccount = $this->actor('user');
        $admin = $this->actor('admin');
        $this->assertFalse(Gate::forUser($firstAccount)->allows('view-email-logs', EmailLog::class));
        $this->assertFalse(Gate::forUser($admin)->allows('view-email-logs', EmailLog::class));
        $log = $this->queuedLog();

        (new ProcessEmailJob($log))->failed(new RuntimeException('No eligible operator'));

        $this->assertSame(EmailStatus::Failed, $log->fresh()->status);
        $this->assertTrue($log->fresh()->retry_safe);
        $this->assertDatabaseCount('app_notifications', 0);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_developer_role_alone_does_not_replace_the_current_authority_gate(): void
    {
        $developer = $this->actor('developer');
        config(['permissions.super_role' => 'admin']);
        $this->assertFalse(Gate::forUser($developer)->allows('view-email-logs', EmailLog::class));
        $log = $this->queuedLog();

        (new ProcessEmailJob($log))->failed(new RuntimeException('Authority configuration rejected access'));

        $this->assertSame(EmailStatus::Failed, $log->fresh()->status);
        $this->assertTrue($log->fresh()->retry_safe);
        $this->assertDatabaseCount('app_notifications', 0);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    /** @return iterable<string, array{EmailStatus}> */
    public static function nonQueuedStatuses(): iterable
    {
        foreach (EmailStatus::cases() as $status) {
            if ($status !== EmailStatus::Queued) {
                yield $status->value => [$status];
            }
        }
    }

    private function actor(string $slug): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
        $actor = User::factory()->create();
        $actor->roles()->sync([$role->id]);

        return $actor;
    }

    private function queuedLog(): EmailLog
    {
        return EmailLog::factory()->queued()->create([
            'dispatch_token' => (string) Str::uuid(),
            'retry_safe' => false,
            'to_email' => 'recipient-alert@example.test',
            'from_email' => 'sender-alert@example.test',
            'subject' => 'MAIL001_PRIVATE_SUBJECT',
            'template' => 'MAIL001_PRIVATE_TEMPLATE',
            'variables' => ['token' => 'MAIL001_SECRET_VARIABLE'],
            'metadata' => ['provider' => 'MAIL001_PRIVATE_METADATA'],
            'idempotency_key' => 'MAIL001_PRIVATE_IDEMPOTENCY',
            'message_id' => null,
            'sent_at' => null,
        ]);
    }
}
