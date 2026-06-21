<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Playlists\Actions\CheckPlaylistSlotAvailability;
use App\Modules\Playlists\Actions\CreatePlaylistSlotFromSaleItem;
use App\Modules\Playlists\Actions\RefreshPlaylistTopOccupiedCount;
use App\Modules\Playlists\Actions\SchedulePlaylistSlot;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Http\Resources\PlaylistSlotResource;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlaylistSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_counts_reserved_and_active_overlapping_slots_only(): void
    {
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 2, 'occupied_slots_count' => 0]);

        PlaylistSlot::factory()->for($playlistTop)->create([
            'status' => PlaylistSlotStatus::Active,
            'quantity' => 1,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);

        PlaylistSlot::factory()->for($playlistTop)->create([
            'status' => PlaylistSlotStatus::Canceled,
            'quantity' => 1,
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-20',
        ]);

        $result = app(CheckPlaylistSlotAvailability::class)->handle(
            playlistTop: $playlistTop,
            startsOn: '2026-07-15',
            endsOn: '2026-08-01',
        );

        $this->assertSame(2, $result['capacity']);
        $this->assertSame(1, $result['occupied']);
        $this->assertSame(1, $result['available']);
        $this->assertTrue($result['can_fit_one']);
    }

    public function test_slot_created_from_item_without_complete_period_is_pending_schedule(): void
    {
        $user = User::factory()->create();
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1]);
        $sale = Sale::factory()->create();
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'quantity' => 1,
            'track_name' => 'Musica Teste',
            'track_spotify_id' => '4kwbbhuemSkRWvtN5dkWmb',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'starts_on' => null,
            'ends_on' => null,
        ]);

        $slot = app(CreatePlaylistSlotFromSaleItem::class)->handle($item, $user);

        $this->assertSame(PlaylistSlotStatus::PendingSchedule, $slot->status);
        $this->assertSame($playlistTop->id, $slot->playlist_top_id);
        $this->assertSame($item->id, $slot->sale_item_id);
        $this->assertSame('Musica Teste', $slot->track_name);
        $this->assertSame(0, $playlistTop->refresh()->occupied_slots_count);
        $this->assertSame($slot->getMorphClass(), $item->refresh()->sellable_type);
        $this->assertSame($slot->id, $item->sellable_id);
    }

    public function test_slot_created_from_item_with_period_blocks_capacity_when_full(): void
    {
        $user = User::factory()->create();
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1]);
        PlaylistSlot::factory()->for($playlistTop)->create([
            'status' => PlaylistSlotStatus::Active,
            'quantity' => 1,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);
        $item = SaleItem::factory()->for(Sale::factory())->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'quantity' => 1,
            'track_name' => 'Musica Bloqueada',
            'track_spotify_url' => 'https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb',
            'starts_on' => '2026-07-15',
            'ends_on' => '2026-07-20',
        ]);

        $this->expectException(ValidationException::class);

        app(CreatePlaylistSlotFromSaleItem::class)->handle($item, $user);
    }

    public function test_pending_slot_can_be_scheduled_when_capacity_is_available(): void
    {
        $user = User::factory()->create();
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1, 'occupied_slots_count' => 0]);
        $sale = Sale::factory()->create();
        $sale->applySystemStatus(SaleStatus::CampaignStarted);
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
            'status' => SaleItemStatus::Reserved,
        ]);
        $slot = PlaylistSlot::factory()->for($playlistTop)->for($item)->create([
            'status' => PlaylistSlotStatus::PendingSchedule,
            'starts_on' => null,
            'ends_on' => null,
        ]);

        app(SchedulePlaylistSlot::class)->handle($slot, '2026-08-01', '2026-08-31', $user);

        $slot->refresh();

        $this->assertSame(PlaylistSlotStatus::Active, $slot->status);
        $this->assertSame('2026-08-01', $slot->starts_on->toDateString());
        $this->assertSame(SaleItemStatus::Active, $item->refresh()->status);
        $this->assertSame(1, $playlistTop->refresh()->occupied_slots_count);
    }

    public function test_pending_slot_schedule_blocks_capacity_when_full(): void
    {
        $user = User::factory()->create();
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 1]);
        PlaylistSlot::factory()->for($playlistTop)->create([
            'status' => PlaylistSlotStatus::Active,
            'quantity' => 1,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ]);
        $sale = Sale::factory()->create();
        $sale->applySystemStatus(SaleStatus::CampaignStarted);
        $item = SaleItem::factory()->for($sale)->for($playlistTop)->create([
            'type' => SaleItemType::PlaylistSlot,
        ]);
        $slot = PlaylistSlot::factory()->for($playlistTop)->for($item)->create([
            'status' => PlaylistSlotStatus::PendingSchedule,
        ]);

        $this->expectException(ValidationException::class);

        app(SchedulePlaylistSlot::class)->handle($slot, '2026-08-10', '2026-08-20', $user);
    }

    public function test_refresh_occupied_count_uses_active_and_reserved_complete_period_slots(): void
    {
        $playlistTop = PlaylistTop::factory()->create(['slots_quantity' => 10, 'occupied_slots_count' => 0]);
        PlaylistSlot::factory()->for($playlistTop)->create([
            'status' => PlaylistSlotStatus::Active,
            'quantity' => 2,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);
        PlaylistSlot::factory()->for($playlistTop)->create([
            'status' => PlaylistSlotStatus::PendingSchedule,
            'quantity' => 3,
            'starts_on' => null,
            'ends_on' => null,
        ]);
        PlaylistSlot::factory()->for($playlistTop)->create([
            'status' => PlaylistSlotStatus::Canceled,
            'quantity' => 4,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ]);

        app(RefreshPlaylistTopOccupiedCount::class)->handle($playlistTop);

        $this->assertSame(2, $playlistTop->refresh()->occupied_slots_count);
    }

    public function test_playlist_slot_resource_exposes_status_presenter(): void
    {
        $slot = PlaylistSlot::factory()->create([
            'status' => PlaylistSlotStatus::PendingSchedule,
        ]);

        $payload = (new PlaylistSlotResource($slot))->resolve();

        $this->assertSame('pending_schedule', $payload['status']);
        $this->assertSame('Pendente de agenda', $payload['status_presenter']['label']);
        $this->assertSame('amber', $payload['status_presenter']['color']);
        $this->assertSame('CalendarClock', $payload['status_presenter']['icon']);
    }
}
