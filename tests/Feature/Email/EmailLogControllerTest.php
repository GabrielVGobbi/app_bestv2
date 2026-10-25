<?php

namespace Tests\Feature\Email;

use App\Models\User;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Jobs\ProcessEmailJob;
use App\Modules\Email\Models\EmailLog;
use App\Modules\Permissions\Models\Role;
use App\Supports\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\TransientToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        config(['permissions.super_role' => 'developer']);
        $this->user = $this->actor('developer');
        Queue::fake();
        Mail::fake();
    }

    public function test_index_renders_for_authenticated_users(): void
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

    public function test_index_does_not_hydrate_large_delivery_payloads(): void
    {
        EmailLog::factory(26)->create([
            'variables' => ['content' => str_repeat('v', 131072)],
            'metadata' => ['response' => str_repeat('m', 131072)],
        ]);
        $hydratedBytes = [];
        EmailLog::retrieved(function (EmailLog $log) use (&$hydratedBytes): void {
            $hydratedBytes[] = strlen(json_encode($log->getRawOriginal(), JSON_THROW_ON_ERROR));
        });

        $this->actingAs($this->user)->get('/email-logs', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonCount(25, 'props.logs.data')
            ->assertJsonPath('props.logs.total', 26);

        $this->assertCount(25, $hydratedBytes);
        $this->assertLessThan(50000, array_sum($hydratedBytes), 'The listing must not fetch delivery variables or provider payloads.');
        $this->get('/email-logs?page=2', ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonCount(1, 'props.logs.data');
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

        $log = EmailLog::factory()->create(['status' => EmailStatus::Failed->value, 'retry_safe' => true]);

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

    public function test_guest_mutations_do_not_queue_or_change_settings(): void
    {
        $log = $this->failedLog();
        $before = $this->administrationSnapshot();

        $this->postJson(route('email-logs.resend', $log))->assertUnauthorized();
        $this->patchJson(route('email-logs.settings.update'), $this->validSettings())->assertUnauthorized();

        $this->assertSame($before, $this->administrationSnapshot());
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    #[DataProvider('nonDeveloperRoles')]
    public function test_non_developers_cannot_read_resend_or_change_settings(?string $role): void
    {
        $this->assertAdministrationDenied($this->actor($role));
    }

    /** @return array<string, array{?string}> */
    public static function nonDeveloperRoles(): array
    {
        return [
            'no role' => [null],
            'user' => ['user'],
            'admin' => ['admin'],
            'super admin' => ['super-admin'],
            'uppercase alias' => ['Developer'],
            'custom role named Developer' => ['email-administrator'],
        ];
    }

    public function test_changing_super_role_does_not_redefine_developer_authority(): void
    {
        config(['permissions.super_role' => 'admin']);
        $this->assertAdministrationDenied($this->user);
    }

    #[DataProvider('bearerModes')]
    public function test_even_developer_tokens_cannot_administer_email(string $mode): void
    {
        $token = $this->user->createToken('SEC021 synthetic token', $mode === 'wildcard' ? ['*'] : ['view-email-logs', 'resend-email-logs', 'manage-email-settings']);

        if ($mode === 'attached') {
            $this->attachToken($this->user, $token->accessToken);
        } else {
            $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken);
        }

        $this->assertAdministrationDenied($this->user);
    }

    /** @return array<string, array{string}> */
    public static function bearerModes(): array
    {
        return ['session with wildcard bearer' => ['wildcard'], 'session with explicit bearer' => ['explicit'], 'attached PAT' => ['attached']];
    }

    public function test_developer_transient_session_preserves_the_flow_in_production(): void
    {
        $this->app->instance('env', 'production');
        config(['app.env' => 'production']);
        $this->attachToken($this->user, new TransientToken);
        $log = EmailLog::factory()->failed()->create(['retry_safe' => true]);

        $this->actingAs($this->user, 'web')->get(route('email-logs.index'))->assertOk();
        $this->post(route('email-logs.resend', $log))->assertRedirect();
        $this->patchJson(route('email-logs.settings.update'), $this->validSettings())->assertOk();

        $this->assertSame(EmailStatus::Queued, $log->refresh()->status);
        Queue::assertPushed(ProcessEmailJob::class, 1);
        Mail::assertNothingOutgoing();
    }

    public function test_developer_role_revocation_and_restoration_are_effective_on_new_requests(): void
    {
        $roleIds = $this->user->roles()->pluck('roles.id')->all();
        $this->actingAs($this->user)->get(route('email-logs.index'))->assertOk();
        $this->user->roles()->detach();
        Auth::forgetGuards();
        $this->assertAdministrationDenied($this->user->fresh());

        $this->user->roles()->attach($roleIds);
        Auth::forgetGuards();
        $this->actingAs($this->user->fresh())->get(route('email-logs.index'))->assertOk();
        $this->patchJson(route('email-logs.settings.update'), $this->validSettings())->assertOk();
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_logs_expose_only_operational_fields_and_generic_errors_without_changing_stored_messages(): void
    {
        $first = $this->failedLog();
        $second = EmailLog::factory()->failed()->create(['error_message' => 'SEC021_SECOND_RAW_SECRET']);
        $sent = EmailLog::factory()->create(['status' => EmailStatus::Sent, 'error_message' => null]);
        $before = $this->administrationSnapshot();

        $response = $this->actingAs($this->user)->get(route('email-logs.index'), ['X-Inertia' => 'true']);
        $response->assertOk()->assertJsonPath('component', 'app/email-logs/index');
        $logs = $response->json('props.logs');
        $this->assertSame(3, $logs['total']);
        $this->assertSame(1, $logs['current_page']);
        $this->assertSame(25, $logs['per_page']);
        $this->assertArrayHasKey('next_page_url', $logs);
        $this->assertArrayNotHasKey('meta', $logs);
        $rows = array_column($logs['data'], null, 'uuid');
        $expectedKeys = ['uuid', 'to_email', 'to_name', 'subject', 'template', 'category', 'status', 'attempts', 'mailer', 'message_id', 'sent_at', 'failed_at', 'created_at', 'error_message'];

        foreach ($rows as $row) {
            $this->assertEqualsCanonicalizing($expectedKeys, array_keys($row));
        }

        $this->assertSame($first->to_email, $rows[$first->uuid]['to_email']);
        $this->assertSame($first->subject, $rows[$first->uuid]['subject']);
        $this->assertSame('sec021-provider-correlation', $rows[$first->uuid]['message_id']);
        $this->assertSame('failed', $rows[$first->uuid]['status']);
        $this->assertIsString($rows[$first->uuid]['error_message']);
        $this->assertNotSame('', trim($rows[$first->uuid]['error_message']));
        $this->assertSame($rows[$first->uuid]['error_message'], $rows[$second->uuid]['error_message']);
        $this->assertNull($rows[$sent->uuid]['error_message']);
        foreach (['SEC021_VARIABLE_SECRET', 'SEC021_METADATA_SECRET', 'SEC021_RAW_ERROR_SECRET', 'SEC021_SECOND_RAW_SECRET', 'SEC021_IDEMPOTENCY_SECRET', 'SEC021_FROM_SECRET'] as $marker) {
            $response->assertDontSee($marker);
        }
        $this->assertSame($before, $this->administrationSnapshot());
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    #[DataProvider('settingsShapes')]
    public function test_settings_props_are_five_booleans_with_safe_defaults(string $shape): void
    {
        $defaults = ['enabled' => true, 'auth_enabled' => false, 'export_enabled' => true, 'notification_enabled' => false, 'system_enabled' => true];
        config(['email.defaults' => $defaults]);
        AppSettings::set('email', 'extra_secret', 'SEC021_SETTINGS_SECRET');
        $expected = $defaults;

        if ($shape === 'stored') {
            AppSettings::set('email', 'enabled', false);
            AppSettings::set('email', 'auth_enabled', true);
            $expected['enabled'] = false;
            $expected['auth_enabled'] = true;
        } elseif ($shape === 'legacy') {
            AppSettings::set('email', 'enabled', 0);
            AppSettings::set('email', 'auth_enabled', '1');
            AppSettings::set('email', 'export_enabled', '0');
            AppSettings::set('email', 'notification_enabled', 1);
            AppSettings::set('email', 'system_enabled', false);
            $expected = ['enabled' => false, 'auth_enabled' => true, 'export_enabled' => false, 'notification_enabled' => true, 'system_enabled' => false];
        } elseif ($shape === 'malformed') {
            AppSettings::set('email', 'enabled', ['secret' => 'SEC021_MALFORMED_SECRET']);
            AppSettings::set('email', 'auth_enabled', 'SEC021_MALFORMED_SECRET');
            config(['email.defaults.system_enabled' => ['secret' => 'SEC021_CONFIG_SECRET']]);
            $expected['system_enabled'] = true;
        }

        $before = $this->administrationSnapshot();
        $response = $this->actingAs($this->user)->get(route('email-logs.index'), ['X-Inertia' => 'true']);
        $response->assertOk()->assertJsonPath('props.settings', $expected);
        foreach (['SEC021_SETTINGS_SECRET', 'SEC021_MALFORMED_SECRET', 'SEC021_CONFIG_SECRET'] as $marker) {
            $response->assertDontSee($marker);
        }
        $this->assertSame($before, $this->administrationSnapshot());
    }

    /** @return array<string, array{string}> */
    public static function settingsShapes(): array
    {
        return ['missing settings' => ['missing'], 'stored booleans override defaults' => ['stored'], 'legacy boolean encodings' => ['legacy'], 'malformed settings and default' => ['malformed']];
    }

    public function test_settings_validation_does_not_mutate_and_valid_json_request_recovers(): void
    {
        AppSettings::set('email', 'enabled', true);
        $before = $this->administrationSnapshot();
        $this->actingAs($this->user)->patchJson(route('email-logs.settings.update'), ['enabled' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['enabled', 'auth_enabled', 'export_enabled', 'notification_enabled', 'system_enabled']);
        $this->assertSame($before, $this->administrationSnapshot());

        $this->patchJson(route('email-logs.settings.update'), $this->validSettings() + ['extra_secret' => 'SEC021_IGNORED_SECRET'])
            ->assertOk()->assertJson(['message' => 'Configurações salvas.']);
        $this->assertFalse(AppSettings::get('email', 'enabled'));
        $this->assertDatabaseMissing('app_settings', ['group' => 'email', 'key' => 'extra_secret']);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_inertia_settings_form_redirects_back_to_a_renderable_page(): void
    {
        $this->actingAs($this->user)->from(route('email-logs.index'))
            ->patch(route('email-logs.settings.update'), $this->validSettings(), ['X-Inertia' => 'true'])
            ->assertRedirect(route('email-logs.index'));

        $this->get(route('email-logs.index'), ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonPath('component', 'app/email-logs/index')
            ->assertJsonPath('props.settings.enabled', false);
        $this->assertFalse(AppSettings::get('email', 'enabled'));
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    private function assertAdministrationDenied(User $actor): void
    {
        $log = $this->failedLog();
        AppSettings::set('email', 'enabled', true);
        Cache::put('app_settings.email.enabled', 'SEC021_CACHE_UNCHANGED');
        $before = $this->administrationSnapshot();

        $this->actingAs($actor, 'web')->get(route('email-logs.index'))->assertForbidden();
        $this->assertSame($before, $this->administrationSnapshot());
        $this->postJson(route('email-logs.resend', $log))->assertForbidden();
        $this->assertSame($before, $this->administrationSnapshot());
        $this->patchJson(route('email-logs.settings.update'), ['enabled' => 'invalid'])->assertForbidden();
        $this->assertSame($before, $this->administrationSnapshot());
        $this->assertSame('SEC021_CACHE_UNCHANGED', Cache::get('app_settings.email.enabled'));
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    private function failedLog(): EmailLog
    {
        return EmailLog::factory()->failed()->create([
            'to_email' => 'sec021-recipient@example.test',
            'variables' => ['reset_url' => 'SEC021_VARIABLE_SECRET'],
            'metadata' => ['token' => 'SEC021_METADATA_SECRET'],
            'error_message' => 'SEC021_RAW_ERROR_SECRET',
            'idempotency_key' => 'SEC021_IDEMPOTENCY_SECRET',
            'from_email' => 'SEC021_FROM_SECRET@example.test',
            'message_id' => 'sec021-provider-correlation',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
        ]);
    }

    private function actor(?string $slug): User
    {
        $actor = User::factory()->create();
        if ($slug !== null) {
            $role = Role::query()->where('slug', $slug)->first();
            if ($role === null) {
                $role = Role::create(['name' => 'Developer', 'slug' => 'sec021-role']);
                DB::table('roles')->where('id', $role->id)->update(['slug' => $slug]);
            }
            $actor->roles()->attach($role);
        }

        return $actor;
    }

    /** Sanctum's untyped setter also accepts the first-party transient token. */
    private function attachToken(User $user, mixed $token): void
    {
        $user->withAccessToken($token);
    }

    /** @return array<string, bool> */
    private function validSettings(): array
    {
        return ['enabled' => false, 'auth_enabled' => true, 'export_enabled' => false, 'notification_enabled' => true, 'system_enabled' => true];
    }

    /** @return array{logs: list<array<string, mixed>>, settings: list<array<string, mixed>>} */
    private function administrationSnapshot(): array
    {
        return [
            'logs' => EmailLog::query()->orderBy('id')->get()->map(fn (EmailLog $log): array => $log->getRawOriginal())->all(),
            'settings' => DB::table('app_settings')->orderBy('group')->orderBy('key')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
    }
}
