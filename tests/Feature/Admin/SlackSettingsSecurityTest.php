<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Permissions\Models\Role;
use App\Modules\Slack\Contracts\SlackServiceInterface;
use App\Modules\Slack\DTOs\SlackMessageDTO;
use App\Modules\Slack\Enums\SlackAlertLevel;
use App\Supports\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\TransientToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SlackSettingsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIG_WEBHOOK = 'https://hooks.slack.com/services/CONFIG/ONLY/CONFIG_SECRET_MARKER';

    private const STORED_WEBHOOK = 'https://hooks.slack.com/services/STORED/ONLY/STORED_SECRET_MARKER';

    private RecordingSettingsSlackService $slack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        config([
            'permissions.super_role' => 'developer',
            'slack.webhook_url' => self::CONFIG_WEBHOOK,
            'slack.enabled' => true,
            'slack.log_level' => 'warning',
        ]);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([], 200));
        $this->slack = new RecordingSettingsSlackService;
        $this->app->instance(SlackServiceInterface::class, $this->slack);
    }

    public function test_guests_cannot_read_write_or_send_slack_settings(): void
    {
        $before = $this->settingsSnapshot();
        $this->get(route('admin.slack.index'))->assertRedirect(route('login'));
        $this->putJson(route('admin.slack.update'), $this->validSettings())->assertUnauthorized();
        $this->postJson(route('admin.slack.test'))->assertUnauthorized();
        $this->assertSame($before, $this->settingsSnapshot());
        $this->assertSame([], $this->slack->calls);
        Http::assertNothingSent();
    }

    #[DataProvider('nonDeveloperRoles')]
    public function test_only_literal_developer_can_read_write_or_send_before_validation_or_settings_queries(?string $slug): void
    {
        $actor = $this->actor($slug);
        AppSettings::set('slack', 'webhook_url', self::STORED_WEBHOOK);
        Cache::put('app_settings.slack.webhook_url', 'OWN_CACHE_MARKER');
        $before = $this->settingsSnapshot();
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        try {
            $this->actingAs($actor)->get(route('admin.slack.index'))->assertForbidden();
            $this->putJson(route('admin.slack.update'), ['enabled' => 'invalid'])->assertForbidden();
            $this->postJson(route('admin.slack.test'))->assertForbidden();
            $queries = DB::connection()->getQueryLog();
        } finally {
            DB::connection()->disableQueryLog();
        }
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('app_settings', strtolower($query['query']));
        }
        $this->assertSame($before, $this->settingsSnapshot());
        $this->assertSame('OWN_CACHE_MARKER', Cache::get('app_settings.slack.webhook_url'));
        $this->assertSame([], $this->slack->calls);
        Http::assertNothingSent();
    }

    public static function nonDeveloperRoles(): array
    {
        return [
            'no role' => [null], 'user' => ['user'], 'admin' => ['admin'],
            'super-admin' => ['super-admin'], 'display alias' => ['Developer'],
            'accent alias' => ['déveloper'], 'custom role named Developer' => ['integration-manager'],
        ];
    }

    public function test_custom_super_role_cannot_redefine_the_developer_authority(): void
    {
        $actor = $this->actor('developer');
        config(['permissions.super_role' => 'admin']);
        $before = $this->settingsSnapshot();
        $this->actingAs($actor)->get(route('admin.slack.index'))->assertForbidden();
        $this->putJson(route('admin.slack.update'), $this->validSettings())->assertForbidden();
        $this->postJson(route('admin.slack.test'))->assertForbidden();
        $this->assertSame($before, $this->settingsSnapshot());
        $this->assertSame([], $this->slack->calls);
    }

    #[DataProvider('bearerModes')]
    public function test_developer_personal_tokens_and_session_with_bearer_do_not_manage_global_settings(string $mode): void
    {
        $actor = $this->actor('developer');
        $token = $actor->createToken('Synthetic integration token', $mode === 'explicit' ? ['manageIntegrationSettings'] : ['*']);
        if ($mode === 'attached') {
            $actor->withAccessToken($token->accessToken);
        } else {
            $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken);
        }
        $before = $this->settingsSnapshot();
        $this->actingAs($actor, 'web')->get(route('admin.slack.index'))->assertForbidden();
        $this->putJson(route('admin.slack.update'), $this->validSettings())->assertForbidden();
        $this->postJson(route('admin.slack.test'))->assertForbidden();
        $this->assertSame($before, $this->settingsSnapshot());
        $this->assertSame([], $this->slack->calls);
        Http::assertNothingSent();
    }

    public static function bearerModes(): array
    {
        return ['PAT on session actor' => ['attached'], 'wildcard bearer' => ['wildcard'], 'explicit bearer' => ['explicit']];
    }

    #[DataProvider('sessionEnvironments')]
    public function test_developer_first_party_session_can_manage_settings_in_any_environment(string $environment, bool $transient): void
    {
        $actor = $this->actor('developer');
        if ($transient) {
            $this->attachSessionToken($actor, new TransientToken);
        }
        $this->app->instance('env', $environment);
        config(['app.env' => $environment]);
        $this->actingAs($actor, 'web')->get(route('admin.slack.index'))->assertOk();
        $this->putJson(route('admin.slack.update'), $this->validSettings())->assertOk();
        $this->postJson(route('admin.slack.test'))->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, count(array_filter($this->slack->calls, fn (string $call): bool => $call === 'alert')));
        $this->assertNotContains('send', $this->slack->calls);
        $this->assertNotContains('exception', $this->slack->calls);
        Http::assertNothingSent();
    }

    public static function sessionEnvironments(): array
    {
        return ['testing null token' => ['testing', false], 'production transient token' => ['production', true]];
    }

    public function test_settings_props_expose_only_operational_values_and_presence_with_database_precedence(): void
    {
        $actor = $this->actor('developer');
        AppSettings::set('slack', 'webhook_url', self::STORED_WEBHOOK);
        AppSettings::set('slack', 'enabled', false);
        AppSettings::set('slack', 'log_level', 'critical');
        AppSettings::set('slack', 'extra_secret', 'EXTRA_SETTINGS_SECRET_MARKER');
        $this->slack->enabled = false;
        $before = $this->settingsSnapshot();
        $this->actingAs($actor)->get(route('admin.slack.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/slack/index')
                ->where('settings', ['enabled' => false, 'log_level' => 'critical'])
                ->where('webhook_configured', true)->where('isEnabled', false)->has('levelOptions'))
            ->assertDontSee('CONFIG_SECRET_MARKER')->assertDontSee('STORED_SECRET_MARKER')
            ->assertDontSee('EXTRA_SETTINGS_SECRET_MARKER');
        $this->assertSame($before, $this->settingsSnapshot());
        Http::assertNothingSent();
    }

    #[DataProvider('webhookPresence')]
    public function test_webhook_presence_uses_stored_override_then_configuration_without_exposing_either(?string $stored, ?string $configured, bool $expected): void
    {
        $actor = $this->actor('developer');
        config(['slack.webhook_url' => $configured]);
        if ($stored !== null) {
            AppSettings::set('slack', 'webhook_url', $stored);
        }
        $this->actingAs($actor)->get(route('admin.slack.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('settings.webhook_url')->where('webhook_configured', $expected))
            ->assertDontSee('SECRET_MARKER');
    }

    public static function webhookPresence(): array
    {
        return ['stored only' => [self::STORED_WEBHOOK, null, true], 'config only' => [null, self::CONFIG_WEBHOOK, true], 'none' => [null, null, false]];
    }

    #[DataProvider('keepWebhookInputs')]
    public function test_absent_null_and_blank_webhook_keep_the_exact_stored_secret(array $extra): void
    {
        $actor = $this->actor('developer');
        AppSettings::set('slack', 'webhook_url', self::STORED_WEBHOOK);
        AppSettings::set('unrelated', 'value', 'UNRELATED_MARKER');
        $original = (array) DB::table('app_settings')->where('group', 'slack')->where('key', 'webhook_url')->first();
        $this->actingAs($actor)->putJson(route('admin.slack.update'), [...$this->validSettings(), ...$extra])
            ->assertOk()->assertDontSee('SECRET_MARKER');
        $this->assertSame($original, (array) DB::table('app_settings')->where('group', 'slack')->where('key', 'webhook_url')->first());
        $this->assertSame(self::STORED_WEBHOOK, AppSettings::get('slack', 'webhook_url'));
        $this->assertSame('UNRELATED_MARKER', AppSettings::get('unrelated', 'value'));
        $this->assertNotContains('alert', $this->slack->calls);
        $this->assertNotContains('send', $this->slack->calls);
        $this->assertNotContains('exception', $this->slack->calls);
        Http::assertNothingSent();
    }

    public static function keepWebhookInputs(): array
    {
        return ['absent' => [[]], 'null' => [['webhook_url' => null]], 'empty' => [['webhook_url' => '']], 'whitespace' => [['webhook_url' => '   ']]];
    }

    public function test_explicit_replacement_and_reset_restore_config_fallback_without_touching_other_settings(): void
    {
        $actor = $this->actor('developer');
        AppSettings::set('slack', 'webhook_url', self::STORED_WEBHOOK);
        AppSettings::set('unrelated', 'value', 'UNRELATED_MARKER');
        $replacement = 'https://hooks.slack.com/services/NEW/ONLY/NEW_SECRET_MARKER';
        $this->actingAs($actor)->putJson(route('admin.slack.update'), [...$this->validSettings(), 'webhook_url' => $replacement])
            ->assertOk()->assertDontSee('NEW_SECRET_MARKER');
        $this->assertSame($replacement, AppSettings::get('slack', 'webhook_url'));
        $this->putJson(route('admin.slack.update'), [...$this->validSettings(), 'reset_webhook' => true])
            ->assertOk()->assertDontSee('CONFIG_SECRET_MARKER');
        $this->assertDatabaseMissing('app_settings', ['group' => 'slack', 'key' => 'webhook_url']);
        $this->assertDatabaseMissing('app_settings', ['group' => 'slack', 'key' => 'reset_webhook']);
        $this->assertSame(self::CONFIG_WEBHOOK, config('slack.webhook_url'));
        $this->assertSame('UNRELATED_MARKER', AppSettings::get('unrelated', 'value'));
        $this->get(route('admin.slack.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('webhook_configured', true)->missing('settings.webhook_url'));
        Http::assertNothingSent();
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_are_rejected_without_partial_writes_or_sends(array $payload): void
    {
        $actor = $this->actor('developer');
        AppSettings::set('slack', 'webhook_url', self::STORED_WEBHOOK);
        $before = $this->settingsSnapshot();
        $this->actingAs($actor)->putJson(route('admin.slack.update'), $payload)->assertUnprocessable();
        $this->assertSame($before, $this->settingsSnapshot());
        $this->assertSame([], $this->slack->calls);
        Http::assertNothingSent();
    }

    public static function invalidSettings(): array
    {
        return [
            'missing enabled' => [['log_level' => 'warning']],
            'missing log level' => [['enabled' => true]],
            'invalid level' => [['enabled' => true, 'log_level' => 'verbose']],
            'invalid webhook' => [['enabled' => false, 'log_level' => 'critical', 'webhook_url' => '[redacted]']],
            'reset and replacement' => [['enabled' => false, 'log_level' => 'critical', 'reset_webhook' => true, 'webhook_url' => self::STORED_WEBHOOK]],
            'invalid reset' => [['enabled' => true, 'log_level' => 'warning', 'reset_webhook' => ['yes']]],
        ];
    }

    public function test_disabled_or_failed_test_send_returns_422_without_settings_mutation_and_can_recover(): void
    {
        $actor = $this->actor('developer');
        $before = $this->settingsSnapshot();
        $this->slack->enabled = false;
        $this->actingAs($actor)->postJson(route('admin.slack.test'))->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertSame(['isEnabled'], $this->slack->calls);
        $this->slack->enabled = true;
        $this->slack->sent = false;
        $this->postJson(route('admin.slack.test'))->assertUnprocessable()->assertJsonPath('success', false)->assertDontSee('SECRET_MARKER');
        $this->assertSame(['isEnabled', 'isEnabled', 'alert'], $this->slack->calls);
        $this->slack->sent = true;
        $this->postJson(route('admin.slack.test'))->assertOk()->assertJsonPath('success', true);
        $this->assertSame(['isEnabled', 'isEnabled', 'alert', 'isEnabled', 'alert'], $this->slack->calls);
        $this->assertSame($before, $this->settingsSnapshot());
        Http::assertNothingSent();
    }

    /** Sanctum's native untyped setter accepts session tokens as well as personal tokens. */
    private function attachSessionToken(User $actor, mixed $token): void
    {
        $actor->withAccessToken($token);
    }

    private function actor(?string $slug): User
    {
        $actor = User::factory()->create();
        if ($slug !== null) {
            $role = Role::create(['name' => 'Developer', 'slug' => 'fixture-role']);
            DB::table('roles')->where('id', $role->getKey())->update(['slug' => $slug]);
            $actor->roles()->attach($role);
        }

        return $actor;
    }

    /** @return array{enabled: bool, log_level: string} */
    private function validSettings(): array
    {
        return ['enabled' => true, 'log_level' => 'warning'];
    }

    /** @return list<array<string, mixed>> */
    private function settingsSnapshot(): array
    {
        return DB::table('app_settings')->orderBy('group')->orderBy('key')->get()
            ->map(fn (object $row): array => (array) $row)->all();
    }
}

class RecordingSettingsSlackService implements SlackServiceInterface
{
    public bool $enabled = true;

    public bool $sent = true;

    /** @var list<string> */
    public array $calls = [];

    public function send(SlackMessageDTO $message): bool
    {
        $this->calls[] = 'send';

        return $this->sent;
    }

    public function alert(string $message, SlackAlertLevel $level = SlackAlertLevel::Info, array $context = []): bool
    {
        $this->calls[] = 'alert';

        return $this->sent;
    }

    public function exception(\Throwable $e, ?string $context = null): bool
    {
        $this->calls[] = 'exception';

        return $this->sent;
    }

    public function isEnabled(): bool
    {
        $this->calls[] = 'isEnabled';

        return $this->enabled;
    }
}
