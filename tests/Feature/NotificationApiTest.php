<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Requests\ExecuteNotificationActionRequest;
use App\Models\Export;
use App\Models\User;
use App\Modules\Notifications\Actions\Executable\ApproveRequestNotificationAction;
use App\Modules\Notifications\Actions\Executable\Contracts\NotificationExecutableAction;
use App\Modules\Notifications\Actions\Executable\RejectRequestNotificationAction;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Services\NotificationActionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
    }

    private function createNotification(User $recipient, array $overrides = []): AppNotification
    {
        return AppNotification::create(array_merge([
            'recipient_id' => $recipient->id,
            'sender_id' => null,
            'type' => NotificationType::SYSTEM->value,
            'category' => NotificationCategory::GENERAL->value,
            'title' => 'Test notification',
            'body' => 'Test body',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/notifications
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_notifications_for_authenticated_user(): void
    {
        $this->createNotification($this->user);
        $this->createNotification($this->user);
        $this->createNotification($this->other); // belongs to another user

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_notifications_with_equal_timestamps_have_stable_descending_id_order_across_pages(): void
    {
        $ids = [];
        for ($index = 0; $index < 17; $index++) {
            $ids[] = $this->createNotification($this->user, [
                'created_at' => '2026-01-01 12:00:00',
                'updated_at' => '2026-01-01 12:00:00',
            ])->uuid;
        }
        $this->createNotification($this->other, ['created_at' => '2026-01-01 12:00:00']);
        $expected = array_reverse($ids);

        $first = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/notifications?page=1');
        $second = $this->getJson('/api/v1/notifications?page=2');
        $first->assertOk()->assertJsonPath('meta.total', 17)->assertJsonPath('meta.last_page', 2);
        $second->assertOk()->assertJsonPath('meta.current_page', 2);
        $this->assertSame($expected, array_merge(array_column($first->json('data'), 'uuid'), array_column($second->json('data'), 'uuid')));
        $this->assertSame(array_slice($expected, 0, 10), array_column($this->getJson('/api/v1/notifications/unread')->assertOk()->json('data'), 'uuid'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_index_does_not_return_other_users_notifications(): void
    {
        $this->createNotification($this->other);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_filters_notifications_by_type_and_read_status(): void
    {
        $this->createNotification($this->user, [
            'type' => NotificationType::WARNING->value,
        ]);
        $this->createNotification($this->user, [
            'type' => NotificationType::WARNING->value,
            'read_at' => now(),
        ]);
        $this->createNotification($this->user, [
            'type' => NotificationType::SYSTEM->value,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications?type=warning&read=false');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', NotificationType::WARNING->value)
            ->assertJsonPath('data.0.is_read', false);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/notifications/unread-count
    // -------------------------------------------------------------------------

    public function test_unread_count_returns_correct_number(): void
    {
        $this->createNotification($this->user);
        $this->createNotification($this->user);
        $this->createNotification($this->user, ['read_at' => now()]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()->assertJson(['count' => 2]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/notifications/unread
    // -------------------------------------------------------------------------

    public function test_unread_returns_only_unread_notifications(): void
    {
        $this->createNotification($this->user);
        $this->createNotification($this->user, ['read_at' => now()]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications/unread');

        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------------------
    // PATCH /api/v1/notifications/{uuid}/read
    // -------------------------------------------------------------------------

    public function test_mark_as_read_updates_notification(): void
    {
        $notification = $this->createNotification($this->user);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->uuid}");

        $response->assertOk()->assertJsonPath('data.is_read', true);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_rejects_other_user_notification(): void
    {
        $notification = $this->createNotification($this->other);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->uuid}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/notifications/mark-all-read
    // -------------------------------------------------------------------------

    public function test_mark_all_read_only_affects_current_user(): void
    {
        $n1 = $this->createNotification($this->user);
        $n2 = $this->createNotification($this->user);
        $otherNotification = $this->createNotification($this->other);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/notifications/mark-all-read')
            ->assertOk();

        // current user's notifications must be read
        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNotNull($n2->fresh()->read_at);

        // other user's notification must still be unread
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/notifications/{uuid}
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_notification(): void
    {
        $notification = $this->createNotification($this->user);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notification->uuid}")
            ->assertNoContent();

        $this->assertModelMissing($notification);
    }

    public function test_destroy_rejects_other_user_notification(): void
    {
        $notification = $this->createNotification($this->other);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notification->uuid}")
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/notifications/{uuid}/execute-action
    // -------------------------------------------------------------------------

    public function test_execute_action_rejects_notification_without_actions(): void
    {
        $notification = $this->createNotification($this->user);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
                'action_type' => 'approve_request',
            ])
            ->assertStatus(422);
    }

    public function test_execute_action_rejects_already_executed_notification(): void
    {
        $notification = $this->createNotification($this->user, [
            'actions' => [['label' => 'Approve', 'type' => 'approve_request', 'variant' => 'primary']],
            'action_status' => 'executed',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
                'action_type' => 'approve_request',
            ])
            ->assertStatus(409);
    }

    public function test_execute_action_rejects_other_user_notification(): void
    {
        $notification = $this->createNotification($this->other, [
            'actions' => [['label' => 'Approve', 'type' => 'approve_request', 'variant' => 'primary']],
            'action_status' => 'pending',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
                'action_type' => 'approve_request',
            ])
            ->assertForbidden();
    }

    public function test_execute_action_refuses_the_unimplemented_reject_request_example(): void
    {
        $notification = $this->createNotification($this->user, [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'actions' => [['label' => 'Reject', 'type' => 'reject_request', 'variant' => 'destructive']],
            'action_status' => 'pending',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
                'action_type' => 'reject_request',
            ])
            ->assertStatus(422);

        $this->assertSame('pending', $notification->refresh()->action_status);
    }

    public function test_execute_action_refuses_the_unimplemented_approve_request_example(): void
    {
        $notification = $this->createNotification($this->user, [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'actions' => [['label' => 'Approve', 'type' => 'approve_request', 'variant' => 'primary']],
            'action_status' => 'pending',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
                'action_type' => 'approve_request',
            ])
            ->assertStatus(422);

        $this->assertSame('pending', $notification->refresh()->action_status);
    }

    #[DataProvider('exampleActions')]
    public function test_unimplemented_example_classes_refuse_direct_execution(string $class): void
    {
        [$notification, $probe] = $this->probeNotification();

        try {
            app($class)->execute($notification, $this->user);
            $this->fail('An unimplemented example must refuse direct execution.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('pending', $notification->fresh()->action_status);
        $this->assertSame(0, $probe->fresh()->executions);
    }

    /** @return array<string, array{class-string<NotificationExecutableAction>}> */
    public static function exampleActions(): array
    {
        return ['approve' => [ApproveRequestNotificationAction::class], 'reject' => [RejectRequestNotificationAction::class]];
    }

    public function test_execute_action_refuses_a_missing_registered_handler_without_effects(): void
    {
        [$notification, $probe] = $this->probeNotification('unregistered_probe');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
            'action_type' => 'unregistered_probe',
        ])->assertStatus(422);

        $this->assertSame('pending', $notification->fresh()->action_status);
        $this->assertSame(0, $probe->fresh()->executions);
    }

    #[DataProvider('realActions')]
    public function test_explicit_real_handler_commits_the_business_effect_and_terminal_status(string $action, string $terminal): void
    {
        [$notification, $probe] = $this->probeNotification($action);
        $this->registerProbeAction($action);

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
            'action_type' => $action, 'payload' => ['reason' => 'Synthetic local decision'],
        ])->assertOk()->assertJsonPath('action_type', $action)->assertJsonPath('result.executions', 1)
            ->assertJsonPath('result.payload.reason', 'Synthetic local decision');

        $this->assertSame(1, $probe->fresh()->executions);
        $this->assertSame($terminal, $notification->fresh()->action_status);
    }

    /** @return array<string, array{string, string}> */
    public static function realActions(): array
    {
        return ['custom' => ['probe_action', 'executed'], 'explicit approve' => ['approve_request', 'executed'], 'explicit reject' => ['reject_request', 'rejected']];
    }

    public function test_execute_action_normalizes_a_nullable_payload_for_a_real_handler(): void
    {
        [$notification, $probe] = $this->probeNotification();
        $this->registerProbeAction();

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
            'action_type' => 'probe_action', 'payload' => null,
        ])->assertOk()->assertJsonPath('result.payload', []);

        $this->assertSame(1, $probe->fresh()->executions);
        $this->assertSame('executed', $notification->fresh()->action_status);
    }

    public function test_real_handler_refuses_a_missing_entity_without_terminal_transition(): void
    {
        [$notification, $probe] = $this->probeNotification();
        $this->registerProbeAction();
        $probe->delete();

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
            'action_type' => 'probe_action',
        ])->assertNotFound();

        $this->assertSame('pending', $notification->fresh()->action_status);
        $this->assertDatabaseCount('ntf_action_probes', 0);
    }

    public function test_real_handler_enforces_the_related_entity_policy(): void
    {
        [$notification, $probe] = $this->probeNotification();
        $this->registerProbeAction();
        $probe->update(['recipient_id' => $this->other->id]);

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
            'action_type' => 'probe_action',
        ])->assertForbidden();

        $this->assertSame(0, $probe->fresh()->executions);
        $this->assertSame('pending', $notification->fresh()->action_status);
    }

    public function test_handler_exception_rolls_back_its_business_mutation(): void
    {
        [$notification, $probe] = $this->probeNotification();
        $this->registerProbeAction();

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
            'action_type' => 'probe_action', 'payload' => ['fail_after_mutation' => true],
        ])->assertStatus(500);

        $this->assertSame(0, $probe->fresh()->executions);
        $this->assertSame('pending', $notification->fresh()->action_status);
    }

    public function test_terminal_state_write_failure_rolls_back_the_handler_effect(): void
    {
        [$notification, $probe] = $this->probeNotification();
        $this->registerProbeAction();
        $dispatcher = AppNotification::getEventDispatcher();
        $events = clone $dispatcher;
        AppNotification::setEventDispatcher($events);
        $events->listen('eloquent.updating: '.AppNotification::class, function (AppNotification $updated) use ($notification): void {
            if ($updated->id === $notification->id && $updated->isDirty('action_status')) {
                throw new RuntimeException('NTF002 controlled terminal write failure');
            }
        });

        try {
            $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
                'action_type' => 'probe_action',
            ])->assertStatus(500);
        } finally {
            AppNotification::setEventDispatcher($dispatcher);
        }

        $this->assertSame(0, $probe->fresh()->executions);
        $this->assertSame('pending', $notification->fresh()->action_status);
    }

    #[DataProvider('vetoEvents')]
    public function test_terminal_state_veto_rolls_back_the_handler_effect(string $event): void
    {
        [$notification, $probe] = $this->probeNotification();
        $this->registerProbeAction();
        $dispatcher = AppNotification::getEventDispatcher();
        $events = clone $dispatcher;
        AppNotification::setEventDispatcher($events);
        $events->listen('eloquent.'.$event.': '.AppNotification::class, function (AppNotification $updated) use ($notification): ?bool {
            return $updated->id === $notification->id && $updated->isDirty('action_status') ? false : null;
        });

        try {
            $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
                'action_type' => 'probe_action',
            ])->assertStatus(500);
        } finally {
            AppNotification::setEventDispatcher($dispatcher);
        }

        $this->assertSame(0, $probe->fresh()->executions);
        $this->assertSame('pending', $notification->fresh()->action_status);
    }

    /** @return array<string, array{string}> */
    public static function vetoEvents(): array
    {
        return ['saving observer' => ['saving'], 'updating observer' => ['updating']];
    }

    #[DataProvider('terminalStatuses')]
    public function test_terminal_notification_never_reenters_the_real_handler(string $status): void
    {
        [$notification, $probe] = $this->probeNotification();
        $this->registerProbeAction();
        $notification->update(['action_status' => $status]);

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
            'action_type' => 'probe_action',
        ])->assertStatus(409);

        $this->assertSame(0, $probe->fresh()->executions);
        $this->assertSame($status, $notification->fresh()->action_status);
    }

    /** @return array<string, array{string}> */
    public static function terminalStatuses(): array
    {
        return ['executed' => ['executed'], 'rejected' => ['rejected'], 'cancelled' => ['cancelled'], 'expired' => ['expired']];
    }

    /** @param 'recipient'|'actions'|'action-type'|'terminal'|'entity'|'entity-type' $change */
    #[DataProvider('staleNotificationChanges')]
    public function test_route_bound_notification_is_revalidated_from_persisted_state(string $change, int $status): void
    {
        [$notification, $probe] = $this->probeNotification();
        $this->registerProbeAction();
        $stale = $notification->fresh();
        $updates = match ($change) {
            'recipient' => ['recipient_id' => $this->other->id],
            'actions' => ['actions' => []],
            'action-type' => ['actions' => [['label' => 'Other', 'type' => 'other_action']]],
            'terminal' => ['action_status' => 'cancelled'],
            'entity' => ['notifiable_id' => $probe->id + 1000],
            'entity-type' => ['notifiable_type' => User::class],
        };
        $notification->update($updates);
        $before = $notification->fresh()->getRawOriginal();
        $request = ExecuteNotificationActionRequest::create('/api/v1/notifications/'.$notification->uuid.'/execute-action', 'POST', ['action_type' => 'probe_action']);
        $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
        $request->setUserResolver(fn (): User => $this->user);
        $request->validateResolved();

        try {
            app(NotificationApiController::class)->executeAction($request, $stale);
            $this->fail('The controller must reject persisted changes even when route binding is stale.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(404, $status);
        }

        $this->assertSame(0, $probe->fresh()->executions);
        $this->assertSame($before, $notification->fresh()->getRawOriginal());
    }

    /** @return array<string, array{string, int}> */
    public static function staleNotificationChanges(): array
    {
        return ['recipient' => ['recipient', 403], 'actions removed' => ['actions', 422], 'action removed' => ['action-type', 422],
            'terminal' => ['terminal', 409], 'entity missing' => ['entity', 404], 'entity type changed' => ['entity-type', 422]];
    }

    public function test_download_export_keeps_its_owner_check_and_returns_the_download_link(): void
    {
        $export = Export::factory()->completed()->create(['user_id' => $this->user->id]);
        $notification = $this->createNotification($this->user, [
            'data' => ['export_uuid' => $export->uuid], 'actions' => [['label' => 'Download', 'type' => 'download_export']], 'action_status' => 'pending',
        ]);

        $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/notifications/{$notification->uuid}/execute-action", [
            'action_type' => 'download_export',
        ])->assertOk()->assertJsonPath('result.download_url', route('exports.download', $export->uuid));
        $this->assertSame('executed', $notification->fresh()->action_status);

        $otherExport = Export::factory()->completed()->create(['user_id' => $this->other->id]);
        $foreign = $this->createNotification($this->user, [
            'data' => ['export_uuid' => $otherExport->uuid], 'actions' => [['label' => 'Download', 'type' => 'download_export']], 'action_status' => 'pending',
        ]);
        $this->postJson("/api/v1/notifications/{$foreign->uuid}/execute-action", ['action_type' => 'download_export'])->assertForbidden();
        $this->assertSame('pending', $foreign->fresh()->action_status);
    }

    /** @return array{AppNotification, NotificationActionProbe} */
    private function probeNotification(string $action = 'probe_action'): array
    {
        Schema::create('ntf_action_probes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('recipient_id');
            $table->unsignedInteger('executions')->default(0);
        });
        $probe = NotificationActionProbe::create(['recipient_id' => $this->user->id, 'executions' => 0]);
        $notification = $this->createNotification($this->user, [
            'notifiable_type' => NotificationActionProbe::class, 'notifiable_id' => $probe->id,
            'actions' => [['label' => 'Synthetic action', 'type' => $action]], 'action_status' => 'pending',
        ]);

        return [$notification, $probe];
    }

    private function registerProbeAction(string $action = 'probe_action'): void
    {
        Gate::define('execute-notification-probe', fn (User $user, NotificationActionProbe $probe): bool => $user->id === $probe->recipient_id);
        app(NotificationActionResolver::class)->register($action, NotificationActionProbeAction::class);
    }
}

/**
 * @property int $id
 * @property int $recipient_id
 * @property int $executions
 */
class NotificationActionProbe extends Model
{
    protected $table = 'ntf_action_probes';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['recipient_id' => 'integer', 'executions' => 'integer'];
    }
}

class NotificationActionProbeAction implements NotificationExecutableAction
{
    public function execute(AppNotification $notification, User $user, array $payload = []): mixed
    {
        abort_unless($notification->notifiable_type === NotificationActionProbe::class, 422);
        $probe = (new NotificationActionProbe)->setConnection($notification->getConnectionName())->newQuery()->findOrFail($notification->notifiable_id);
        Gate::forUser($user)->authorize('execute-notification-probe', $probe);
        $probe->increment('executions');
        if ($payload['fail_after_mutation'] ?? false) {
            throw new RuntimeException('NTF002 controlled handler failure');
        }

        return ['executions' => $probe->fresh()->executions, 'payload' => $payload];
    }
}
