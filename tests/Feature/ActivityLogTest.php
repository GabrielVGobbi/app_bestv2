<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_event_creates_activity_log(): void
    {
        $user = User::factory()->create();

        Auth::login($user);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'event' => 'login',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);
    }

    public function test_log_approved_creates_activity_log(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin);

        $user->logApproved($admin, ['reason' => 'Verified documents']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'approved',
            'event' => 'approved',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_type' => User::class,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_log_rejected_creates_activity_log(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin);

        $user->logRejected($admin);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'rejected',
            'event' => 'rejected',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_log_event_creates_activity_log_with_custom_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $user->logEvent('password_changed', ['method' => 'manual']);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'password_changed',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_user_model_creation_is_logged_automatically(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('activity_log', [
            'event' => 'created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_user_password_is_not_stored_in_activity_log_properties(): void
    {
        $user = User::factory()->create();

        $user->update(['password' => 'new-password']);

        $activity = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $properties = $activity->properties->toArray();

        $this->assertArrayNotHasKey('password', $properties['attributes'] ?? []);
        $this->assertArrayNotHasKey('password', $properties['old'] ?? []);
        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }
}
