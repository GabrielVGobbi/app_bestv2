<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Actions\Status\CancelSale;
use App\Modules\Sales\Actions\Status\ConfirmSale;
use App\Modules\Sales\Actions\Status\FinishSaleCampaign;
use App\Modules\Sales\Actions\Status\QuoteSale;
use App\Modules\Sales\Actions\Status\RevertSaleToDraft;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleCampaignFinished;
use App\Modules\Sales\Events\SaleCanceled;
use App\Modules\Sales\Events\SaleConfirmed;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaleStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_is_created_as_draft_and_casts_status_to_enum(): void
    {
        $sale = Sale::factory()->create();

        $this->assertSame(SaleStatus::Draft, $sale->status);
        $this->assertSame('draft', $sale->getRawOriginal('status'));
    }

    public function test_update_status_allows_valid_transition_and_logs_status_change(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create();

        $this->actingAs($user);

        $sale->updateStatus(SaleStatus::Quoted, metadata: ['action' => QuoteSale::class]);

        $sale->refresh();

        $this->assertSame(SaleStatus::Quoted, $sale->status);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'sales',
            'event' => 'status_changed',
            'subject_type' => Sale::class,
            'subject_id' => $sale->id,
        ]);
    }

    public function test_invalid_transition_throws_validation_exception(): void
    {
        $sale = Sale::factory()->create();

        $this->expectException(ValidationException::class);

        $sale->updateStatus(SaleStatus::CampaignStarted);
    }

    public function test_direct_status_update_is_blocked_but_system_status_can_bypass(): void
    {
        $sale = Sale::factory()->create();

        $this->expectException(\LogicException::class);

        $sale->update(['status' => SaleStatus::Quoted]);
    }

    public function test_apply_system_status_is_an_explicit_bypass(): void
    {
        $sale = Sale::factory()->create();

        $sale->applySystemStatus(SaleStatus::Canceled, 'Migração operacional');

        $this->assertSame(SaleStatus::Canceled, $sale->refresh()->status);
    }

    public function test_status_actions_use_transition_flow(): void
    {
        $user = $this->userWithPermissions(['sales.quote', 'sales.confirm', 'sales.finish', 'sales.cancel']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        SaleItem::factory()->for($sale)->create();

        $this->actingAs($user);

        app(QuoteSale::class)->handle($sale);
        $this->assertSame(SaleStatus::Quoted, $sale->refresh()->status);

        app(ConfirmSale::class)->handle($sale, confirmed: true);
        $this->assertSame(SaleStatus::CampaignStarted, $sale->refresh()->status);
        $this->assertNotNull($sale->confirmed_at);
        $this->assertSame($user->id, $sale->confirmed_by_id);

        app(FinishSaleCampaign::class)->handle($sale, confirmed: true);
        $this->assertSame(SaleStatus::CampaignFinished, $sale->refresh()->status);
        $this->assertNotNull($sale->finished_at);
        $this->assertSame($user->id, $sale->finished_by_id);
    }

    public function test_confirm_sale_requires_at_least_one_item(): void
    {
        $user = $this->userWithPermissions(['sales.confirm']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::Quoted);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        app(ConfirmSale::class)->handle($sale, confirmed: true);
    }

    public function test_quote_sale_requires_at_least_one_item(): void
    {
        $sale = Sale::factory()->create();

        $this->expectException(ValidationException::class);

        app(QuoteSale::class)->handle($sale);
    }

    public function test_quote_sale_recalculates_totals_before_transition(): void
    {
        $sale = Sale::factory()->create(['discount_amount' => 100]);
        SaleItem::factory()->for($sale)->create([
            'quantity' => 2,
            'unit_amount' => 1000,
            'discount_amount' => 250,
            'total_amount' => 0,
        ]);

        app(QuoteSale::class)->handle($sale);

        $sale->refresh();

        $this->assertSame(SaleStatus::Quoted, $sale->status);
        $this->assertSame(1750, $sale->subtotal_amount);
        $this->assertSame(1650, $sale->total_amount);
    }

    public function test_revert_sale_to_draft_from_quoted(): void
    {
        $user = $this->userWithPermissions(['sales.update']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::Quoted);
        SaleItem::factory()->for($sale)->create();

        $this->actingAs($user);

        app(RevertSaleToDraft::class)->handle($sale);

        $this->assertSame(SaleStatus::Draft, $sale->refresh()->status);
    }

    public function test_revert_sale_to_draft_is_invalid_from_campaign_started(): void
    {
        $user = $this->userWithPermissions(['sales.update']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::CampaignStarted);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        app(RevertSaleToDraft::class)->handle($sale);
    }

    public function test_revert_to_draft_route_requires_update_permission(): void
    {
        $user = $this->userWithPermissions(['sales.update', 'sales.view']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::Quoted);

        $this->actingAs($user)
            ->patch(route('sales.revert-to-draft', $sale))
            ->assertRedirect();

        $this->assertSame(SaleStatus::Draft, $sale->refresh()->status);

        $limitedUser = $this->userWithPermissions(['sales.quote']);

        $this->actingAs($limitedUser)
            ->patch(route('sales.revert-to-draft', $sale))
            ->assertForbidden();
    }

    public function test_confirm_sale_with_item_period_creates_active_slot(): void
    {
        $user = $this->userWithPermissions(['sales.confirm']);
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1, 'occupied_slots_count' => 0]);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::Quoted);
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'quantity' => 1,
            'discount_amount' => 0,
            'track_name' => 'Musica Teste',
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
        ]);

        $this->actingAs($user);

        app(ConfirmSale::class)->handle($sale, confirmed: true);

        $slot = PlaylistSlot::query()->firstOrFail();

        $this->assertSame(PlaylistSlotStatus::Active, $slot->status);
        $this->assertSame('2026-09-01', $slot->starts_on?->toDateString());
        $this->assertSame('2026-09-30', $slot->ends_on?->toDateString());
        $this->assertSame(SaleItemStatus::Active, $item->refresh()->status);
        $this->assertSame(1, $playlistTop->refresh()->occupied_slots_count);
    }

    public function test_confirm_sale_with_item_period_blocks_when_capacity_is_exhausted(): void
    {
        $user = $this->userWithPermissions(['sales.confirm']);
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1, 'occupied_slots_count' => 0]);

        $occupyingSale = Sale::factory()->create(['seller_id' => $user->id]);
        $occupyingItem = SaleItem::factory()->for($occupyingSale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'status' => SaleItemStatus::Active,
        ]);
        PlaylistSlot::factory()->for($playlistTop)->for($occupyingItem)->create([
            'status' => PlaylistSlotStatus::Active,
            'quantity' => 1,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-09-30',
        ]);

        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::Quoted);
        SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'quantity' => 1,
            'discount_amount' => 0,
            'track_name' => 'Musica Teste',
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'starts_on' => '2026-09-10',
            'ends_on' => '2026-09-20',
        ]);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        app(ConfirmSale::class)->handle($sale, confirmed: true);
    }

    public function test_confirm_sale_materializes_playlist_slot_items(): void
    {
        $user = $this->userWithPermissions(['sales.confirm']);
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1, 'occupied_slots_count' => 0]);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::Quoted);
        SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'quantity' => 1,
            'discount_amount' => 0,
            'track_name' => 'Musica Teste',
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'starts_on' => null,
            'ends_on' => null,
        ]);

        $this->actingAs($user);

        app(ConfirmSale::class)->handle($sale, confirmed: true);

        $slot = PlaylistSlot::query()->firstOrFail();

        $this->assertSame(PlaylistSlotStatus::PendingSchedule, $slot->status);
        $this->assertSame($playlistTop->id, $slot->playlist_top_id);
        $this->assertSame(SaleStatus::CampaignStarted, $sale->refresh()->status);
    }

    public function test_confirm_sale_recalculates_totals_and_dispatches_event(): void
    {
        Event::fake([SaleConfirmed::class]);

        $user = $this->userWithPermissions(['sales.confirm']);
        $sale = Sale::factory()->create(['seller_id' => $user->id, 'discount_amount' => 500]);
        $sale->applySystemStatus(SaleStatus::Quoted);
        SaleItem::factory()->for($sale)->create([
            'quantity' => 3,
            'unit_amount' => 1000,
            'discount_amount' => 250,
            'total_amount' => 0,
        ]);

        $this->actingAs($user);

        app(ConfirmSale::class)->handle($sale, confirmed: true);

        $sale->refresh();

        $this->assertSame(2750, $sale->subtotal_amount);
        $this->assertSame(2250, $sale->total_amount);
        Event::assertDispatched(SaleConfirmed::class);
    }

    public function test_cancel_sale_cancels_related_playlist_slots_and_releases_capacity(): void
    {
        Event::fake([SaleCanceled::class]);

        $user = $this->userWithPermissions(['sales.cancel']);
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1]);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::CampaignStarted);
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
            'status' => SaleItemStatus::Active,
        ]);
        $slot = PlaylistSlot::factory()->for($playlistTop)->for($item)->create([
            'status' => PlaylistSlotStatus::Active,
            'quantity' => 1,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);
        $item->forceFill([
            'sellable_type' => $slot->getMorphClass(),
            'sellable_id' => $slot->id,
        ])->save();

        $this->actingAs($user);

        app(CancelSale::class)->handle($sale, reason: 'Cliente desistiu', confirmed: true);

        $this->assertSame(PlaylistSlotStatus::Canceled, $slot->refresh()->status);
        $this->assertSame(SaleItemStatus::Canceled, $item->refresh()->status);
        $this->assertSame(0, $playlistTop->refresh()->occupied_slots_count);
        Event::assertDispatched(SaleCanceled::class);
    }

    public function test_finish_sale_campaign_finishes_active_playlist_slots(): void
    {
        Event::fake([SaleCampaignFinished::class]);

        $user = $this->userWithPermissions(['sales.finish']);
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1]);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::CampaignStarted);
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'service_id' => null,
            'playlist_top_id' => $playlistTop->id,
        ]);
        $slot = PlaylistSlot::factory()->for($playlistTop)->for($item)->create([
            'status' => PlaylistSlotStatus::Active,
            'quantity' => 1,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);
        $item->forceFill([
            'sellable_type' => $slot->getMorphClass(),
            'sellable_id' => $slot->id,
        ])->save();

        $this->actingAs($user);

        app(FinishSaleCampaign::class)->handle($sale, confirmed: true);

        $this->assertSame(SaleStatus::CampaignFinished, $sale->refresh()->status);
        $this->assertSame(PlaylistSlotStatus::Finished, $slot->refresh()->status);
        $this->assertSame(0, $playlistTop->refresh()->occupied_slots_count);
        Event::assertDispatched(SaleCampaignFinished::class);
    }

    public function test_confirmed_sale_cancellation_requires_reason(): void
    {
        $user = $this->userWithPermissions(['sales.cancel']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::CampaignStarted);

        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        app(CancelSale::class)->handle($sale, confirmed: true);
    }

    public function test_cancel_sale_action_records_reason_and_actor(): void
    {
        $user = $this->userWithPermissions(['sales.cancel']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $sale->applySystemStatus(SaleStatus::CampaignStarted);

        $this->actingAs($user);

        app(CancelSale::class)->handle($sale, reason: 'Cliente desistiu', confirmed: true);

        $sale->refresh();

        $this->assertSame(SaleStatus::Canceled, $sale->status);
        $this->assertSame('Cliente desistiu', $sale->cancellation_reason);
        $this->assertSame($user->id, $sale->canceled_by_id);
        $this->assertNotNull($sale->canceled_at);
    }

    public function test_sale_resource_exposes_only_available_transitions(): void
    {
        $sale = Sale::factory()
            ->has(SaleItem::factory()->state(['status' => SaleItemStatus::Reserved]), 'saleItems')
            ->create();

        $payload = (new SaleResource($sale->load('saleItems')))->resolve();

        $this->assertSame('draft', $payload['status']);
        $this->assertSame('Rascunho', $payload['status_presenter']['label']);
        $this->assertSame('zinc', $payload['status_presenter']['color']);
        $this->assertSame('FilePenLine', $payload['status_presenter']['icon']);
        $this->assertSame('reserved', $payload['sale_items'][0]['status']);
        $this->assertSame('Reservado', $payload['sale_items'][0]['status_presenter']['label']);
        $this->assertSame('amber', $payload['sale_items'][0]['status_presenter']['color']);
        $this->assertSame(['quoted', 'canceled'], collect($payload['available_transitions'])->pluck('value')->all());
    }

    public function test_lifecycle_routes_are_protected_by_authorization_and_transition_rules(): void
    {
        $user = $this->userWithPermissions(['sales.quote']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        SaleItem::factory()->for($sale)->create();

        $this->actingAs($user)
            ->patch(route('sales.quote', $sale))
            ->assertRedirect();

        $this->assertSame(SaleStatus::Quoted, $sale->refresh()->status);

        $this->actingAs($user)
            ->patch(route('sales.confirm', $sale), ['status_confirmed' => true])
            ->assertForbidden();
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
