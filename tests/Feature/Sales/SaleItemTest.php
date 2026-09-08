<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Playlists\Models\Playlist;
use App\Modules\Playlists\Models\PlaylistTop;
use App\Modules\Playlists\Models\Top;
use App\Modules\Sales\Actions\Items\SyncSaleTotals;
use App\Modules\Sales\Actions\Items\UpsertSaleItem;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Services\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaleItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_item_preserves_negotiated_value_and_syncs_sale_totals(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create(['subtotal_amount' => 0, 'total_amount' => 0]);
        $service = Service::factory()->create([
            'name' => 'Divulgacao Premium',
            'base_price' => 150000,
        ]);

        $item = app(UpsertSaleItem::class)->handle($sale, [
            'type' => SaleItemType::Service->value,
            'service_id' => $service->id,
            'quantity' => 2,
            'discount_amount' => 10000,
        ], $user);

        $this->assertSame(SaleItemType::Service, $item->type);
        $this->assertSame(SaleItemStatus::Draft, $item->status);
        $this->assertSame('Divulgacao Premium', $item->description);
        $this->assertSame(150000, $item->unit_amount);
        $this->assertSame(290000, $item->total_amount);
        $this->assertSame(290000, $sale->refresh()->subtotal_amount);
        $this->assertSame(290000, $sale->total_amount);

        $service->update(['base_price' => 999999]);

        $this->assertSame(150000, $item->refresh()->unit_amount);
    }

    public function test_service_item_uses_service_name_as_description_and_ignores_period_input(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create();
        $service = Service::factory()->create([
            'name' => 'Assessoria Digital',
            'base_price' => 90000,
        ]);

        $item = app(UpsertSaleItem::class)->handle($sale, [
            'type' => SaleItemType::Service->value,
            'service_id' => $service->id,
            'description' => 'Descricao manual nao permitida',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
        ], $user);

        $this->assertSame('Assessoria Digital', $item->description);
        $this->assertNull($item->starts_on);
        $this->assertNull($item->ends_on);
    }

    public function test_playlist_slot_item_requires_playlist_top_track_name_and_track_reference(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create();

        $this->expectException(ValidationException::class);

        app(UpsertSaleItem::class)->handle($sale, [
            'type' => SaleItemType::PlaylistSlot->value,
            'quantity' => 1,
            'unit_amount' => 100000,
        ], $user);
    }

    public function test_playlist_slot_item_normalizes_track_reference_and_keeps_playlist_top_relational(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create();
        $playlist = Playlist::factory()->create(['name' => 'Pop Hits']);
        $top = Top::factory()->create(['name' => 'TOP 10']);
        $playlistTop = PlaylistTop::factory()->for($playlist)->for($top)->create(['price' => 220000]);

        $item = app(UpsertSaleItem::class)->handle($sale, [
            'type' => SaleItemType::PlaylistSlot->value,
            'playlist_top_id' => $playlistTop->id,
            'quantity' => 1,
            'discount_amount' => 20000,
            'description' => 'Descricao manual nao permitida',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'track_name' => 'Musica Teste',
            'track_spotify_url' => 'spotify:track:4kwbbhuemSkRWvtN5dkWmb',
        ], $user);

        $this->assertSame(SaleItemType::PlaylistSlot, $item->type);
        $this->assertSame('Pop Hits - TOP 10', $item->description);
        $this->assertSame($playlistTop->id, $item->playlist_top_id);
        $this->assertNull($item->service_id);
        $this->assertSame('4kwbbhuemSkRWvtN5dkWmb', $item->track_spotify_id);
        $this->assertSame('https://open.spotify.com/track/4kwbbhuemSkRWvtN5dkWmb', $item->track_spotify_url);
        $this->assertSame(220000, $item->unit_amount);
        $this->assertSame(200000, $item->total_amount);
        $this->assertSame('2026-07-01', $item->starts_on?->toDateString());
        $this->assertSame('2026-07-31', $item->ends_on?->toDateString());
    }

    public function test_item_discount_cannot_exceed_gross_amount(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create();
        $service = Service::factory()->create(['base_price' => 1000]);

        $this->expectException(ValidationException::class);

        app(UpsertSaleItem::class)->handle($sale, [
            'type' => SaleItemType::Service->value,
            'service_id' => $service->id,
            'quantity' => 1,
            'discount_amount' => 1001,
        ], $user);
    }

    public function test_sale_totals_sum_items_and_apply_sale_discount(): void
    {
        $sale = Sale::factory()->create(['discount_amount' => 500]);
        SaleItem::factory()->for($sale)->create(['total_amount' => 1500]);
        SaleItem::factory()->for($sale)->create(['total_amount' => 900]);

        app(SyncSaleTotals::class)->handle($sale);

        $sale->refresh();

        $this->assertSame(2400, $sale->subtotal_amount);
        $this->assertSame(1900, $sale->total_amount);
    }
}
