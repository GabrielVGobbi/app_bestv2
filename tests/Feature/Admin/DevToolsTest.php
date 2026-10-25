<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use App\Services\DevToolsService;
use App\Services\NavigationService;
use Dotenv\Dotenv;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Tests\TestEnvironment;

class DevToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
        File::put(app()->environmentFilePath(), "APP_NAME=Fixture\nAPP_LOCALE=pt_BR\nAPP_FALLBACK_LOCALE=en\nTELESCOPE_ENABLED=false\nAPP_KEY=fixture-private-key\nUNLISTED_API_TOKEN=fixture-private-token\n");
        config(['app.name' => 'Fixture', 'app.locale' => 'pt_BR', 'app.fallback_locale' => 'en', 'telescope.enabled' => false]);
    }

    private function developer(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'developer'], ['name' => 'Developer']);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_response_contains_only_operational_settings_without_environment_secrets(): void
    {
        $this->actingAs($this->developer())->get(route('admin.dev.dev-tools.index'))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->missing('envVariables')
            ->where('operationalSettings', [
                'APP_NAME' => 'Fixture', 'APP_LOCALE' => 'pt_BR',
                'APP_FALLBACK_LOCALE' => 'en', 'TELESCOPE_ENABLED' => false,
            ])
            ->missing('systemInfo.telescope_capture_all'))
            ->assertDontSee('fixture-private-key')->assertDontSee('fixture-private-token');
    }

    #[DataProvider('nonDeveloperRoles')]
    public function test_common_accounts_cannot_access_or_mutate_developer_tools(?string $slug): void
    {
        $user = User::factory()->create();

        if ($slug !== null) {
            $role = Role::create(['name' => 'Fixture role', 'slug' => 'fixture-role']);
            $role->getConnection()->table('roles')->where('id', $role->id)->update(['slug' => $slug]);
            $user->roles()->attach($role->id);
        }

        Artisan::shouldReceive('call')->never();
        $before = File::get(app()->environmentFilePath());
        $this->actingAs($user)->get(route('admin.dev.dashboard'))->assertForbidden();
        $this->get(route('admin.dev.components.index'))->assertForbidden();
        $this->post(route('admin.dev.dev-tools.env.update'), ['key' => 'APP_NAME', 'value' => 'Changed'])->assertForbidden();
        $this->post(route('admin.dev.dev-tools.cache.clear'))->assertForbidden();
        $this->assertSame($before, File::get(app()->environmentFilePath()));
    }

    public static function nonDeveloperRoles(): array
    {
        return [[null], ['user'], ['admin'], ['super-admin'], ['Developer'], ['dévelóper']];
    }

    #[DataProvider('deniedEnvironments')]
    public function test_developer_tools_are_denied_outside_local_testing(string $environment): void
    {
        $developer = $this->developer();
        $this->app->instance('env', $environment);
        config(['app.env' => $environment]);
        Artisan::shouldReceive('call')->never();
        $this->actingAs($developer)->get(route('admin.dev.dev-tools.index'))->assertForbidden();
        $this->post(route('admin.dev.dev-tools.cache.clear'))->assertForbidden();
    }

    public static function deniedEnvironments(): array
    {
        return [['staging'], ['production'], ['preview'], ['LOCAL']];
    }

    public function test_divergent_container_and_config_environment_are_denied(): void
    {
        config(['app.env' => 'local']);
        $this->actingAs($this->developer())->get(route('admin.dev.dev-tools.index'))->assertForbidden();
    }

    public function test_bearer_star_cannot_use_developer_tools_even_with_web_authentication(): void
    {
        $developer = $this->developer();
        $token = $developer->createToken('Fixture', ['*'])->plainTextToken;
        $this->actingAs($developer, 'web')->withHeader('Authorization', 'Bearer '.$token)
            ->get(route('admin.dev.dev-tools.index'))->assertForbidden();
    }

    public function test_homonymous_permission_cannot_grant_technical_access(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'Fixture ability', 'slug' => 'fixture-ability']);
        $permission->getConnection()->table('permissions')->where('id', $permission->id)->update(['slug' => 'useDeveloperTools']);
        $user->permissions()->attach($permission->id);
        config(['permissions.functional' => ['useDeveloperTools']]);
        $this->actingAs($user)->get(route('admin.dev.dev-tools.index'))->assertForbidden();
    }

    public function test_unknown_environment_key_cannot_be_written(): void
    {
        $before = File::get(app()->environmentFilePath());
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.env.update'), ['key' => 'APP_DEBUG', 'value' => 'true'])->assertUnprocessable();
        $this->assertSame($before, File::get(app()->environmentFilePath()));
    }

    public function test_environment_value_cannot_inject_another_setting(): void
    {
        $before = File::get(app()->environmentFilePath());
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.env.update'), ['key' => 'APP_NAME', 'value' => "Fixture\"\nAPP_DEBUG=true"])->assertUnprocessable();
        $this->assertSame($before, File::get(app()->environmentFilePath()));
    }

    public function test_http_trimming_cannot_hide_control_characters_in_an_environment_value(): void
    {
        $before = File::get(app()->environmentFilePath());
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.env.update'), ['key' => 'APP_NAME', 'value' => "New Name\r\n"])->assertUnprocessable();
        $this->assertSame($before, File::get(app()->environmentFilePath()));
    }

    #[DataProvider('invalidOperationalSettings')]
    public function test_service_rejects_invalid_settings_without_partial_writes(string $key, string $value): void
    {
        $before = File::get(app()->environmentFilePath());
        $this->expectException(ValidationException::class);

        try {
            app(DevToolsService::class)->updateEnvVariable($key, $value);
        } finally {
            $this->assertSame($before, File::get(app()->environmentFilePath()));
        }
    }

    public static function invalidOperationalSettings(): array
    {
        return [
            ['APP_KEY', 'replacement'], ['APP_ENV', 'local'], ['APP_DEBUG', 'true'],
            ['APP_NAME', 'Name "quoted"'], ['APP_NAME', "Name\r\nAPP_DEBUG=true"],
            ['APP_NAME', 'Name$1'], ['APP_NAME', 'Name#comment'], ['APP_NAME', 'Name\\escape'],
            ['APP_NAME', "Name\0"], ['APP_LOCALE', 'not-installed'],
            ['APP_FALLBACK_LOCALE', 'fr'], ['TELESCOPE_ENABLED', '1'], ['TELESCOPE_ENABLED', 'FALSE'],
        ];
    }

    #[DataProvider('validOperationalSettings')]
    public function test_allowed_settings_round_trip_and_preserve_unrelated_bytes(string $key, string $value): void
    {
        chmod(app()->environmentFilePath(), 0600);
        clearstatcache(true, app()->environmentFilePath());
        $originalMode = fileperms(app()->environmentFilePath()) & 0777;
        $before = File::get(app()->environmentFilePath());
        app(DevToolsService::class)->updateEnvVariable($key, $value);
        clearstatcache(true, app()->environmentFilePath());
        $this->assertSame($originalMode, fileperms(app()->environmentFilePath()) & 0777);
        $after = File::get(app()->environmentFilePath());
        $this->assertSame($value, Dotenv::parse($after)[$key]);
        $this->assertStringContainsString("APP_KEY=fixture-private-key\nUNLISTED_API_TOKEN=fixture-private-token\n", $after);
        $this->assertSame(
            preg_replace('/^'.preg_quote($key, '/').'=[^\r\n]*/m', '', $before),
            preg_replace('/^'.preg_quote($key, '/').'=[^\r\n]*/m', '', $after),
        );
        $this->assertSame($key === 'TELESCOPE_ENABLED' ? $value === 'true' : $value, app(DevToolsService::class)->getOperationalSettings()[$key]);
    }

    public static function validOperationalSettings(): array
    {
        return [['APP_NAME', 'Fixture Tools'], ['APP_LOCALE', 'en'], ['APP_FALLBACK_LOCALE', 'pt_BR'], ['TELESCOPE_ENABLED', 'true']];
    }

    public function test_writer_uses_the_configured_environment_file_and_preserves_crlf(): void
    {
        $baseEnvironment = File::get(base_path('.env'));
        $this->app->loadEnvironmentFrom('.env.operational');
        File::put(app()->environmentFilePath(), "UNRELATED=preserved\r\nAPP_NAME=Before\r\n");
        app(DevToolsService::class)->updateEnvVariable('APP_NAME', 'After');
        app(DevToolsService::class)->updateEnvVariable('APP_LOCALE', 'en');
        $this->assertSame("UNRELATED=preserved\r\nAPP_NAME=\"After\"\r\nAPP_LOCALE=en\r\n", File::get(app()->environmentFilePath()));
        $this->assertSame($baseEnvironment, File::get(base_path('.env')));
    }

    public function test_duplicate_operational_keys_are_refused_without_partial_writes(): void
    {
        $contents = "APP_NAME=First\r\nexport APP_NAME=Second\r\nUNRELATED=preserved\r\n";
        File::put(app()->environmentFilePath(), $contents);
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.env.update'), ['key' => 'APP_NAME', 'value' => 'After'])->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->assertSame($contents, File::get(app()->environmentFilePath()));
    }

    #[DataProvider('multilineEnvironmentFixtures')]
    public function test_multiline_environment_values_are_refused_without_changing_any_bytes(string $contents): void
    {
        $this->assertArrayHasKey('OTHER', Dotenv::parse($contents));
        File::put(app()->environmentFilePath(), $contents);

        try {
            $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.env.update'), ['key' => 'APP_NAME', 'value' => 'After'])
                ->assertUnprocessable()->assertJsonValidationErrors('value')->assertSessionMissing('success');
        } finally {
            $this->assertSame($contents, File::get(app()->environmentFilePath()));
        }
    }

    public static function multilineEnvironmentFixtures(): array
    {
        return [
            ["OTHER=\"first\nAPP_NAME=inside-secret\nlast\"\n"],
            ["OTHER=\"first\r\nAPP_NAME=inside-secret\r\nlast\"\r\n"],
            ["OTHER=\"first\nAPP_NAME=inside-secret\nlast\"\nOTHER=overridden\nAPP_NAME=Before\n"],
        ];
    }

    #[DataProvider('ambiguousAssignmentFixtures')]
    public function test_unsupported_target_assignment_forms_are_refused_without_appending_duplicates(string $contents): void
    {
        $this->assertIsArray(Dotenv::parse($contents));
        File::put(app()->environmentFilePath(), $contents);

        try {
            $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.env.update'), ['key' => 'APP_NAME', 'value' => 'After'])
                ->assertUnprocessable()->assertJsonValidationErrors('key')->assertSessionMissing('success');
        } finally {
            $this->assertSame($contents, File::get(app()->environmentFilePath()));
        }
    }

    public static function ambiguousAssignmentFixtures(): array
    {
        return [["\"APP_NAME\"=Before\n"], ["export 'APP_NAME' = Before\n"], ["APP_NAME\n"], ["APP_NAME=Before\n'APP_NAME'=Duplicate\n"]];
    }

    public function test_partial_temporary_write_preserves_the_existing_environment_file(): void
    {
        $path = app()->environmentFilePath();
        $before = File::get($path);
        File::partialMock()->shouldReceive('put')->once()
            ->withArgs(fn (string $destination, string $contents, bool $lock): bool => realpath(dirname($destination)) === realpath(dirname($path)) && str_starts_with(basename($destination), '.op') && $lock)
            ->andReturnUsing(function (string $destination, string $contents): int {
                file_put_contents($destination, substr($contents, 0, 3));

                return 3;
            });
        $this->expectException(RuntimeException::class);

        try {
            app(DevToolsService::class)->updateEnvVariable('APP_NAME', 'After');
        } finally {
            $this->assertSame($before, File::get($path));
            $this->assertSame([], glob(dirname($path).'/.op*'));
        }
    }

    public function test_cached_configuration_refuses_environment_writes_until_cleared(): void
    {
        File::put(app()->getCachedConfigPath(), '<?php return [];');
        $before = File::get(app()->environmentFilePath());
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.env.update'), ['key' => 'APP_NAME', 'value' => 'After'])->assertUnprocessable();
        $this->assertSame($before, File::get(app()->environmentFilePath()));
    }

    public function test_unsupported_capture_all_control_cannot_write_environment(): void
    {
        $before = File::get(app()->environmentFilePath());
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.telescope.capture-all'))->assertUnprocessable();
        $this->assertSame($before, File::get(app()->environmentFilePath()));
    }

    public function test_telescope_toggle_uses_effective_configuration_and_writes_only_its_boolean(): void
    {
        $this->actingAs($this->developer())->post(route('admin.dev.dev-tools.telescope.toggle'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(config('telescope.enabled'));
        $this->assertSame('true', Dotenv::parse(File::get(app()->environmentFilePath()))['TELESCOPE_ENABLED']);
    }

    public function test_shared_cache_drivers_are_refused_before_any_command(): void
    {
        $navigation = $this->mock(NavigationService::class);
        $navigation->shouldReceive('style')->andReturn('flat');
        $navigation->shouldReceive('modulesMenuPosition')->andReturn('sidebar');
        $navigation->shouldReceive('global', 'modules', 'footer')->andReturn([]);
        $navigation->shouldReceive('resolved')->andReturn(['global' => [], 'modules' => [], 'footer' => []]);
        config(['cache.default' => 'shared', 'cache.stores.shared' => ['driver' => 'redis']]);
        Artisan::shouldReceive('call')->never();
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.cache.clear'))->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_view_cache_outside_the_application_storage_is_refused_before_commands(): void
    {
        $sibling = new TestEnvironment;

        try {
            config(['view.compiled' => $sibling->path.'/storage/framework/views']);
            Artisan::shouldReceive('call')->never();
            $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.optimize'))->assertUnprocessable();
        } finally {
            $sibling->cleanUp();
        }
    }

    public function test_cache_command_failure_has_errors_and_does_not_announce_success(): void
    {
        Artisan::shouldReceive('call')->once()->with('config:cache')->andReturn(1);
        Artisan::shouldReceive('call')->with('route:cache')->never();
        Artisan::shouldReceive('call')->with('view:cache')->never();
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.optimize'))->assertUnprocessable()->assertJsonValidationErrors('operation')->assertSessionMissing('success');
    }

    public function test_shared_maintenance_driver_is_refused_without_activation(): void
    {
        config(['app.maintenance.driver' => 'cache']);
        Artisan::shouldReceive('call')->never();
        $this->actingAs($this->developer())->postJson(route('admin.dev.dev-tools.maintenance.toggle'))->assertUnprocessable();
        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    #[DataProvider('maintenanceLinkTargets')]
    public function test_maintenance_refuses_owned_fixture_symlinks_without_following_or_removing_them(bool $targetExists): void
    {
        $target = base_path('outside-maintenance-target');
        $down = storage_path('framework/down');
        $contents = json_encode(['status' => 503, 'secret' => 'fixture-maintenance-secret'], JSON_THROW_ON_ERROR);

        if ($targetExists) {
            File::put($target, $contents);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $link = new Process(['cmd', '/c', 'mklink', $down, $target]);
            $link->run();

            if (! $link->isSuccessful()) {
                $this->markTestSkipped('Native file symlinks require a Windows privilege unavailable to this runner.');
            }
        } else {
            $this->assertTrue(symlink($target, $down));
        }

        $this->assertTrue(is_link($down));
        $this->assertSame($targetExists, app()->maintenanceMode()->active());
        $this->expectException(ValidationException::class);

        try {
            app(DevToolsService::class)->toggleMaintenance();
        } finally {
            try {
                $this->assertTrue(is_link($down));

                if ($targetExists) {
                    $this->assertSame($contents, File::get($target));
                } else {
                    $this->assertFileDoesNotExist($target);
                }
            } finally {
                if (is_link($down)) {
                    unlink($down);
                }

                File::delete($target);
            }
        }
    }

    public static function maintenanceLinkTargets(): array
    {
        return [[false], [true]];
    }

    public function test_maintenance_cookie_supports_authenticated_recovery_and_rotates_without_secret_props(): void
    {
        Artisan::shouldReceive('call')->never();
        $developer = $this->developer();
        $response = $this->actingAs($developer)->post(route('admin.dev.dev-tools.maintenance.toggle'))->assertRedirect();
        $data = json_decode(File::get(storage_path('framework/down')), true, flags: JSON_THROW_ON_ERROR);
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'laravel_maintenance');
        $this->assertNotNull($cookie);
        $this->assertNull($cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertTrue(MaintenanceModeBypassCookie::isValid($cookie->getValue(), $data['secret']));
        $this->assertFalse(str_contains($response->getContent(), $data['secret']));
        $this->assertFalse(str_contains((string) $response->headers->get('Location'), $data['secret']));
        $this->assertFalse(str_contains(json_encode(session()->all(), JSON_THROW_ON_ERROR), $data['secret']));
        $this->assertFalse($data['secret'] === config('app.maintenance.secret'));
        $page = $this->withUnencryptedCookie('laravel_maintenance', $cookie->getValue())->get(route('admin.dev.dev-tools.index'))->assertOk();
        $this->assertFalse(str_contains($page->getContent(), $data['secret']));

        foreach (File::allFiles(storage_path('logs')) as $log) {
            $this->assertFalse(str_contains(File::get($log->getPathname()), $data['secret']));
        }
        $this->post(route('admin.dev.dev-tools.maintenance.toggle'))->assertRedirect();
        $this->assertFalse(app()->isDownForMaintenance());
        $this->post(route('admin.dev.dev-tools.maintenance.toggle'))->assertRedirect();
        $nextSecret = app()->maintenanceMode()->data()['secret'];
        $this->assertFalse($nextSecret === $data['secret']);
        $this->assertFalse(MaintenanceModeBypassCookie::isValid($cookie->getValue(), $nextSecret));
        app()->maintenanceMode()->deactivate();
    }

    public function test_two_real_workers_preserve_distinct_environment_updates_under_the_same_lock(): void
    {
        $project = base_path();
        $owner = bin2hex(random_bytes(16));
        File::put($project.'/.devtools-owner', $owner);
        $before = File::get(app()->environmentFilePath());
        $lock = fopen(app()->environmentFilePath().'.lock', 'c+b');
        $this->assertNotFalse($lock);
        $this->assertTrue(flock($lock, LOCK_EX));
        $workers = [];

        try {
            foreach ([['APP_NAME', 'Concurrent App'], ['APP_LOCALE', 'en']] as $worker => [$key, $value]) {
                $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/devtools-env-worker.php', $project, $owner, (string) $worker, $key, $value], dirname(__DIR__, 3), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
                ]);
                $process->setTimeout(20);
                $process->start();
                $workers[] = $process;
            }

            $deadline = microtime(true) + 10;

            while (! is_file($project.'/worker-locked-0') || ! is_file($project.'/worker-locked-1')) {
                foreach ($workers as $worker) {
                    if (! $worker->isRunning()) {
                        $this->fail('Worker did not prove contention on the owned sidecar. '.$worker->getErrorOutput());
                    }
                }

                if (microtime(true) > $deadline) {
                    $this->fail('Workers timed out before proving sidecar lock contention.');
                }

                usleep(10000);
            }

            $this->assertSame($before, File::get(app()->environmentFilePath()));
            $this->assertFileDoesNotExist($project.'/worker-result-0');
            $this->assertFileDoesNotExist($project.'/worker-result-1');
            flock($lock, LOCK_UN);
            fclose($lock);
            $lock = null;

            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
            }

            $after = File::get(app()->environmentFilePath());
            $settings = Dotenv::parse($after);
            $this->assertSame('Concurrent App', $settings['APP_NAME']);
            $this->assertSame('en', $settings['APP_LOCALE']);
            $this->assertStringContainsString("APP_KEY=fixture-private-key\nUNLISTED_API_TOKEN=fixture-private-token\n", $after);
            $this->assertSame('success', File::get($project.'/worker-result-0'));
            $this->assertSame('success', File::get($project.'/worker-result-1'));
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }

            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(2);
                }

                $this->assertFalse($worker->isRunning());
            }

            foreach ([0, 1] as $worker) {
                $registry = $project.'/worker-environment-'.$worker;

                if (! is_file($registry)) {
                    continue;
                }

                $path = File::get($registry);

                if (is_dir($path)) {
                    if (realpath(dirname($path)) !== realpath(sys_get_temp_dir()) || ! preg_match('/^start-kit-tests-[a-f0-9]{32}$/', basename($path))
                        || is_link($path) || ! is_file($path.'/.devtools-owner') || File::get($path.'/.devtools-owner') !== $owner) {
                        throw new RuntimeException('Refusing cleanup of an unowned environment worker.');
                    }

                    File::deleteDirectory($path);
                }

                $this->assertDirectoryDoesNotExist($path);
            }
        }
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_dev_tools_page_is_displayed(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->get(route('admin.dev.dev-tools.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('admin/dev/tools')
                ->has('systemInfo')
                ->has('operationalSettings')
                ->has('maintenanceMode'),
        );
    }

    public function test_dev_tools_requires_authentication(): void
    {
        $this->get(route('admin.dev.dev-tools.index'))->assertRedirect(route('login'));
    }

    public function test_components_page_is_displayed(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->get(route('admin.dev.components.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('admin/dev/components/index'),
        );
    }

    public function test_inline_cell_edit_page_is_displayed(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->get(route('admin.dev.components.inline-cell-edit'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('admin/dev/components/inline-cell-edit'),
        );
    }

    public function test_inline_cell_edit_demo_can_update_user_name(): void
    {
        $admin = $this->developer();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.dev.components.inline-cell-edit.users.update', $user), [
            'field' => 'name',
            'value' => 'Edited User',
        ]);

        $response->assertRedirect();
        $this->assertSame('Edited User', $user->refresh()->name);
    }

    public function test_inline_cell_edit_demo_can_update_verification_status(): void
    {
        $admin = $this->developer();
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($admin)->patch(route('admin.dev.components.inline-cell-edit.users.update', $user), [
            'field' => 'email_verified_at',
            'value' => 'verified',
        ]);

        $response->assertRedirect();
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_async_select_page_is_displayed(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->get(route('admin.dev.components.async-select'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('admin/dev/components/async-select'),
        );
    }

    public function test_toasts_page_is_displayed(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->get(route('admin.dev.components.toasts'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('admin/dev/components/toasts'),
        );
    }

    public function test_async_select_users_endpoint_returns_options(): void
    {
        $admin = $this->developer();
        User::factory()->create([
            'name' => 'Maria Async',
            'email' => 'maria.async@example.com',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.dev.components.async-select.users', [
            'search' => 'Maria',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Maria Async')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['value', 'label', 'description'],
                ],
                'meta' => ['current_page', 'last_page'],
            ]);
    }

    public function test_form_playground_page_is_displayed(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->get(route('admin.dev.components.form-playground'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('admin/dev/components/form-playground'),
        );
    }

    // -------------------------------------------------------------------------
    // cache/clear
    // -------------------------------------------------------------------------

    public function test_all_caches_can_be_cleared(): void
    {
        foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $command) {
            Artisan::shouldReceive('call')->once()->with($command)->andReturn(0);
        }

        $user = $this->developer();

        $response = $this->actingAs($user)->post(route('admin.dev.dev-tools.cache.clear'));

        $response->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // cache/clear-specific
    // -------------------------------------------------------------------------

    public function test_specific_cache_can_be_cleared(): void
    {
        $user = $this->developer();

        foreach (['cache', 'config', 'route', 'view'] as $type) {
            Artisan::shouldReceive('call')->once()->with($type.':clear')->andReturn(0);
            $response = $this->actingAs($user)->post(route('admin.dev.dev-tools.cache.clear-specific'), ['type' => $type]);
            $response->assertRedirect();
        }
    }

    public function test_specific_cache_rejects_invalid_type(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->post(route('admin.dev.dev-tools.cache.clear-specific'), ['type' => 'invalid']);

        $response->assertSessionHasErrors('type');
    }

    // -------------------------------------------------------------------------
    // optimize
    // -------------------------------------------------------------------------

    public function test_application_can_be_optimized(): void
    {
        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            Artisan::shouldReceive('call')->once()->with($command)->andReturn(0);
        }

        $user = $this->developer();

        $response = $this->actingAs($user)->post(route('admin.dev.dev-tools.optimize'));

        $response->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // env/update
    // -------------------------------------------------------------------------

    public function test_env_variable_can_be_updated(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->post(route('admin.dev.dev-tools.env.update'), [
            'key' => 'APP_NAME',
            'value' => 'hello',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertStringContainsString('APP_NAME="hello"', File::get(app()->environmentFilePath()));
    }

    public function test_env_update_rejects_invalid_key(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->post(route('admin.dev.dev-tools.env.update'), [
            'key' => 'invalid key!',
            'value' => 'value',
        ]);

        $response->assertSessionHasErrors('key');
    }

    public function test_env_update_requires_both_fields(): void
    {
        $user = $this->developer();

        $response = $this->actingAs($user)->post(route('admin.dev.dev-tools.env.update'), []);

        $response->assertSessionHasErrors(['key', 'value']);
    }
}
