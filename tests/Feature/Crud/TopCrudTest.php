<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Database\Seeders\TopSeeder;
use App\Modules\Playlists\Models\Top;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TopCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_seeder_creates_initial_catalog_idempotently(): void
    {
        $this->seed(TopSeeder::class);
        $this->seed(TopSeeder::class);

        $this->assertSame(5, Top::query()->count());

        $tops = Top::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('slug');

        $this->assertSame('TOP 1', $tops->get('top-1')->name);
        $this->assertSame(1, $tops->get('top-1')->default_slots_quantity);
        $this->assertSame('TOP 5', $tops->get('top-5')->name);
        $this->assertSame(5, $tops->get('top-5')->default_slots_quantity);
        $this->assertSame('TOP 10', $tops->get('top-10')->name);
        $this->assertSame(10, $tops->get('top-10')->default_slots_quantity);
        $this->assertSame('TOP 20', $tops->get('top-20')->name);
        $this->assertSame(20, $tops->get('top-20')->default_slots_quantity);
        $this->assertSame('Destaque', $tops->get('destaque')->name);
        $this->assertTrue($tops->get('destaque')->is_active);
    }

    public function test_authorized_user_can_view_top_pages(): void
    {
        $user = $this->userWithPermissions(['playlist-tops.view']);
        $top = Top::factory()->create([
            'name' => 'TOP 1',
            'slug' => 'top-1',
            'default_slots_quantity' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('playlist-tops.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/playlist-tops/index')
                ->where('permissions.can_create', false)
            );

        $this->actingAs($user)
            ->get(route('playlist-tops.show', $top))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/playlist-tops/show')
                ->where('topData.data.uuid', $top->uuid)
                ->where('topData.data.name', 'TOP 1')
            );
    }

    public function test_authorized_user_can_create_update_delete_and_restore_top(): void
    {
        $user = $this->userWithPermissions([
            'playlist-tops.view',
            'playlist-tops.create',
            'playlist-tops.update',
            'playlist-tops.delete',
            'playlist-tops.restore',
        ]);

        $this->actingAs($user)
            ->post(route('playlist-tops.store'), [
                'name' => 'TOP Curadoria',
                'default_slots_quantity' => 3,
                'description' => 'Posição comercial especial.',
                'sort_order' => 15,
                'is_active' => true,
                'slug' => 'slug-nao-deve-ser-usado',
            ])
            ->assertRedirect();

        $top = Top::query()->where('slug', 'top-curadoria')->firstOrFail();

        $this->assertSame(3, $top->default_slots_quantity);
        $this->assertSame('Posição comercial especial.', $top->description);

        $this->actingAs($user)
            ->put(route('playlist-tops.update', $top), [
                'name' => 'TOP Curadoria Premium',
                'default_slots_quantity' => 4,
                'description' => 'Posição revisada.',
                'sort_order' => 25,
                'is_active' => false,
                'slug' => 'outro-slug-ignorado',
            ])
            ->assertRedirect(route('playlist-tops.show', $top));

        $top->refresh();

        $this->assertSame('TOP Curadoria Premium', $top->name);
        $this->assertSame('top-curadoria', $top->slug);
        $this->assertSame(4, $top->default_slots_quantity);
        $this->assertFalse($top->is_active);

        $this->actingAs($user)
            ->delete(route('playlist-tops.destroy', $top))
            ->assertRedirect(route('playlist-tops.index'));

        $this->assertSoftDeleted($top);

        $this->actingAs($user)
            ->patch(route('playlist-tops.restore', $top->uuid))
            ->assertRedirect(route('playlist-tops.show', $top->uuid));

        $this->assertFalse($top->refresh()->trashed());
    }

    public function test_top_slug_is_generated_uniquely_from_name(): void
    {
        $user = $this->userWithPermissions(['playlist-tops.view', 'playlist-tops.create']);

        Top::factory()->create(['name' => 'TOP Curadoria', 'slug' => 'top-curadoria']);

        $this->actingAs($user)
            ->post(route('playlist-tops.store'), [
                'name' => 'TOP Curadoria',
                'default_slots_quantity' => 1,
                'sort_order' => 10,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tops', ['slug' => 'top-curadoria-1']);
    }

    public function test_top_validation_rejects_invalid_payloads(): void
    {
        $user = $this->userWithPermissions(['playlist-tops.view', 'playlist-tops.create']);

        $this->actingAs($user)
            ->post(route('playlist-tops.store'), [
                'name' => '',
                'default_slots_quantity' => 0,
                'sort_order' => -1,
            ])
            ->assertSessionHasErrors([
                'name',
                'default_slots_quantity',
                'sort_order',
            ]);
    }

    public function test_unauthorized_user_cannot_mutate_tops(): void
    {
        $user = User::factory()->create();
        $top = Top::factory()->create([
            'name' => 'TOP 1',
            'slug' => 'top-1',
        ]);

        $this->actingAs($user)->get(route('playlist-tops.index'))->assertForbidden();
        $this->actingAs($user)->get(route('playlist-tops.create'))->assertForbidden();
        $this->actingAs($user)->post(route('playlist-tops.store'), [
            'name' => 'TOP bloqueado',
            'default_slots_quantity' => 1,
            'sort_order' => 10,
        ])->assertForbidden();
        $this->actingAs($user)->put(route('playlist-tops.update', $top), [
            'name' => 'TOP atualizado',
            'default_slots_quantity' => 2,
            'sort_order' => 20,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('playlist-tops.destroy', $top))->assertForbidden();

        $top->refresh();

        $this->assertSame('TOP 1', $top->name);
        $this->assertNull($top->deleted_at);
        $this->assertDatabaseMissing('tops', ['slug' => 'top-bloqueado']);
    }

    public function test_api_returns_top_records_and_can_filter_active_tops(): void
    {
        $user = $this->userWithPermissions(['playlist-tops.view']);

        Top::factory()->create([
            'name' => 'TOP ativo',
            'slug' => 'top-ativo',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        Top::factory()->create([
            'name' => 'TOP inativo',
            'slug' => 'top-inativo',
            'is_active' => false,
            'sort_order' => 10,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/playlist-tops')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'TOP inativo');

        $this->actingAs($user)
            ->getJson('/api/v1/playlist-tops?'.http_build_query([
                'filter' => [
                    'is_active' => [
                        'operator' => 'equals',
                        'value' => true,
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'TOP ativo');
    }

    public function test_playlist_tops_route_and_navigation_live_under_comercial_operacao(): void
    {
        $route = Route::getRoutes()->getByName('playlist-tops.index');

        $this->assertNotNull($route);
        $this->assertSame('playlist-tops', $route->uri());

        $navigation = config('navigation.modules');
        $comercialModule = collect($navigation)->firstWhere('slug', 'comercial');

        $this->assertContains('/playlist-tops', $comercialModule['route_prefixes']);

        $operacaoSection = collect($comercialModule['items'])->firstWhere('slug', 'comercial_operacao_section');

        $this->assertNotNull($operacaoSection);
        $this->assertSame(
            ['route' => 'playlist-tops.index'],
            collect($operacaoSection['items'])->firstWhere('slug', 'comercial_playlist_tops')['href'],
        );
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create();

        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $slug): int => Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace('.', ' ')->title()->toString(),
                    'group' => str($slug)->before('.')->toString(),
                ],
            )->id)
            ->all();

        $user->permissions()->sync($permissionIds);
        $user->load(['roles', 'permissions']);

        return $user;
    }
}
