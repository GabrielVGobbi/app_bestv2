<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Modules\Sales\Enums\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlaylistLessSoldReportSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_playlist_less_sold_report_seeder_creates_complete_scenario(): void
    {
        $this->artisan('db:seed', [
            '--class' => 'Database\\Seeders\\PlaylistLessSoldReportSeeder',
        ])->assertExitCode(0);

        $this->assertSame(5, DB::table('playlists')->where('name', 'like', 'RPT Seeder - %')->count());
        $this->assertSame(6, DB::table('sales')->where('order_number', 'like', 'RPT-PL-LESS-%')->count());
        $this->assertSame(6, DB::table('sale_items')->where('description', 'like', 'RPT Seeder - %')->count());

        $rows = $this->playlistRevenueRows();

        $this->assertSame([
            'RPT Seeder - Sem vendas no periodo',
            'RPT Seeder - Venda antiga',
            'RPT Seeder - Venda cancelada',
            'RPT Seeder - Baixa venda',
            'RPT Seeder - Alta venda',
        ], $rows->pluck('playlist_name')->all());

        $this->assertSame(0, (int) $rows[0]->total_revenue);
        $this->assertSame(5000, (int) $rows[3]->total_revenue);
        $this->assertSame(42000, (int) $rows[4]->total_revenue);
    }

    private function playlistRevenueRows()
    {
        $salesByPlaylist = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('companies as c', 'c.id', '=', 's.company_id')
            ->join('playlist_tops as pt', 'pt.id', '=', 'si.playlist_top_id')
            ->where('si.type', 'playlist_slot')
            ->whereBetween('si.starts_on', [now()->subMonths(3)->toDateString(), now()->toDateString()])
            ->where('c.nickname', 'Best Play')
            ->whereIn('s.status', [
                SaleStatus::CampaignStarted->value,
                SaleStatus::CampaignFinished->value,
            ])
            ->whereNull('s.deleted_at')
            ->whereNull('si.deleted_at')
            ->selectRaw('pt.playlist_id, SUM(si.total_amount) as total_revenue, SUM(si.quantity) as total_items')
            ->groupBy('pt.playlist_id');

        return DB::table('playlists as p')
            ->leftJoinSub($salesByPlaylist, 'sales_by_playlist', 'sales_by_playlist.playlist_id', '=', 'p.id')
            ->where('p.name', 'like', 'RPT Seeder - %')
            ->whereNull('p.deleted_at')
            ->selectRaw('p.name as playlist_name, COALESCE(sales_by_playlist.total_revenue, 0) as total_revenue, COALESCE(sales_by_playlist.total_items, 0) as total_items')
            ->orderBy('total_revenue')
            ->orderBy('total_items')
            ->orderBy('p.name')
            ->get();
    }
}
