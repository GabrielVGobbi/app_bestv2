<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Services\Database\Seeders\ServiceSeeder;
use App\Modules\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_seeder_creates_initial_catalog_idempotently(): void
    {
        $this->seed(ServiceSeeder::class);
        $this->seed(ServiceSeeder::class);

        $this->assertSame(5, Service::query()->count());

        $services = Service::query()
            ->orderBy('slug')
            ->get()
            ->keyBy('slug');

        $this->assertTrue($services->has('ads'));
        $this->assertTrue($services->has('curso'));
        $this->assertTrue($services->has('ebook'));
        $this->assertTrue($services->has('selo'));
        $this->assertTrue($services->has('venda-de-ativo'));
        $this->assertSame(0, $services->get('ads')->base_price);
        $this->assertTrue($services->get('ads')->is_active);
    }

    public function test_authorized_user_can_view_service_pages(): void
    {
        $user = $this->userWithPermissions(['services.view']);
        $service = Service::factory()->create([
            'name' => 'Ads',
            'slug' => 'ads',
        ]);

        $this->actingAs($user)
            ->get(route('services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/services/index')
                ->where('permissions.can_create', false)
            );

        $this->actingAs($user)
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/services/show')
                ->where('serviceData.data.uuid', $service->uuid)
                ->missing('serviceData.data.type')
            );
    }

    public function test_index_exposes_update_permission_for_inline_editing(): void
    {
        $user = $this->userWithPermissions(['services.view', 'services.update']);

        $this->actingAs($user)
            ->get(route('services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/services/index')
                ->where('permissions.can_update', true)
            );
    }

    public function test_authorized_user_can_create_update_delete_and_restore_service(): void
    {
        $user = $this->userWithPermissions([
            'services.view',
            'services.create',
            'services.update',
            'services.delete',
            'services.restore',
        ]);

        $this->actingAs($user)
            ->post(route('services.store'), [
                'name' => 'Campanha Ads',
                'description' => 'Pacote comercial de anuncios.',
                'base_price' => '1299.90',
                'currency' => 'BRL',
                'is_active' => true,
                'metadata' => [
                    'requires_period' => true,
                    'requires_asset_reference' => false,
                    'delivery_mode' => 'manual',
                    'default_notes' => 'Validar briefing antes da venda.',
                ],
            ])
            ->assertRedirect();

        $service = Service::query()->where('slug', 'campanha-ads')->firstOrFail();

        $this->assertSame(129990, $service->base_price);
        $this->assertSame('BRL', $service->currency);
        $this->assertSame('manual', $service->metadata['delivery_mode']);

        $this->actingAs($user)
            ->put(route('services.update', $service), [
                'name' => 'Campanha Ads Premium',
                'description' => 'Pacote atualizado.',
                'base_price' => 250000,
                'currency' => 'BRL',
                'is_active' => false,
                'metadata' => [
                    'requires_period' => true,
                    'requires_asset_reference' => false,
                    'delivery_mode' => 'operational',
                    'default_notes' => null,
                ],
            ])
            ->assertRedirect(route('services.show', $service));

        $service->refresh();

        $this->assertSame('Campanha Ads Premium', $service->name);
        $this->assertSame('campanha-ads', $service->slug);
        $this->assertSame(250000, $service->base_price);
        $this->assertFalse($service->is_active);
        $this->assertSame('operational', $service->metadata['delivery_mode']);

        $this->actingAs($user)
            ->delete(route('services.destroy', $service))
            ->assertRedirect(route('services.index'));

        $this->assertSoftDeleted($service);

        $this->actingAs($user)
            ->patch(route('services.restore', $service->uuid))
            ->assertRedirect(route('services.show', $service->uuid));

        $this->assertFalse($service->refresh()->trashed());
    }

    public function test_unauthorized_user_cannot_mutate_services(): void
    {
        $user = User::factory()->create();
        $service = Service::factory()->create([
            'name' => 'Ads',
            'slug' => 'ads',
        ]);

        $this->actingAs($user)->get(route('services.index'))->assertForbidden();
        $this->actingAs($user)->get(route('services.create'))->assertForbidden();
        $this->actingAs($user)->post(route('services.store'), [
            'name' => 'Curso',
            'base_price' => 0,
            'currency' => 'BRL',
        ])->assertForbidden();
        $this->actingAs($user)->put(route('services.update', $service), [
            'name' => 'Ads atualizado',
            'base_price' => 0,
            'currency' => 'BRL',
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('services.destroy', $service))->assertForbidden();

        $service->refresh();

        $this->assertSame('Ads', $service->name);
        $this->assertNull($service->deleted_at);
        $this->assertDatabaseMissing('services', ['slug' => 'curso']);
    }

    public function test_service_validation_rejects_invalid_payloads(): void
    {
        $user = $this->userWithPermissions(['services.view', 'services.create']);

        $this->actingAs($user)
            ->post(route('services.store'), [
                'name' => 'Ad',
                'base_price' => -1,
                'currency' => 'USD',
                'metadata' => [
                    'delivery_mode' => 'instant',
                ],
            ])
            ->assertSessionHasErrors([
                'name',
                'base_price',
                'currency',
                'metadata.delivery_mode',
            ]);
    }

    public function test_service_slug_is_generated_uniquely_from_name(): void
    {
        $user = $this->userWithPermissions(['services.view', 'services.create']);

        Service::factory()->create(['name' => 'Campanha Ads', 'slug' => 'campanha-ads']);

        $this->actingAs($user)
            ->post(route('services.store'), [
                'name' => 'Campanha Ads',
                'base_price' => 0,
                'currency' => 'BRL',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('services', ['slug' => 'campanha-ads-1']);
    }

    public function test_authorized_user_can_quick_update_service_name_only(): void
    {
        $user = $this->userWithPermissions(['services.view', 'services.update']);
        $service = Service::factory()->create([
            'name' => 'Ads',
            'slug' => 'ads',
            'base_price' => 1000,
        ]);

        $this->actingAs($user)
            ->patch(route('services.quick-update', $service), [
                'field' => 'name',
                'value' => 'Ads Premium',
            ])
            ->assertRedirect();

        $service->refresh();

        $this->assertSame('Ads Premium', $service->name);
        $this->assertSame('ads', $service->slug);
        $this->assertSame(1000, $service->base_price);
    }

    public function test_service_quick_update_rejects_invalid_columns_and_invalid_name(): void
    {
        $user = $this->userWithPermissions(['services.view', 'services.update']);
        $service = Service::factory()->create([
            'name' => 'Ads',
            'slug' => 'ads',
            'base_price' => 1000,
        ]);

        $this->actingAs($user)
            ->patch(route('services.quick-update', $service), [
                'field' => 'base_price',
                'value' => '0',
            ])
            ->assertSessionHasErrors(['field']);

        $this->actingAs($user)
            ->patch(route('services.quick-update', $service), [
                'field' => 'name',
                'value' => 'Ad',
            ])
            ->assertSessionHasErrors(['value']);

        $this->actingAs($user)
            ->patch(route('services.quick-update', $service), [
                'field' => 'name',
                'value' => 'Ads validado',
                'base_price' => 0,
            ])
            ->assertSessionHasErrors(['field']);

        $service->refresh();

        $this->assertSame('Ads', $service->name);
        $this->assertSame(1000, $service->base_price);
    }

    public function test_unauthorized_user_cannot_quick_update_service_name(): void
    {
        $user = $this->userWithPermissions(['services.view']);
        $service = Service::factory()->create([
            'name' => 'Ads',
            'slug' => 'ads',
        ]);

        $this->actingAs($user)
            ->patch(route('services.quick-update', $service), [
                'field' => 'name',
                'value' => 'Ads Premium',
            ])
            ->assertForbidden();

        $this->assertSame('Ads', $service->refresh()->name);
    }

    public function test_api_returns_service_records_and_can_filter_active_services(): void
    {
        $user = $this->userWithPermissions(['services.view']);

        Service::factory()->create([
            'name' => 'Ads',
            'slug' => 'ads',
            'is_active' => true,
        ]);

        Service::factory()->create([
            'name' => 'Ebook legado',
            'slug' => 'ebook-legado',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ebook legado')
            ->assertJsonMissingPath('data.0.type');

        $this->actingAs($user)
            ->getJson('/api/v1/services?'.http_build_query([
                'filter' => [
                    'is_active' => [
                        'operator' => 'equals',
                        'value' => true,
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ads');
    }

    public function test_service_api_route_uses_the_stateful_api_group(): void
    {
        $route = Route::getRoutes()->getByName('api.services.index');

        $this->assertNotNull($route);
        $this->assertSame('api/v1/services', $route->uri());
        $this->assertContains('api', $route->gatherMiddleware());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    public function test_services_navigation_lives_under_gestao_cadastros(): void
    {
        $navigation = config('navigation.modules');

        $gestaoModule = collect($navigation)->firstWhere('slug', 'gestao');

        $this->assertContains('/services', $gestaoModule['route_prefixes']);

        $cadastrosSection = collect($gestaoModule['items'])->firstWhere('slug', 'gestao_cadastros_section');

        $this->assertNotNull($cadastrosSection);
        $this->assertSame(
            ['route' => 'services.index'],
            collect($cadastrosSection['items'])->firstWhere('slug', 'gestao_services')['href'],
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
