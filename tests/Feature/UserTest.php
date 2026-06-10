<?php

namespace Tests\Feature;

use App\Actions\Users\CreateUserWithTemporaryPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function test_create_page_is_displayed(): void
    {
        $admin = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->get(route('users.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('app/users/create'));
    }

    public function test_create_page_requires_authentication(): void
    {
        $this->get(route('users.create'))->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_user_can_be_created_without_password(): void
    {
        $admin = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New User',
                'email' => 'newuser@example.com',
            ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'newuser@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotEmpty($user->password);
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue($user->must_change_password);
        $response->assertRedirect(route('users.show', $user));
    }

    public function test_user_can_be_created_from_index_modal(): void
    {
        $admin = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Modal User',
                'email' => 'modaluser@example.com',
                'redirect_to_index' => '1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'modaluser@example.com']);
        $this->assertDatabaseHas('users', [
            'email' => 'modaluser@example.com',
            'must_change_password' => true,
        ]);
    }

    public function test_created_user_receives_a_temporary_password(): void
    {
        $result = app(CreateUserWithTemporaryPassword::class)->handle([
            'name' => 'Invited User',
            'email' => 'invited@example.com',
        ]);

        $this->assertTrue(Hash::check($result['temporary_password'], $result['user']->password));
        $this->assertTrue($result['user']->must_change_password);
    }

    public function test_user_with_temporary_password_is_redirected_to_forced_password_change_page(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this
            ->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('password.force.edit'));
    }

    public function test_store_requires_unique_email(): void
    {
        $admin = User::factory()->create();
        $existing = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New User',
                'email' => $existing->email,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_store_requires_authentication(): void
    {
        $this->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    // -------------------------------------------------------------------------
    // edit
    // -------------------------------------------------------------------------

    public function test_edit_page_is_displayed(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->get(route('users.edit', $user));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('app/users/edit'));
    }

    public function test_edit_page_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->get(route('users.edit', $user))->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_user_can_be_updated(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->patch(route('users.update', $user), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.show', $user));

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_unchanged_when_email_unchanged(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        $this
            ->actingAs($admin)
            ->patch(route('users.update', $user), [
                'name' => 'New Name',
                'email' => $user->email,
            ]);

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_update_requires_valid_name(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->patch(route('users.update', $user), [
                'name' => '',
                'email' => $user->email,
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_update_requires_unique_email(): void
    {
        $admin = User::factory()->create();
        $existing = User::factory()->create();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->patch(route('users.update', $user), [
                'name' => $user->name,
                'email' => $existing->email,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_update_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->patch(route('users.update', $user), [
            'name' => 'Name',
            'email' => 'email@example.com',
        ])->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_user_can_be_deleted(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->delete(route('users.destroy', $user));

        $response->assertRedirect(route('users.index'));

        $this->assertModelMissing($user);
    }

    public function test_destroy_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->delete(route('users.destroy', $user))->assertRedirect(route('login'));

        $this->assertModelExists($user);
    }
}
