<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Permissions\Models\Role;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PermissionsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bestplay_roles_and_permissions_seeders_are_idempotent_and_match_initial_matrix(): void
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $expectedRoles = [
            'developer',
            'super-admin',
            'admin',
            'user',
            'finance',
            'manager',
            'commercial',
            'seller',
            'operation',
        ];

        $expectedPermissions = [
            'access-panel',
            'users.manage',
            'roles.manage',
            'permissions.manage',
            'companies.view',
            'companies.manage',
            'clients.view',
            'clients.manage',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',
            'addresses.manage',
            'services.view',
            'services.create',
            'services.update',
            'services.delete',
            'services.restore',
            'sales.view',
            'sales.view-own',
            'sales.create',
            'sales.update',
            'sales.quote',
            'sales.confirm',
            'sales.finish',
            'sales.cancel',
            'sales.delete',
            'sales.amounts.view',
            'sales.items.create',
            'sales.items.update',
            'sales.items.delete',
            'sales.items.playlist-slot.create',
            'sales.items.spotify-track.lookup',
            'sales.items.spotify-playlist.verify',
            'sales.items.discount',
            'playlists.view',
            'playlists.create',
            'playlists.update',
            'playlists.delete',
            'playlists.restore',
            'playlists.genres.manage',
            'playlists.availability.view',
            'playlist-tops.view',
            'playlist-tops.create',
            'playlist-tops.update',
            'playlist-tops.delete',
            'playlist-tops.restore',
            'playlist-tops.attach',
            'playlist-tops.configure',
            'playlist-tops.detach',
            'playlist-slots.availability.view',
            'playlist-slots.reserve',
            'playlist-slots.activate',
            'playlist-slots.schedule',
            'playlist-slots.cancel',
            'playlist-slots.view',
            'playlist-delivery-tasks.view',
            'playlist-delivery-tasks.complete',
            'playlist-delivery-tasks.change-track',
            'finance.view',
            'finance.manage',
            'finance.settings.manage',
            'finance.entries.view',
            'finance.entries.create',
            'finance.entries.update',
            'finance.entries.cancel',
            'finance.settlements.create',
            'finance.settlements.reverse',
            'finance.reports.view',
            'finance.reconciliation.manage',
            'finance.attachments.manage',
            'finance.accounts.view',
            'finance.accounts.create',
            'finance.accounts.update',
            'finance.accounts.archive',
            'finance.categories.view',
            'finance.categories.create',
            'finance.categories.update',
            'finance.categories.delete',
            'finance.payment-methods.view',
            'finance.payment-methods.create',
            'finance.payment-methods.update',
            'finance.payment-methods.delete',
        ];

        $this->assertEqualsCanonicalizing($expectedRoles, Role::query()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing($expectedPermissions, Permission::query()->pluck('slug')->all());
        $this->assertSame(count($expectedRoles), Role::query()->count());
        $this->assertSame(count($expectedPermissions), Permission::query()->count());

        $developerPermissionSlugs = Role::query()
            ->where('slug', 'developer')
            ->firstOrFail()
            ->permissions()
            ->pluck('slug')
            ->all();

        $financePermissionSlugs = Role::query()
            ->where('slug', 'finance')
            ->firstOrFail()
            ->permissions()
            ->pluck('slug')
            ->all();

        $sellerPermissionSlugs = Role::query()
            ->where('slug', 'seller')
            ->firstOrFail()
            ->permissions()
            ->pluck('slug')
            ->all();

        $this->assertEqualsCanonicalizing($expectedPermissions, $developerPermissionSlugs);
        $this->assertContains('finance.manage', $financePermissionSlugs);
        $this->assertContains('suppliers.view', $financePermissionSlugs);
        $this->assertContains('finance.entries.view', $financePermissionSlugs);
        $this->assertContains('finance.entries.create', $financePermissionSlugs);
        $this->assertContains('finance.settlements.create', $financePermissionSlugs);
        $this->assertContains('finance.settlements.reverse', $financePermissionSlugs);
        $this->assertContains('finance.reports.view', $financePermissionSlugs);
        $this->assertContains('finance.reconciliation.manage', $financePermissionSlugs);
        $this->assertContains('finance.attachments.manage', $financePermissionSlugs);
        $this->assertContains('finance.accounts.view', $financePermissionSlugs);
        $this->assertContains('finance.accounts.create', $financePermissionSlugs);
        $this->assertContains('finance.accounts.update', $financePermissionSlugs);
        $this->assertContains('finance.accounts.archive', $financePermissionSlugs);
        $this->assertContains('finance.categories.view', $financePermissionSlugs);
        $this->assertContains('finance.payment-methods.view', $financePermissionSlugs);
        $this->assertNotContains('finance.origins.manage', $financePermissionSlugs);
        $this->assertNotContains('sales.create', $financePermissionSlugs);
        $this->assertContains('services.view', $sellerPermissionSlugs);
        $this->assertContains('playlist-tops.view', $sellerPermissionSlugs);
        $this->assertNotContains('services.create', $sellerPermissionSlugs);
        $this->assertContains('sales.create', $sellerPermissionSlugs);
        $this->assertContains('sales.update', $sellerPermissionSlugs);
        $this->assertContains('sales.quote', $sellerPermissionSlugs);
        $this->assertContains('clients.manage', $sellerPermissionSlugs);
        $this->assertNotContains('companies.manage', $sellerPermissionSlugs);
        $this->assertNotContains('sales.confirm', $sellerPermissionSlugs);
    }

    public function test_user_seeder_creates_one_login_user_for_each_bestplay_role(): void
    {
        $this->seed([RolesSeeder::class, UserSeeder::class]);
        $this->seed([RolesSeeder::class, UserSeeder::class]);

        $expectedUsers = [
            'developer' => ['email' => 'super@admin.com', 'password' => 'superadmin'],
            'super-admin' => ['email' => 'super-admin@bestplay.local', 'password' => 'password'],
            'admin' => ['email' => 'admin@admin.com', 'password' => 'password'],
            'user' => ['email' => 'user@test.com', 'password' => 'password'],
            'finance' => ['email' => 'finance@bestplay.local', 'password' => 'password'],
            'manager' => ['email' => 'manager@bestplay.local', 'password' => 'password'],
            'commercial' => ['email' => 'commercial@bestplay.local', 'password' => 'password'],
            'seller' => ['email' => 'seller@bestplay.local', 'password' => 'password'],
            'operation' => ['email' => 'operation@bestplay.local', 'password' => 'password'],
        ];

        $this->assertSame(count($expectedUsers), User::query()->count());

        foreach ($expectedUsers as $roleSlug => $credentials) {
            $user = User::query()
                ->where('email', $credentials['email'])
                ->with('roles')
                ->firstOrFail();

            $this->assertEqualsCanonicalizing([$roleSlug], $user->roles->pluck('slug')->all());
            $this->assertTrue(Hash::check($credentials['password'], $user->password));
            $this->assertNotNull($user->email_verified_at);
        }
    }

    // -------------------------------------------------------------------------
    // Roles API - index
    // -------------------------------------------------------------------------

    public function test_roles_index_returns_paginated_list(): void
    {
        $user = User::factory()->create();
        Role::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson(route('api.roles.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'description', 'permissions_count', 'users_count']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.total', 3);
    }

    public function test_roles_index_can_be_searched(): void
    {
        $user = User::factory()->create();
        Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);

        $response = $this->actingAs($user)->getJson(route('api.roles.index', ['search' => 'admin']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Administrator');
    }

    public function test_roles_index_includes_counts(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission);
        $role->users()->attach($user);

        $response = $this->actingAs($user)->getJson(route('api.roles.index'));

        $response->assertOk();
        $response->assertJsonPath('data.0.permissions_count', 1);
        $response->assertJsonPath('data.0.users_count', 1);
    }

    public function test_roles_index_requires_authentication(): void
    {
        $this->getJson(route('api.roles.index'))->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Roles API - store
    // -------------------------------------------------------------------------

    public function test_role_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.roles.store'), [
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Management role',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Manager');
        $response->assertJsonPath('data.slug', 'manager');
        $this->assertDatabaseHas('roles', ['slug' => 'manager']);
    }

    public function test_role_creation_requires_name_and_slug(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.roles.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'slug']);
    }

    public function test_role_slug_must_be_unique(): void
    {
        $user = User::factory()->create();
        Role::factory()->create(['slug' => 'admin']);

        $response = $this->actingAs($user)->postJson(route('api.roles.store'), [
            'name' => 'Admin 2',
            'slug' => 'admin',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_role_creation_requires_authentication(): void
    {
        $this->postJson(route('api.roles.store'), ['name' => 'Test', 'slug' => 'test'])->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Roles API - update
    // -------------------------------------------------------------------------

    public function test_role_can_be_updated(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $response = $this->actingAs($user)->putJson(route('api.roles.update', $role), [
            'name' => 'New Name',
            'slug' => 'new-name',
            'description' => 'Updated description',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'New Name']);
    }

    public function test_role_slug_unique_rule_ignores_self_on_update(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);

        $response = $this->actingAs($user)->putJson(route('api.roles.update', $role), [
            'name' => 'Admin Updated',
            'slug' => 'admin',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Admin Updated']);
    }

    public function test_role_update_requires_authentication(): void
    {
        $role = Role::factory()->create();
        $this->putJson(route('api.roles.update', $role), ['name' => 'Test', 'slug' => 'test'])->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Roles API - destroy
    // -------------------------------------------------------------------------

    public function test_role_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $response = $this->actingAs($user)->deleteJson(route('api.roles.destroy', $role));

        $response->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_role_deletion_requires_authentication(): void
    {
        $role = Role::factory()->create();
        $this->deleteJson(route('api.roles.destroy', $role))->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Roles API - syncPermissions
    // -------------------------------------------------------------------------

    public function test_role_permissions_can_be_synced(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission1 = Permission::factory()->create();
        $permission2 = Permission::factory()->create();
        $permission3 = Permission::factory()->create();

        $role->permissions()->attach([$permission1->id, $permission2->id]);

        $response = $this->actingAs($user)->putJson(route('api.roles.sync-permissions', $role), [
            'permissions' => [$permission2->id, $permission3->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('roles_permissions', ['role_id' => $role->id, 'permission_id' => $permission1->id]);
        $this->assertDatabaseHas('roles_permissions', ['role_id' => $role->id, 'permission_id' => $permission2->id]);
        $this->assertDatabaseHas('roles_permissions', ['role_id' => $role->id, 'permission_id' => $permission3->id]);
    }

    public function test_role_sync_permissions_can_clear_all(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->permissions()->attach($permission);

        $response = $this->actingAs($user)->putJson(route('api.roles.sync-permissions', $role), [
            'permissions' => [],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('roles_permissions', ['role_id' => $role->id]);
    }

    public function test_role_sync_permissions_validates_permission_ids_exist(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $response = $this->actingAs($user)->putJson(route('api.roles.sync-permissions', $role), [
            'permissions' => [99999],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_role_sync_permissions_requires_authentication(): void
    {
        $role = Role::factory()->create();
        $this->putJson(route('api.roles.sync-permissions', $role), ['permissions' => []])->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Permissions API - index
    // -------------------------------------------------------------------------

    public function test_permissions_index_returns_paginated_list(): void
    {
        $user = User::factory()->create();
        Permission::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson(route('api.permissions.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'group', 'description']],
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
        $response->assertJsonPath('meta.total', 3);
    }

    public function test_permissions_index_can_be_searched_by_name(): void
    {
        $user = User::factory()->create();
        Permission::factory()->create(['name' => 'Create User', 'slug' => 'users.create', 'group' => 'Users']);
        Permission::factory()->create(['name' => 'Delete Post', 'slug' => 'posts.delete', 'group' => 'Posts']);

        $response = $this->actingAs($user)->getJson(route('api.permissions.index', ['search' => 'Create']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Create User');
    }

    public function test_permissions_index_can_be_searched_by_group(): void
    {
        $user = User::factory()->create();
        Permission::factory()->create(['name' => 'Create User', 'slug' => 'users.create', 'group' => 'Users']);
        Permission::factory()->create(['name' => 'Delete Post', 'slug' => 'posts.delete', 'group' => 'Posts']);

        $response = $this->actingAs($user)->getJson(route('api.permissions.index', ['search' => 'Users']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.group', 'Users');
    }

    public function test_permissions_index_requires_authentication(): void
    {
        $this->getJson(route('api.permissions.index'))->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Permissions API - store
    // -------------------------------------------------------------------------

    public function test_permission_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.permissions.store'), [
            'name' => 'Create Post',
            'slug' => 'posts.create',
            'group' => 'Posts',
            'description' => 'Allows creating posts',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Create Post');
        $response->assertJsonPath('data.slug', 'posts.create');
        $this->assertDatabaseHas('permissions', ['slug' => 'posts.create']);
    }

    public function test_permission_creation_requires_name_and_slug(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.permissions.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'slug']);
    }

    public function test_permission_slug_must_be_unique(): void
    {
        $user = User::factory()->create();
        Permission::factory()->create(['slug' => 'posts.create']);

        $response = $this->actingAs($user)->postJson(route('api.permissions.store'), [
            'name' => 'Create Post 2',
            'slug' => 'posts.create',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_permission_creation_requires_authentication(): void
    {
        $this->postJson(route('api.permissions.store'), ['name' => 'Test', 'slug' => 'test'])->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Permissions API - update
    // -------------------------------------------------------------------------

    public function test_permission_can_be_updated(): void
    {
        $user = User::factory()->create();
        $permission = Permission::factory()->create(['name' => 'Old Name', 'slug' => 'old.name']);

        $response = $this->actingAs($user)->putJson(route('api.permissions.update', $permission), [
            'name' => 'New Name',
            'slug' => 'new.name',
            'group' => 'Updated Group',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'New Name']);
    }

    public function test_permission_slug_unique_rule_ignores_self_on_update(): void
    {
        $user = User::factory()->create();
        $permission = Permission::factory()->create(['name' => 'Create Post', 'slug' => 'posts.create']);

        $response = $this->actingAs($user)->putJson(route('api.permissions.update', $permission), [
            'name' => 'Create Post Updated',
            'slug' => 'posts.create',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'Create Post Updated']);
    }

    public function test_permission_update_requires_authentication(): void
    {
        $permission = Permission::factory()->create();
        $this->putJson(route('api.permissions.update', $permission), ['name' => 'Test', 'slug' => 'test'])->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Permissions API - destroy
    // -------------------------------------------------------------------------

    public function test_permission_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $permission = Permission::factory()->create();

        $response = $this->actingAs($user)->deleteJson(route('api.permissions.destroy', $permission));

        $response->assertNoContent();
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_permission_deletion_requires_authentication(): void
    {
        $permission = Permission::factory()->create();
        $this->deleteJson(route('api.permissions.destroy', $permission))->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Users API - syncRolesPermissions
    // -------------------------------------------------------------------------

    public function test_user_roles_and_permissions_can_be_synced(): void
    {
        $actor = $this->userWithRole('admin');
        $target = User::factory()->create();
        $role1 = Role::factory()->create();
        $role2 = Role::factory()->create();
        $perm1 = Permission::factory()->create();
        $perm2 = Permission::factory()->create();

        $target->roles()->attach($role1);
        $target->permissions()->attach($perm1);

        $response = $this->actingAs($actor)->putJson(route('api.users.sync-roles-permissions', $target), [
            'roles' => [$role2->id],
            'permissions' => [$perm2->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('users_roles', ['user_id' => $target->id, 'role_id' => $role1->id]);
        $this->assertDatabaseHas('users_roles', ['user_id' => $target->id, 'role_id' => $role2->id]);
        $this->assertDatabaseMissing('users_permissions', ['user_id' => $target->id, 'permission_id' => $perm1->id]);
        $this->assertDatabaseHas('users_permissions', ['user_id' => $target->id, 'permission_id' => $perm2->id]);
    }

    public function test_user_sync_can_clear_all_roles_and_permissions(): void
    {
        $actor = $this->userWithRole('admin');
        $target = User::factory()->create();
        $role = Role::factory()->create();
        $perm = Permission::factory()->create();
        $target->roles()->attach($role);
        $target->permissions()->attach($perm);

        $response = $this->actingAs($actor)->putJson(route('api.users.sync-roles-permissions', $target), [
            'roles' => [],
            'permissions' => [],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('users_roles', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('users_permissions', ['user_id' => $target->id]);
    }

    public function test_user_sync_validates_role_ids_exist(): void
    {
        $actor = $this->userWithRole('admin');
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->putJson(route('api.users.sync-roles-permissions', $target), [
            'roles' => [99999],
            'permissions' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['roles.0']);
    }

    public function test_user_sync_validates_permission_ids_exist(): void
    {
        $actor = $this->userWithRole('admin');
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->putJson(route('api.users.sync-roles-permissions', $target), [
            'roles' => [],
            'permissions' => [99999],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_user_sync_requires_roles_and_permissions_keys_to_be_present(): void
    {
        $actor = $this->userWithRole('admin');
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->putJson(route('api.users.sync-roles-permissions', $target), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['roles', 'permissions']);
    }

    public function test_user_sync_requires_authentication(): void
    {
        $target = User::factory()->create();
        $this->putJson(route('api.users.sync-roles-permissions', $target), ['roles' => [], 'permissions' => []])->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Web routes - Roles
    // -------------------------------------------------------------------------

    public function test_roles_index_page_renders(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->get(route('roles.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('app/roles/index'));
    }

    public function test_role_show_page_renders(): void
    {
        $user = $this->userWithRole('admin');
        $role = Role::factory()->create();

        $response = $this->actingAs($user)->get(route('roles.show', $role));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('app/roles/show')
            ->has('roleData.data'));
    }

    public function test_role_edit_page_renders(): void
    {
        $user = $this->userWithRole('admin');
        $role = Role::factory()->create();

        $response = $this->actingAs($user)->get(route('roles.edit', $role));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('app/roles/edit')
            ->has('roleData.data'));
    }

    public function test_role_permissions_page_renders(): void
    {
        $user = $this->userWithRole('admin');
        $role = Role::factory()->create();

        $response = $this->actingAs($user)->get(route('roles.permissions', $role));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('app/roles/permissions')
            ->has('roleData.data')
            ->has('allPermissions'));
    }

    // -------------------------------------------------------------------------
    // Web routes - Permissions
    // -------------------------------------------------------------------------

    public function test_permissions_index_page_renders(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->get(route('permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('app/permissions/index'));
    }

    public function test_permission_show_page_renders(): void
    {
        $user = $this->userWithRole('admin');
        $permission = Permission::factory()->create();

        $response = $this->actingAs($user)->get(route('permissions.show', $permission));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('app/permissions/show')
            ->has('permissionData.data'));
    }

    public function test_permission_edit_page_renders(): void
    {
        $user = $this->userWithRole('admin');
        $permission = Permission::factory()->create();

        $response = $this->actingAs($user)->get(route('permissions.edit', $permission));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('app/permissions/edit')
            ->has('permissionData.data'));
    }

    // -------------------------------------------------------------------------
    // Web routes - User roles/permissions sub-pages
    // -------------------------------------------------------------------------

    public function test_user_show_page_includes_roles_and_permissions(): void
    {
        $actor = $this->userWithRole('admin');
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $perm = Permission::factory()->create();
        $user->roles()->attach($role);
        $user->permissions()->attach($perm);

        $response = $this->actingAs($actor)->get(route('users.show', $user->uuid));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('app/users/show')
            ->has('userData.data.roles', 1)
            ->has('userData.data.permissions', 1));
    }

    public function test_user_roles_page_renders(): void
    {
        $actor = $this->userWithRole('admin');
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->get(route('users.roles', $target->uuid));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('app/users/roles')
            ->has('userData.data')
            ->has('allRoles'));
    }

    public function test_user_permissions_page_renders(): void
    {
        $actor = $this->userWithRole('admin');
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->get(route('users.permissions', $target->uuid));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('app/users/permissions')
            ->has('userData.data')
            ->has('allPermissions'));
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
