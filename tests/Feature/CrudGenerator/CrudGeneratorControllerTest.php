<?php

declare(strict_types=1);

namespace Tests\Feature\CrudGenerator;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrudGeneratorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateProjectFiles();
    }

    private function account(?string $slug = 'developer'): User
    {
        $user = User::factory()->create();

        if ($slug !== null) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->getConnection()->table('roles')->where('id', $role->id)->update(['slug' => $slug]);
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'model' => 'ScreenClient', 'module' => 'ScreenClients', 'target' => 'module',
            'register_provider' => false, 'with_export' => false, 'force' => true,
            'fields' => [['name' => 'name', 'type' => 'string']],
        ];
    }

    private function assertTechnicalEndpointsForbidden(): void
    {
        $this->getJson(route('admin.dev.crud-generator.index'))->assertForbidden();
        $this->postJson(route('admin.dev.crud-generator.preview'), $this->payload())->assertForbidden();
        $this->postJson(route('admin.dev.crud-generator.apply'), $this->payload())->assertForbidden();
        $this->assertDirectoryDoesNotExist(app_path('Modules/ScreenClients'));
    }

    public function test_guest_cannot_use_generator(): void
    {
        $this->getJson(route('admin.dev.crud-generator.index'))->assertUnauthorized();
        $this->postJson(route('admin.dev.crud-generator.preview'), $this->payload())->assertUnauthorized();
        $this->postJson(route('admin.dev.crud-generator.apply'), $this->payload())->assertUnauthorized();
        $this->assertDirectoryDoesNotExist(app_path('Modules/ScreenClients'));
    }

    #[DataProvider('nonDeveloperRoles')]
    public function test_accounts_without_literal_developer_cannot_use_generator(?string $slug): void
    {
        $this->actingAs($this->account($slug));
        $this->assertTechnicalEndpointsForbidden();
    }

    public static function nonDeveloperRoles(): array
    {
        return [[null], ['user'], ['admin'], ['super-admin'], ['Developer']];
    }

    #[DataProvider('deniedEnvironments')]
    public function test_developer_cannot_use_generator_in_forbidden_environment(string $environment): void
    {
        $this->actingAs($this->account());
        $this->app->instance('env', $environment);
        config(['app.env' => $environment]);
        $this->assertTechnicalEndpointsForbidden();
    }

    public static function deniedEnvironments(): array
    {
        return [['production'], ['staging'], ['preview'], ['LOCAL']];
    }

    public function test_environment_disagreement_cannot_enable_generator(): void
    {
        config(['app.env' => 'local']);
        $this->actingAs($this->account());
        $this->assertTechnicalEndpointsForbidden();
    }

    public function test_bearer_star_cannot_enable_generator_with_web_developer(): void
    {
        $developer = $this->account();
        $token = $developer->createToken('Fixture', ['*'])->plainTextToken;
        $this->actingAs($developer, 'web')->withHeader('Authorization', 'Bearer '.$token);
        $this->assertTechnicalEndpointsForbidden();
    }

    public function test_technical_permission_and_changed_super_role_cannot_enable_generator(): void
    {
        $user = $this->account('admin');
        $permission = Permission::create(['name' => 'Fixture ability', 'slug' => 'fixture-ability']);
        $permission->getConnection()->table('permissions')->where('id', $permission->id)->update(['slug' => 'useDeveloperTools']);
        $user->permissions()->attach($permission->id);
        config(['permissions.super_role' => 'admin', 'permissions.functional' => ['useDeveloperTools']]);
        $this->actingAs($user);
        $this->assertTechnicalEndpointsForbidden();
    }

    #[DataProvider('allowedEnvironments')]
    public function test_developer_can_apply_only_to_owned_fixture(string $environment): void
    {
        foreach (['config/navigation.php', 'resources/js/lib/api/api-routes.ts'] as $path) {
            File::ensureDirectoryExists(dirname(base_path($path)));
            File::copy(dirname(__DIR__, 3).'/'.$path, base_path($path));
        }

        $this->app->instance('env', $environment);
        config(['app.env' => $environment]);
        $this->actingAs($this->account())->postJson(route('admin.dev.crud-generator.apply'), $this->payload())
            ->assertOk()->assertJsonPath('data.model', 'ScreenClient')->assertJsonPath('data.files.0.exists', true);
        $this->assertFileExists(app_path('Modules/ScreenClients/Models/ScreenClient.php'));
    }

    public static function allowedEnvironments(): array
    {
        return [['local'], ['testing']];
    }

    public function test_developer_can_preview_crud_from_admin_screen(): void
    {
        $user = $this->account();

        $this->actingAs($user)
            ->postJson('/admin/dev/crud-generator/preview', [
                'model' => 'ScreenClient',
                'target' => 'module',
                'module' => 'ScreenClients',
                'icon' => 'TableProperties',
                'with_export' => true,
                'with_observer' => false,
                'with_policy' => true,
                'with_factory' => true,
                'with_seeder' => true,
                'with_tests' => true,
                'register_provider' => false,
                'fields' => [
                    [
                        'name' => 'name',
                        'type' => 'string',
                        'nullable' => false,
                        'unique' => false,
                        'searchable' => true,
                        'filterable' => true,
                        'sortable' => true,
                        'exportable' => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.model', 'ScreenClient')
            ->assertJsonPath('data.target', 'module')
            ->assertJsonPath('data.module', 'ScreenClients')
            ->assertJsonFragment([
                'Skip ServiceProvider generation and bootstrap/providers.php registration for App\Modules\ScreenClients\Providers\ScreenClientsServiceProvider',
            ]);

        $this->assertFileDoesNotExist(app_path('Modules/ScreenClients/Models/ScreenClient.php'));
    }
}
