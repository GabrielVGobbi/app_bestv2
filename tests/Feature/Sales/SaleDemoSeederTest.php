<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskType;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Sales\Database\Seeders\SaleDemoSeeder;
use App\Modules\Sales\Enums\SaleItemType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_demo_seeder_creates_lifecycle_scenarios_without_duplicates(): void
    {
        $this->artisan('db:seed', [
            '--class' => SaleDemoSeeder::class,
        ])->assertExitCode(0);

        $this->artisan('db:seed', [
            '--class' => SaleDemoSeeder::class,
        ])->assertExitCode(0);

        $sales = Sale::query()
            ->with(['saleItems.sellable', 'client'])
            ->where('order_number', 'like', 'VEN-DEMO-%')
            ->get()
            ->keyBy('order_number');

        $this->assertCount(16, $sales);
        $this->assertSame(3, $sales->where('status', SaleStatus::Draft)->count());
        $this->assertSame(2, $sales->where('status', SaleStatus::Quoted)->count());
        $this->assertSame(6, $sales->where('status', SaleStatus::CampaignStarted)->count());
        $this->assertSame(2, $sales->where('status', SaleStatus::CampaignFinished)->count());
        $this->assertSame(3, $sales->where('status', SaleStatus::Canceled)->count());

        $draftService = $sales->get('VEN-DEMO-01');
        $this->assertNotNull($draftService);
        $this->assertSame(SaleStatus::Draft, $draftService->status);
        $this->assertSame(SaleItemType::Service, $draftService->saleItems->sole()->type);
        $this->assertSame(150000, $draftService->total_amount);

        $draftPlaylist = $sales->get('VEN-DEMO-02');
        $this->assertNotNull($draftPlaylist);
        $this->assertSame(SaleItemType::PlaylistSlot, $draftPlaylist->saleItems->sole()->type);
        $this->assertNull($draftPlaylist->saleItems->sole()->sellable_id);

        $quotedPlaylist = $sales->get('VEN-DEMO-03');
        $this->assertNotNull($quotedPlaylist);
        $this->assertSame(SaleStatus::Quoted, $quotedPlaylist->status);

        $quotedMixed = $sales->get('VEN-DEMO-04');
        $this->assertNotNull($quotedMixed);
        $this->assertSame(2, $quotedMixed->saleItems->count());
        $this->assertTrue($quotedMixed->saleItems->contains(fn (SaleItem $item): bool => $item->type === SaleItemType::PlaylistSlot));
        $this->assertTrue($quotedMixed->saleItems->contains(fn (SaleItem $item): bool => $item->type === SaleItemType::Service));

        $reverted = $sales->get('VEN-DEMO-05');
        $this->assertNotNull($reverted);
        $this->assertSame(SaleStatus::Draft, $reverted->status);

        $startedPlaylist = $sales->get('VEN-DEMO-06');
        $this->assertNotNull($startedPlaylist);
        $this->assertSame(SaleStatus::CampaignStarted, $startedPlaylist->status);
        $this->assertNotNull($startedPlaylist->confirmed_at);
        $this->assertSame(PlaylistSlotStatus::Active, $startedPlaylist->saleItems->sole()->sellable?->status);

        $startedService = $sales->get('VEN-DEMO-07');
        $this->assertNotNull($startedService);
        $this->assertSame(SaleStatus::CampaignStarted, $startedService->status);
        $this->assertSame(SaleItemType::Service, $startedService->saleItems->sole()->type);
        $this->assertSame(700000, $startedService->total_amount);

        $startedMixed = $sales->get('VEN-DEMO-08');
        $this->assertNotNull($startedMixed);
        $this->assertSame(2, $startedMixed->saleItems->count());

        $twoPlaylists = $sales->get('VEN-DEMO-09');
        $this->assertNotNull($twoPlaylists);
        $this->assertSame(2, $twoPlaylists->saleItems->where('type', SaleItemType::PlaylistSlot)->count());
        $this->assertSame(2, $twoPlaylists->saleItems->whereNotNull('sellable_id')->count());

        $withoutPeriod = $sales->get('VEN-DEMO-10');
        $this->assertNotNull($withoutPeriod);
        $this->assertSame(PlaylistSlotStatus::PendingSchedule, $withoutPeriod->saleItems->sole()->sellable?->status);

        $finishedPlaylist = $sales->get('VEN-DEMO-11');
        $this->assertNotNull($finishedPlaylist);
        $this->assertSame(SaleStatus::CampaignFinished, $finishedPlaylist->status);
        $this->assertNotNull($finishedPlaylist->finished_at);
        $this->assertSame(PlaylistSlotStatus::Finished, $finishedPlaylist->saleItems->sole()->sellable?->status);

        $finishedMixed = $sales->get('VEN-DEMO-12');
        $this->assertNotNull($finishedMixed);
        $this->assertSame(SaleStatus::CampaignFinished, $finishedMixed->status);

        $canceledDraft = $sales->get('VEN-DEMO-13');
        $this->assertNotNull($canceledDraft);
        $this->assertSame(SaleStatus::Canceled, $canceledDraft->status);

        $canceledQuoted = $sales->get('VEN-DEMO-14');
        $this->assertNotNull($canceledQuoted);
        $this->assertSame(SaleStatus::Canceled, $canceledQuoted->status);

        $canceledAfterStart = $sales->get('VEN-DEMO-15');
        $this->assertNotNull($canceledAfterStart);
        $this->assertSame(SaleStatus::Canceled, $canceledAfterStart->status);
        $this->assertSame(PlaylistSlotStatus::Canceled, $canceledAfterStart->saleItems->sole()->sellable?->status);

        $discounted = $sales->get('VEN-DEMO-16');
        $this->assertNotNull($discounted);
        $this->assertSame(SaleStatus::CampaignStarted, $discounted->status);
        $this->assertSame(15000, $discounted->discount_amount);
        $this->assertSame($discounted->subtotal_amount - 15000, $discounted->total_amount);

        $this->assertSame(9, PlaylistSlot::query()->count());
        $this->assertSame(5, PlaylistSlot::query()->where('status', PlaylistSlotStatus::Active)->count());
        $this->assertSame(1, PlaylistSlot::query()->where('status', PlaylistSlotStatus::PendingSchedule)->count());
        $this->assertSame(2, PlaylistSlot::query()->where('status', PlaylistSlotStatus::Finished)->count());
        $this->assertSame(1, PlaylistSlot::query()->where('status', PlaylistSlotStatus::Canceled)->count());

        $tasks = PlaylistDeliveryTask::query()
            ->where('sale_order_number', 'like', 'VEN-DEMO-%')
            ->get();

        $this->assertCount(11, $tasks);
        $this->assertSame(7, $tasks->where('type', PlaylistDeliveryTaskType::AddTrack)->where('status', PlaylistDeliveryTaskStatus::Pending)->count());
        $this->assertSame(2, $tasks->where('type', PlaylistDeliveryTaskType::AddTrack)->where('status', PlaylistDeliveryTaskStatus::Canceled)->count());
        $this->assertSame(2, $tasks->where('type', PlaylistDeliveryTaskType::RemoveTrack)->count());

        $receivables = FinancialEntry::query()
            ->where('originable_type', (new Sale)->getMorphClass())
            ->whereIn('originable_id', $sales->pluck('id'))
            ->get();

        $this->assertCount(9, $receivables);
        $this->assertTrue($receivables->every(fn (FinancialEntry $entry): bool => $entry->direction === FinancialDirection::Receivable));
        $this->assertSame(8, $receivables->where('status', FinancialEntryStatus::Open)->count());
        $this->assertSame(1, $receivables->where('status', FinancialEntryStatus::Canceled)->count());
        $this->assertSame(
            $canceledAfterStart->id,
            $receivables->firstWhere('status', FinancialEntryStatus::Canceled)?->originable_id,
        );
    }
}
