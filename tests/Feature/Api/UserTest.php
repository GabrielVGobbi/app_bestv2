<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_users_list_is_paginated(): void
    {
        $actor = User::factory()->create(['created_at' => '2026-01-01 12:00:00']);
        User::factory(20)->create();

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['uuid', 'name', 'email']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
    }

    public function test_users_list_respects_per_page(): void
    {
        $actor = User::factory()->create(['created_at' => '2026-01-01 12:00:00']);
        User::factory(10)->create();

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', ['per_page' => 5]));

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.per_page', 5);
    }

    public function test_users_can_be_searched_by_name(): void
    {
        $actor = User::factory()->create(['created_at' => '2026-01-01 12:00:00']);
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', ['search' => 'john']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_users_can_be_searched_by_email(): void
    {
        $actor = User::factory()->create(['created_at' => '2026-01-01 12:00:00']);
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', ['search' => 'jane@example']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Jane Smith');
    }

    public function test_users_can_be_filtered_by_role(): void
    {
        $actor = User::factory()->create();
        $commercialRole = Role::factory()->create(['slug' => 'commercial']);
        $financeRole = Role::factory()->create(['slug' => 'finance']);
        $commercialUser = User::factory()->create(['name' => 'Commercial User']);
        $financeUser = User::factory()->create(['name' => 'Finance User']);

        $commercialUser->roles()->attach($commercialRole);
        $financeUser->roles()->attach($financeRole);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', ['role' => 'commercial']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Commercial User');
    }

    public function test_users_can_be_filtered_by_allowed_columns(): void
    {
        $actor = User::factory()->create();
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', ['filter' => ['email' => 'john@example.com']]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_users_can_be_filtered_with_contains_operator(): void
    {
        $actor = User::factory()->create();
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', [
                'filter' => [
                    'name' => [
                        'operator' => 'contains',
                        'value' => 'ohn',
                    ],
                ],
            ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_users_can_be_filtered_with_in_operator(): void
    {
        $actor = User::factory()->create(['email' => 'actor@example.com']);
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', [
                'filter' => [
                    'email' => [
                        'operator' => 'in',
                        'value' => ['john@example.com', 'jane@example.com'],
                    ],
                ],
            ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_users_can_be_filtered_with_between_operator(): void
    {
        $actor = User::factory()->create(['created_at' => '2026-01-01 12:00:00']);
        User::factory()->create(['name' => 'Old User', 'created_at' => '2026-04-01 12:00:00']);
        User::factory()->create(['name' => 'Current User', 'created_at' => '2026-05-02 12:00:00']);
        User::factory()->create(['name' => 'Future User', 'created_at' => '2026-06-01 12:00:00']);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', [
                'filter' => [
                    'created_at' => [
                        'operator' => 'between',
                        'value' => [
                            'from' => '2026-05-01 00:00:00',
                            'to' => '2026-05-31 23:59:59',
                        ],
                    ],
                ],
            ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Current User');
    }

    public function test_users_can_be_filtered_with_gte_and_lte_operators(): void
    {
        $actor = User::factory()->create(['created_at' => '2026-01-01 12:00:00']);
        User::factory()->create(['name' => 'Alpha User', 'created_at' => '2026-05-01 12:00:00']);
        User::factory()->create(['name' => 'Beta User', 'created_at' => '2026-05-10 12:00:00']);
        User::factory()->create(['name' => 'Gamma User', 'created_at' => '2026-05-20 12:00:00']);

        $gteResponse = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', [
                'filter' => [
                    'created_at' => [
                        'operator' => 'gte',
                        'value' => '2026-05-10 00:00:00',
                    ],
                ],
            ]));

        $gteResponse->assertOk();
        $gteResponse->assertJsonCount(2, 'data');

        $lteResponse = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', [
                'filter' => [
                    'created_at' => [
                        'operator' => 'lte',
                        'value' => '2026-05-10 23:59:59',
                    ],
                ],
            ]));

        $lteResponse->assertOk();
        $lteResponse->assertJsonCount(3, 'data');
    }

    public function test_users_can_be_filtered_with_null_operators(): void
    {
        $actor = User::factory()->create();
        User::factory()->create(['name' => 'Verified User', 'email_verified_at' => now()]);
        User::factory()->unverified()->create(['name' => 'Unverified User']);

        $nullResponse = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', [
                'filter' => [
                    'email_verified_at' => ['operator' => 'null'],
                ],
            ]));

        $nullResponse->assertOk();
        $nullResponse->assertJsonCount(1, 'data');
        $nullResponse->assertJsonPath('data.0.name', 'Unverified User');

        $notNullResponse = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', [
                'filter' => [
                    'email_verified_at' => ['operator' => 'not_null'],
                ],
            ]));

        $notNullResponse->assertOk();
        $notNullResponse->assertJsonCount(2, 'data');
    }

    public function test_users_ignore_filters_that_are_not_allowed_by_the_model(): void
    {
        $actor = User::factory()->create();
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', ['filter' => ['password' => 'secret']]));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_users_can_be_sorted(): void
    {
        $actor = User::factory()->create(['name' => 'Zoo User']);
        User::factory()->create(['name' => 'Alpha User']);

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', ['sort' => 'name', 'direction' => 'asc']));

        $response->assertOk();
        $this->assertSame('Alpha User', $response->json('data.0.name'));
    }

    public function test_users_ignore_sort_columns_that_are_not_allowed_by_the_model(): void
    {
        $actor = User::factory()->create();

        $response = $this
            ->actingAs($actor)
            ->getJson(route('api.users.index', ['sort' => 'password', 'direction' => 'asc']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_users_list_requires_authentication(): void
    {
        $this->getJson(route('api.users.index'))->assertUnauthorized();
    }
}
