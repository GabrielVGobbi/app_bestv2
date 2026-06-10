<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    public function test_execute_action_marks_reject_request_as_rejected(): void
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
            ->assertOk()
            ->assertJsonPath('action_type', 'reject_request');

        $this->assertSame('rejected', $notification->refresh()->action_status);
    }

    public function test_execute_action_marks_approve_request_as_executed(): void
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
            ->assertOk()
            ->assertJsonPath('action_type', 'approve_request');

        $this->assertSame('executed', $notification->refresh()->action_status);
    }
}
