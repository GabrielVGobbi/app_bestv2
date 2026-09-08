<?php

namespace Tests\Feature\Email;

use App\Models\User;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Permissions\Models\Role;
use App\Supports\AppSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailLogControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->roles()->attach(Role::factory()->create(['slug' => 'admin']));
    }

    public function test_index_renders_for_administrators(): void
    {
        EmailLog::factory(3)->create();

        $response = $this->actingAs($this->user)->get('/email-logs');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('app/email-logs/index')
                ->has('logs.data', 3)
                ->has('statuses')
                ->has('categories')
                ->has('settings')
            );
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/email-logs')->assertRedirect('/login');
    }

    public function test_regular_users_cannot_read_resend_or_configure_email_logs(): void
    {
        Queue::fake();
        $log = EmailLog::factory()->create(['status' => EmailStatus::Failed]);
        $this->actingAs(User::factory()->create());

        $this->get('/email-logs')->assertForbidden();
        $this->post("/email-logs/{$log->uuid}/resend")->assertForbidden();
        $this->patch('/email-logs/settings', ['enabled' => false])->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertSame(EmailStatus::Failed, $log->fresh()->status);
    }

    public function test_index_filters_by_status(): void
    {
        EmailLog::factory()->create(['status' => EmailStatus::Sent->value]);
        EmailLog::factory()->create(['status' => EmailStatus::Failed->value]);
        EmailLog::factory()->create(['status' => EmailStatus::Failed->value]);

        $response = $this->actingAs($this->user)->get('/email-logs?status=failed');

        $response->assertInertia(fn ($page) => $page
            ->component('app/email-logs/index')
            ->has('logs.data', 2)
        );
    }

    public function test_resend_requeues_failed_email(): void
    {
        Queue::fake();

        $log = EmailLog::factory()->create(['status' => EmailStatus::Failed->value]);

        $this->actingAs($this->user)
            ->post("/email-logs/{$log->uuid}/resend")
            ->assertRedirect();

        $this->assertDatabaseHas('email_logs', [
            'uuid' => $log->uuid,
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProcessEmailJob::class);
    }

    public function test_resend_rejects_non_failed_emails(): void
    {
        Queue::fake();

        $log = EmailLog::factory()->create(['status' => EmailStatus::Sent->value]);

        $this->actingAs($this->user)
            ->post("/email-logs/{$log->uuid}/resend")
            ->assertSessionHasErrors();
    }

    public function test_update_settings_persists_kill_switch(): void
    {
        $this->actingAs($this->user)
            ->patch('/email-logs/settings', [
                'enabled' => false,
                'auth_enabled' => true,
                'export_enabled' => false,
                'notification_enabled' => true,
                'system_enabled' => true,
            ])
            ->assertOk()
            ->assertJson(['message' => 'Configurações salvas.']);

        $this->assertFalse(AppSettings::get('email', 'enabled', true));
        $this->assertFalse(AppSettings::get('email', 'export_enabled', true));
    }
}
