<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevToolsTest extends TestCase
{
    use RefreshDatabase;

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
                ->has('envVariables')
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
            'key' => 'TEST_VAR',
            'value' => 'hello',
        ]);

        $response->assertRedirect();
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

    private function developer(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'developer'],
            ['name' => 'Developer'],
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);
        $user->load('roles');

        return $user;
    }
}
