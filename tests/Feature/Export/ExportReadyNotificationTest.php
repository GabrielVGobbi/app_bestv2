<?php

namespace Tests\Feature\Export;

use App\Events\NotificationCreated;
use App\Jobs\ProcessExport;
use App\Models\Export;
use App\Models\User;
use App\Modules\Email\Contracts\EmailServiceInterface;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Notifications\ExportReadyNotification;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExportReadyNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        Storage::fake('local');
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        Event::fake([NotificationCreated::class]);
        config(['permissions.super_role' => 'developer']);
        $this->mock(EmailServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('queue');
            $mock->shouldNotReceive('sendNow');
        });
    }

    public function test_completed_export_is_written_to_the_application_notification_center_only(): void
    {
        [$owner, $export] = $this->pendingExport();

        (new ProcessExport($export))->handle();

        $export->refresh();
        $this->assertSame(Export::STATUS_COMPLETED, $export->status);
        $this->assertDatabaseCount('app_notifications', 1);
        $notice = AppNotification::query()->sole();
        $this->assertExportNotice($notice, $owner, $export);
        $this->assertSame(1, $owner->unreadNotificationsCount());
        $this->assertSame($notice->getKey(), $owner->appNotifications()->sole()->getKey());
        Event::assertDispatchedTimes(NotificationCreated::class, 1);
        Event::assertDispatched(NotificationCreated::class, fn (NotificationCreated $event): bool => $event->notification->id === $notice->id
            && $event->notification->recipient_id === $owner->id
            && $event->broadcastOn()[0]->name === 'private-user.'.$owner->id.'.notifications');
        $this->assertNoLegacyOrExternalDelivery();
    }

    public function test_only_the_owner_can_see_the_export_in_the_bell_page_api_and_download_its_bytes(): void
    {
        [$owner, $export] = $this->pendingExport();
        $other = User::factory()->create();
        (new ProcessExport($export))->handle();
        $export->refresh();
        $this->assertDatabaseCount('app_notifications', 1);
        $notice = AppNotification::query()->sole();

        $this->actingAs($owner, 'web')->getJson(route('api.notifications.index'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $notice->uuid)
            ->assertJsonPath('data.0.category', 'export')->assertJsonPath('data.0.link', route('exports.download', $export->uuid));
        $this->getJson(route('api.notifications.unread'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $notice->uuid);
        $this->getJson(route('api.notifications.unread-count'))->assertOk()->assertJsonPath('count', 1);
        $this->get(route('notifications.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('notifications/index'));
        $download = $this->get($notice->link)->assertOk()->assertDownload($export->file_name);
        $bytes = $download->streamedContent();
        $this->assertSame(Storage::disk('local')->get($export->file_path), $bytes);
        $this->assertStringContainsString($owner->email, $bytes);

        $this->app['auth']->forgetGuards();
        $this->actingAs($other, 'web')->getJson(route('api.notifications.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('api.notifications.unread'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('api.notifications.unread-count'))->assertOk()->assertJsonPath('count', 0);
        $this->get(route('notifications.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('notifications/index'));
        $this->get($notice->link)->assertForbidden();
        $this->patchJson(route('api.notifications.mark-read', $notice->uuid))->assertForbidden();
        $this->postJson(route('api.notifications.execute-action', $notice->uuid), ['action_type' => 'download_export'])->assertForbidden();
        $this->assertSame(0, $other->appNotifications()->count());
        $this->assertSame(1, AppNotification::query()->count());
        $this->assertNoLegacyOrExternalDelivery();
    }

    public function test_redelivering_a_completed_export_does_not_duplicate_the_notice_or_file(): void
    {
        [$owner, $export] = $this->pendingExport();
        $job = new ProcessExport($export);
        $job->handle();
        $export->refresh();
        $this->assertDatabaseCount('app_notifications', 1);
        $notice = AppNotification::query()->sole()->getRawOriginal();
        $artifact = Storage::disk('local')->get($export->file_path);
        $completed = $export->getRawOriginal();

        $job->handle();
        (new ProcessExport($export->fresh()))->handle();

        $this->assertSame($completed, $export->fresh()->getRawOriginal());
        $this->assertSame($notice, AppNotification::query()->sole()->getRawOriginal());
        $this->assertSame(1, $owner->appNotifications()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame($artifact, Storage::disk('local')->get($export->file_path));
        Event::assertDispatchedTimes(NotificationCreated::class, 1);
        $this->assertNoLegacyOrExternalDelivery();
    }

    #[DataProvider('unsuccessfulExports')]
    /** @return array<string, array{string}> */
    public static function unsuccessfulExports(): array
    {
        return ['terminal failure' => ['failed'], 'authorization revoked' => ['access-revoked']];
    }

    #[DataProvider('invalidChannelExports')]
    public function test_ready_delivery_rejects_an_invalid_or_stale_export_without_writes(string $change): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $export = Export::factory()->completed()->create(['user_id' => $owner->id]);
        $notification = new ExportReadyNotification($export);
        $recipient = $owner;
        if ($change === 'wrong-owner') {
            $recipient = $other;
        } elseif ($change === 'missing') {
            $export->delete();
        } elseif ($change === 'owner-changed') {
            Export::whereKey($export->id)->update(['user_id' => $other->id]);
        } else {
            Export::whereKey($export->id)->update(['status' => $change]);
        }

        try {
            $recipient->notify($notification);
            $this->fail('Ready delivery must require a persisted completed export owned by its recipient.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('app_notifications', 0);
        }

        Event::assertNotDispatched(NotificationCreated::class);
        $this->assertNoLegacyOrExternalDelivery();
    }

    /** @return array<string, array{string}> */
    public static function invalidChannelExports(): array
    {
        return ['wrong recipient' => ['wrong-owner'], 'deleted export' => ['missing'], 'owner changed in database' => ['owner-changed'],
            'pending in database' => ['pending'], 'processing in database' => ['processing'], 'failed in database' => ['failed']];
    }

    public function test_ready_channel_rejects_a_non_user_recipient(): void
    {
        $owner = User::factory()->create();
        $export = Export::factory()->completed()->create(['user_id' => $owner->id]);
        $notification = new ExportReadyNotification($export);
        $channel = app(ChannelManager::class)->driver($notification->via($owner)[0]);

        try {
            $channel->send(new \stdClass, $notification);
            $this->fail('Ready delivery must require a User recipient.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('app_notifications', 0);
        }

        Event::assertNotDispatched(NotificationCreated::class);
        $this->assertNoLegacyOrExternalDelivery();
    }

    public function test_ready_channel_uses_fresh_persisted_export_fields_instead_of_the_notification_snapshot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $export = Export::factory()->completed()->create(['user_id' => $owner->id]);
        $persisted = $export->fresh();
        $notification = new ExportReadyNotification($export);
        $export->forceFill(['status' => Export::STATUS_FAILED, 'user_id' => $other->id, 'file_name' => 'UNSAVED secret.xlsx', 'format' => 'xlsx', 'total_records' => 999]);

        $owner->notify($notification);

        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertExportNotice(AppNotification::query()->sole(), $owner, $persisted);
        $this->assertSame(0, $other->appNotifications()->count());
        $this->assertSame($persisted->getRawOriginal(), $persisted->fresh()->getRawOriginal());
        Event::assertDispatchedTimes(NotificationCreated::class, 1);
        $this->assertNoLegacyOrExternalDelivery();
    }

    public function test_broadcast_unavailability_preserves_the_persisted_export_notice(): void
    {
        [$owner, $export] = $this->pendingExport();
        Broadcast::shouldReceive('event')->once()->with(Mockery::type(NotificationCreated::class))
            ->andThrow(new BroadcastException('NTF001 controlled realtime unavailability'));

        (new ProcessExport($export))->handle();

        $export->refresh();
        $this->assertSame(Export::STATUS_COMPLETED, $export->status);
        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertExportNotice(AppNotification::query()->sole(), $owner, $export);
        $this->assertNoLegacyOrExternalDelivery();
    }

    /** @return array{User, Export} */
    private function pendingExport(): array
    {
        $owner = User::factory()->create(['name' => 'NTF001 export source', 'email' => 'ntf001-owner@example.test']);
        $owner->roles()->attach(Role::firstOrCreate(['slug' => 'developer'], ['name' => 'developer']));
        $owner->permissions()->attach(Permission::firstOrCreate(['slug' => 'view-users'], ['name' => 'view-users']));

        return [$owner, Export::factory()->create([
            'user_id' => $owner->id, 'model_class' => User::class, 'format' => 'csv', 'columns' => ['name', 'email'],
            'filters' => [], 'search' => 'NTF001 export source', 'sort_column' => 'id', 'sort_direction' => 'asc',
        ])];
    }

    private function assertExportNotice(AppNotification $notice, User $owner, Export $export): void
    {
        $this->assertSame($owner->id, $notice->recipient_id);
        $this->assertNull($notice->sender_id);
        $this->assertSame(NotificationType::SUCCESS, $notice->type);
        $this->assertSame(NotificationCategory::EXPORT, $notice->category);
        $this->assertSame('Exportação concluída', $notice->title);
        $this->assertSame('Sua exportação está pronta para download.', $notice->body);
        $this->assertSame(Export::class, $notice->notifiable_type);
        $this->assertSame($export->id, $notice->notifiable_id);
        $this->assertSame(route('exports.download', $export->uuid), $notice->link);
        $this->assertSame(['export_uuid' => $export->uuid, 'file_name' => $export->file_name, 'format' => $export->format, 'total_records' => $export->total_records], $notice->data);
        $this->assertNull($notice->actions);
        $this->assertNull($notice->action_status);
        $this->assertNull($notice->read_at);
    }

    private function assertNoLegacyOrExternalDelivery(): void
    {
        if (Schema::hasTable('notifications')) {
            $this->assertDatabaseCount('notifications', 0);
        }
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}
