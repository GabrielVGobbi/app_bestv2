<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Services\Models\Service;
use App\Modules\Timeline\Support\TimelineSubjectResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SaleDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_sale_requires_a_client(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'company_id' => $company->id,
                'title' => 'Campanha nova',
            ])
            ->assertSessionHasErrors('client_id');
    }

    public function test_creating_sale_requires_a_company(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'client_id' => $client->id,
                'title' => 'Campanha nova',
            ])
            ->assertSessionHasErrors('company_id');
    }

    public function test_creating_sale_stores_actor_defaults_and_order_number(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $company = Company::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'title' => 'Campanha de lançamento',
                'notes' => 'Observação interna',
                'subtotal_amount' => 999999,
                'total_amount' => 999999,
                'currency' => 'USD',
            ])
            ->assertRedirect();

        $sale = Sale::query()->firstOrFail();

        $this->assertSame($company->id, $sale->company_id);
        $this->assertSame($client->id, $sale->client_id);
        $this->assertSame($user->id, $sale->seller_id);
        $this->assertSame($user->id, $sale->created_by_id);
        $this->assertSame(SaleStatus::Draft, $sale->status);
        $this->assertSame(0, $sale->subtotal_amount);
        $this->assertSame(0, $sale->discount_amount);
        $this->assertSame(0, $sale->total_amount);
        $this->assertSame('BRL', $sale->currency);
        $this->assertMatchesRegularExpression('/^VEN-\d{6}$/', (string) $sale->order_number);
    }

    public function test_creating_sale_can_attach_service_items_and_sync_totals(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $ads = Service::factory()->create([
            'name' => 'Ads',
            'base_price' => 120000,
            'is_active' => true,
        ]);
        $consulting = Service::factory()->create([
            'name' => 'Consultoria',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'title' => 'Pacote inicial',
                'service_items' => [
                    [
                        'service_id' => $ads->id,
                        'quantity' => 2,
                        'unit_amount' => 110000,
                    ],
                    [
                        'service_id' => $consulting->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::query()
            ->with('saleItems')
            ->where('title', 'Pacote inicial')
            ->firstOrFail();

        $this->assertCount(2, $sale->saleItems);
        $this->assertSame(270000, $sale->subtotal_amount);
        $this->assertSame(270000, $sale->total_amount);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'service_id' => $ads->id,
            'description' => 'Ads',
            'quantity' => 2,
            'unit_amount' => 110000,
            'discount_amount' => 0,
            'total_amount' => 220000,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'service_id' => $consulting->id,
            'description' => 'Consultoria',
            'quantity' => 1,
            'unit_amount' => 50000,
            'discount_amount' => 0,
            'total_amount' => 50000,
        ]);
    }

    public function test_creating_sale_can_attach_playlist_slot_items_and_sync_totals(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $playlist = Playlist::factory()->create([
            'name' => 'Pop Hits',
            'spotify_image_url' => 'https://i.scdn.co/image/pop-hits-cover',
        ]);
        $top = Top::factory()->create(['name' => 'TOP 10']);
        $playlistTop = PlaylistTop::factory()
            ->for($playlist)
            ->for($top)
            ->create(['price' => 220000]);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'title' => 'Campanha playlist',
                'items' => [
                    [
                        'type' => SaleItemType::PlaylistSlot->value,
                        'playlist_top_id' => $playlistTop->id,
                        'quantity' => 1,
                        'unit_amount' => 210000,
                        'track_name' => 'Musica Teste',
                        'track_spotify_url' => 'spotify:track:4kwbbhuemSkRWvtN5dkWmb',
                    ],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::query()
            ->with('saleItems')
            ->where('title', 'Campanha playlist')
            ->firstOrFail();

        $this->assertCount(1, $sale->saleItems);
        $this->assertSame(210000, $sale->subtotal_amount);
        $this->assertSame(210000, $sale->total_amount);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'type' => SaleItemType::PlaylistSlot->value,
            'playlist_top_id' => $playlistTop->id,
            'description' => 'Pop Hits - TOP 10',
            'quantity' => 1,
            'unit_amount' => 210000,
            'discount_amount' => 0,
            'total_amount' => 210000,
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'track_name' => 'Musica Teste',
        ]);

    }

    public function test_creating_sale_rejects_playlist_url_as_playlist_slot_track_reference(): void
    {
        $user = $this->userWithPermissions(['sales.create']);
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $playlist = Playlist::factory()->create();
        $top = Top::factory()->create();
        $playlistTop = PlaylistTop::factory()
            ->for($playlist)
            ->for($top)
            ->create(['price' => 220000]);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'items' => [
                    [
                        'type' => SaleItemType::PlaylistSlot->value,
                        'playlist_top_id' => $playlistTop->id,
                        'quantity' => 1,
                        'unit_amount' => 210000,
                        'track_name' => 'Musica Teste',
                        'track_spotify_url' => 'https://open.spotify.com/playlist/5bNqE1YOKGpbylAsxAiNtC',
                    ],
                ],
            ])
            ->assertSessionHasErrors([
                'items.0.track_spotify_url' => 'Informe um link, URI ou ID válido de música do Spotify.',
            ]);
    }

    public function test_update_recalculates_totals_and_only_accepts_editable_fields(): void
    {
        $user = $this->userWithPermissions(['sales.update']);
        $company = Company::factory()->create();
        $newCompany = Company::factory()->create();
        $client = Client::factory()->create();
        $newClient = Client::factory()->create();
        $sale = Sale::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'seller_id' => $user->id,
            'subtotal_amount' => 1200,
            'discount_amount' => 0,
            'total_amount' => 1200,
        ]);

        $this->actingAs($user)
            ->patch(route('sales.update', $sale), [
                'company_id' => $newCompany->id,
                'client_id' => $newClient->id,
                'title' => 'Título atualizado',
                'notes' => 'Notas atualizadas',
                'discount_amount' => 250,
                'subtotal_amount' => 999999,
                'total_amount' => 999999,
                'status' => SaleStatus::Canceled->value,
            ])
            ->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertSame($newCompany->id, $sale->company_id);
        $this->assertSame($newClient->id, $sale->client_id);
        $this->assertSame('Título atualizado', $sale->title);
        $this->assertSame('Notas atualizadas', $sale->notes);
        $this->assertSame(SaleStatus::Draft, $sale->status);
        $this->assertSame(1200, $sale->subtotal_amount);
        $this->assertSame(250, $sale->discount_amount);
        $this->assertSame(950, $sale->total_amount);
    }

    public function test_update_can_sync_sale_items_from_edit_form(): void
    {
        $user = $this->userWithPermissions([
            'sales.update',
            'sales.items.create',
            'sales.items.update',
            'sales.items.delete',
        ]);
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $sale = Sale::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'seller_id' => $user->id,
            'subtotal_amount' => 30000,
            'discount_amount' => 0,
            'total_amount' => 30000,
        ]);
        $existingService = Service::factory()->create([
            'name' => 'Ads antigo',
            'base_price' => 10000,
            'is_active' => true,
        ]);
        $newService = Service::factory()->create([
            'name' => 'Consultoria nova',
            'base_price' => 25000,
            'is_active' => true,
        ]);
        $updatedItem = SaleItem::factory()
            ->for($sale)
            ->for($existingService, 'service')
            ->create([
                'description' => 'Ads antigo',
                'quantity' => 1,
                'unit_amount' => 10000,
                'discount_amount' => 0,
                'total_amount' => 10000,
            ]);
        $removedItem = SaleItem::factory()
            ->for($sale)
            ->for(Service::factory()->create(['is_active' => true]), 'service')
            ->create([
                'quantity' => 2,
                'unit_amount' => 10000,
                'discount_amount' => 0,
                'total_amount' => 20000,
            ]);

        $this->actingAs($user)
            ->patch(route('sales.update', $sale), [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'title' => 'Composicao ajustada',
                'discount_amount' => 5000,
                'items' => [
                    [
                        'uuid' => $updatedItem->uuid,
                        'type' => SaleItemType::Service->value,
                        'service_id' => $existingService->id,
                        'quantity' => 3,
                        'unit_amount' => 12000,
                    ],
                    [
                        'type' => SaleItemType::Service->value,
                        'service_id' => $newService->id,
                        'quantity' => 1,
                        'unit_amount' => 25000,
                    ],
                ],
            ])
            ->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        $this->assertSame('Composicao ajustada', $sale->title);
        $this->assertSame(61000, $sale->subtotal_amount);
        $this->assertSame(5000, $sale->discount_amount);
        $this->assertSame(56000, $sale->total_amount);
        $this->assertDatabaseHas('sale_items', [
            'uuid' => $updatedItem->uuid,
            'quantity' => 3,
            'unit_amount' => 12000,
            'total_amount' => 36000,
        ]);
        $this->assertDatabaseMissing('sale_items', [
            'uuid' => $removedItem->uuid,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'service_id' => $newService->id,
            'description' => 'Consultoria nova',
            'quantity' => 1,
            'unit_amount' => 25000,
            'total_amount' => 25000,
        ]);
    }

    public function test_timeline_records_service_and_playlist_top_changes_when_sale_items_are_edited(): void
    {
        $user = $this->userWithPermissions([
            'sales.view',
            'sales.update',
            'sales.items.update',
        ]);
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $sale = Sale::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'seller_id' => $user->id,
        ]);

        $oldService = Service::factory()->create([
            'name' => 'Ads antigo',
            'base_price' => 10000,
            'is_active' => true,
        ]);
        $newService = Service::factory()->create([
            'name' => 'Consultoria nova',
            'base_price' => 20000,
            'is_active' => true,
        ]);

        $oldPlaylist = Playlist::factory()->create(['name' => 'Pop Hits']);
        $newPlaylist = Playlist::factory()->create(['name' => 'Corrida']);
        $oldTop = Top::factory()->create(['name' => 'TOP 10']);
        $newTop = Top::factory()->create(['name' => 'TOP 20']);
        $oldPlaylistTop = PlaylistTop::factory()->for($oldPlaylist)->for($oldTop)->create(['price' => 50000]);
        $newPlaylistTop = PlaylistTop::factory()->for($newPlaylist)->for($newTop)->create(['price' => 80000]);

        $serviceItem = SaleItem::factory()
            ->for($sale)
            ->for($oldService, 'service')
            ->create([
                'type' => SaleItemType::Service,
                'description' => 'Ads antigo',
                'quantity' => 1,
                'unit_amount' => 10000,
                'total_amount' => 10000,
            ]);
        $playlistItem = SaleItem::factory()
            ->for($sale)
            ->for($oldPlaylistTop)
            ->create([
                'type' => SaleItemType::PlaylistSlot,
                'description' => 'Pop Hits - TOP 10',
                'quantity' => 1,
                'unit_amount' => 50000,
                'total_amount' => 50000,
                'track_name' => 'Musica Teste',
                'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
                'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            ]);

        Activity::query()->delete();

        $this->actingAs($user)
            ->patch(route('sales.update', $sale), [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'items' => [
                    [
                        'uuid' => $serviceItem->uuid,
                        'type' => SaleItemType::Service->value,
                        'service_id' => $newService->id,
                        'quantity' => 1,
                        'unit_amount' => 20000,
                    ],
                    [
                        'uuid' => $playlistItem->uuid,
                        'type' => SaleItemType::PlaylistSlot->value,
                        'playlist_top_id' => $newPlaylistTop->id,
                        'quantity' => 1,
                        'unit_amount' => 80000,
                        'track_name' => 'Musica Teste',
                        'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
                    ],
                ],
            ])
            ->assertRedirect(route('sales.show', $sale));

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/timeline/sale/{$sale->uuid}?types[]=activity&limit=10")
            ->assertOk();

        $changes = collect($response->json('data'))
            ->where('event', 'item_updated')
            ->flatMap(fn (array $activity): array => $activity['changes'] ?? [])
            ->keyBy('field');

        $this->assertSame('Ads antigo', $changes->get('item_service')['old'] ?? null);
        $this->assertSame('Consultoria nova', $changes->get('item_service')['new'] ?? null);
        $this->assertSame('Pop Hits - TOP 10', $changes->get('item_playlist_top')['old'] ?? null);
        $this->assertSame('Corrida - TOP 20', $changes->get('item_playlist_top')['new'] ?? null);
    }

    public function test_update_is_blocked_after_sale_leaves_editable_statuses(): void
    {
        $user = $this->userWithPermissions(['sales.update']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::CampaignStarted);

        $this->actingAs($user)
            ->patch(route('sales.update', $sale), [
                'company_id' => $sale->company_id,
                'client_id' => $sale->client_id,
                'title' => 'Não deve atualizar',
            ])
            ->assertSessionHasErrors('status');

        $this->assertNotSame('Não deve atualizar', $sale->refresh()->title);
    }

    public function test_discount_cannot_make_total_negative(): void
    {
        $user = $this->userWithPermissions(['sales.update']);
        $sale = Sale::factory()->create([
            'seller_id' => $user->id,
            'subtotal_amount' => 1200,
            'total_amount' => 1200,
        ]);

        $this->actingAs($user)
            ->patch(route('sales.update', $sale), [
                'company_id' => $sale->company_id,
                'client_id' => $sale->client_id,
                'discount_amount' => 1201,
            ])
            ->assertSessionHasErrors('discount_amount');
    }

    public function test_view_own_scope_only_returns_sales_for_the_current_seller(): void
    {
        $seller = $this->userWithPermissions(['sales.view-own']);
        $otherSeller = User::factory()->create();
        $ownSale = Sale::factory()->create(['seller_id' => $seller->id]);
        Sale::factory()->create(['seller_id' => $otherSeller->id]);

        $visibleIds = Sale::query()
            ->visibleTo($seller)
            ->pluck('id')
            ->all();

        $this->assertSame([$ownSale->id], $visibleIds);
    }

    public function test_sale_resource_hides_amounts_without_permission(): void
    {
        $user = $this->userWithPermissions(['sales.view']);
        $sale = Sale::factory()->create([
            'subtotal_amount' => 2000,
            'discount_amount' => 250,
            'total_amount' => 1750,
        ]);

        $this->actingAs($user);

        $payload = (new SaleResource($sale))->resolve();

        $this->assertArrayNotHasKey('subtotal_amount', $payload);
        $this->assertArrayNotHasKey('discount_amount', $payload);
        $this->assertArrayNotHasKey('total_amount', $payload);
    }

    public function test_sale_resource_exposes_amounts_with_permission_and_loaded_relations(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.amounts.view']);
        $sale = Sale::factory()
            ->for(Company::factory()->state(['name' => 'Best Play', 'nickname' => 'Best Play']), 'company')
            ->for(Client::factory()->state(['name' => 'Cliente Teste', 'phone' => '11999990000']))
            ->for(User::factory()->state(['name' => 'Vendedor Teste']), 'seller')
            ->create([
                'subtotal_amount' => 2000,
                'discount_amount' => 250,
                'total_amount' => 1750,
            ]);

        $this->actingAs($user);

        $payload = (new SaleResource($sale->load(['company', 'client', 'seller'])))->resolve();

        $this->assertSame(2000, $payload['subtotal_amount']);
        $this->assertSame(250, $payload['discount_amount']);
        $this->assertSame(1750, $payload['total_amount']);
        $this->assertSame('Best Play', $payload['company']['name']);
        $this->assertSame('Best Play', $payload['company']['nickname']);
        $this->assertSame('Cliente Teste', $payload['client']['name']);
        $this->assertSame('11999990000', $payload['client']['phone']);
        $this->assertSame('Vendedor Teste', $payload['seller']['name']);
    }

    public function test_routes_enforce_sale_authorization(): void
    {
        $user = $this->userWithPermissions(['sales.view-own']);
        $otherSeller = User::factory()->create();
        $sale = Sale::factory()->create(['seller_id' => $otherSeller->id]);

        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertForbidden();
    }

    public function test_sale_is_available_as_a_timeline_subject(): void
    {
        $sale = Sale::factory()->create();

        $resolved = app(TimelineSubjectResolver::class)->resolve('sale', $sale->uuid);

        $this->assertTrue($sale->is($resolved));
        $this->assertSame('sale', Sale::timelineType());
    }

    public function test_sales_pages_render_existing_inertia_components(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.create', 'sales.update', 'sales.amounts.view']);
        $company = Company::factory()->create(['name' => 'Best Play']);
        $sale = Sale::factory()->create(['seller_id' => $user->id, 'company_id' => $company->id]);
        $service = Service::factory()->create(['is_active' => true]);
        SaleItem::factory()->for($sale)->for($service, 'service')->create([
            'quantity' => 2,
            'unit_amount' => 15000,
            'discount_amount' => 0,
            'total_amount' => 30000,
        ]);
        Client::factory()->create();
        $playlist = Playlist::factory()->create([
            'name' => 'Pop Hits',
            'spotify_image_url' => 'https://i.scdn.co/image/pop-hits-cover',
        ]);
        $top = Top::factory()->create(['name' => 'TOP 10']);
        $playlistTop = PlaylistTop::factory()->for($playlist)->for($top)->create();

        $this->actingAs($user)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('app/sales/index')
                ->where('permissions.can_create', true)
                ->where('permissions.can_delete', false)
                ->where('filters.statuses.0.value', SaleStatus::Draft->value)
                ->has('filters.clients', 1)
                ->has('filters.sellers', 1)
                ->has('filters.companies', 1));

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('app/sales/create')
                ->where('formOptions.companies.0.id', $company->id)
                ->where('formOptions.companies.0.name', 'Best Play')
                ->where('formOptions.playlists.0.id', $playlist->id)
                ->where('formOptions.playlists.0.name', 'Pop Hits')
                ->where('formOptions.playlists.0.spotify_image_url', 'https://i.scdn.co/image/pop-hits-cover')
                ->where('formOptions.playlists.0.cover_image_url', 'https://i.scdn.co/image/pop-hits-cover')
                ->where('formOptions.playlist_tops.0.id', $playlistTop->id)
                ->where('formOptions.playlist_tops.0.playlist_id', $playlist->id)
                ->where('formOptions.playlist_tops.0.playlist_name', 'Pop Hits')
                ->where('formOptions.playlist_tops.0.spotify_image_url', 'https://i.scdn.co/image/pop-hits-cover')
                ->where('formOptions.playlist_tops.0.cover_image_url', 'https://i.scdn.co/image/pop-hits-cover')
                ->where('formOptions.playlist_tops.0.top_name', 'TOP 10'));

        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('app/sales/show')
                ->where('timelineSubject.type', Sale::timelineType())
                ->where('timelineSubject.id', $sale->uuid)
                ->has('saleData.data.sale_items', 1)
                ->where('saleData.data.sale_items.0.service.id', $service->id)
                ->where('saleData.data.sale_items.0.service.name', $service->name)
                ->where('saleData.data.sale_items.0.quantity', 2)
                ->where('saleData.data.sale_items.0.unit_amount', 15000)
                ->where('saleData.data.sale_items.0.total_amount', 30000)
                ->where('saleData.data.sale_items.0.status_presenter.label', 'Rascunho')
                ->missing('formOptions'));

        $this->actingAs($user)
            ->get(route('sales.edit', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('app/sales/edit')
                ->has('saleData.data.sale_items', 1)
                ->where('saleData.data.sale_items.0.service_id', $service->id));
    }

    public function test_sales_create_shell_exposes_csrf_token_for_ajax_requests(): void
    {
        $user = $this->userWithPermissions(['sales.create']);

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('name="csrf-token"', false);
    }

    public function test_api_returns_visible_sale_records_and_can_filter_them(): void
    {
        $user = $this->userWithPermissions(['sales.view', 'sales.amounts.view']);
        $company = Company::factory()->create(['name' => 'Best Play']);
        $client = Client::factory()->create(['name' => 'Cliente Gold']);
        $seller = User::factory()->create(['name' => 'Vendedor Principal']);
        $matchingSale = Sale::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'status' => SaleStatus::Quoted,
            'order_number' => 'VEN-123456',
            'title' => 'Campanha Gold',
            'total_amount' => 250000,
        ]);

        Sale::factory()->create([
            'seller_id' => User::factory()->create()->id,
            'status' => SaleStatus::Draft,
            'title' => 'Campanha fora do filtro',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sales?'.http_build_query([
                'search' => 'Gold',
                'sort' => 'total_amount',
                'direction' => 'desc',
                'filter' => [
                    'status' => SaleStatus::Quoted->value,
                    'company_id' => $company->id,
                    'client_id' => $client->id,
                    'seller_id' => $seller->id,
                    'created_at' => [
                        'operator' => 'between',
                        'value' => [
                            'from' => now()->subDay()->toDateTimeString(),
                            'to' => now()->addDay()->toDateTimeString(),
                        ],
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $matchingSale->uuid)
            ->assertJsonPath('data.0.company.name', 'Best Play')
            ->assertJsonPath('data.0.client.name', 'Cliente Gold')
            ->assertJsonPath('data.0.seller.name', 'Vendedor Principal')
            ->assertJsonPath('data.0.total_amount', 250000);
    }

    public function test_sales_are_available_for_export_columns(): void
    {
        $user = $this->userWithPermissions(['sales.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/exports/columns?model_class='.urlencode(Sale::class));

        $response->assertOk();

        $keys = collect($response->json('data'))->pluck('key')->all();

        $this->assertContains('order_number', $keys);
        $this->assertContains('status', $keys);
        $this->assertContains('total_amount', $keys);
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
