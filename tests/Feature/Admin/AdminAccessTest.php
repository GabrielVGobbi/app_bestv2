<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_access_admin_companies_or_dev_areas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('companies.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.dev.dashboard'))
            ->assertForbidden();
    }

    public function test_regular_user_does_not_receive_admin_or_dev_navigation_modules(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('nav.modules.1')
                ->where('nav.modules.0.slug', 'gestao')
            );
    }

    public function test_admin_receives_admin_navigation_module(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('nav.modules.1.slug', 'admin')
                ->missing('nav.modules.2')
            );
    }

    public function test_developer_can_access_dev_area(): void
    {
        $developer = $this->userWithRole('developer');

        $this->actingAs($developer)
            ->get(route('admin.dev.dashboard'))
            ->assertOk();
    }

    private function userWithRole(string $roleSlug): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => str($roleSlug)->replace('-', ' ')->title()->toString()],
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);
        $user->load('roles');

        return $user;
    }
}
