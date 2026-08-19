<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\User;
use App\Modules\Finance\Actions\Reports\BuildFinancialDashboardReport;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Carbon;

class BuildOperationalDashboard
{
    public function __construct(
        private readonly BuildFinancialDashboardReport $financialDashboard,
    ) {}

    /**
     * @return array{
     *     generated_at: string,
     *     sales?: array{active_campaigns: int, awaiting_quotes: int, month_amount?: int},
     *     playlists?: array{pending_deliveries: int, active_slots: int},
     *     finance?: array{overdue_receivables: int, overdue_payables: int, pending_reconciliations: int}
     * }
     */
    public function handle(User $user, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $dashboard = ['generated_at' => $now->toIso8601String()];

        if ($user->hasAnyPermission('sales.view', 'sales.view-own')) {
            $dashboard['sales'] = $this->salesSummary($user, $now);
        }

        if ($user->hasAnyPermission('playlist-delivery-tasks.view')
            && $user->hasAnyPermission('playlist-slots.view')) {
            $dashboard['playlists'] = [
                'pending_deliveries' => PlaylistDeliveryTask::query()
                    ->where('status', PlaylistDeliveryTaskStatus::Pending)
                    ->count(),
                'active_slots' => PlaylistSlot::query()
                    ->where('status', PlaylistSlotStatus::Active)
                    ->count(),
            ];
        }

        if ($user->hasAnyPermission('finance.reports.view')) {
            $financial = $this->financialDashboard->handle(today: $now->copy()->startOfDay());
            $cards = $financial['cards'];

            $dashboard['finance'] = [
                'overdue_receivables' => (int) $cards['overdue_receivables']['amount'],
                'overdue_payables' => (int) $cards['overdue_payables']['amount'],
                'pending_reconciliations' => (int) $cards['pending_reconciliation']['amount'],
            ];
        }

        return $dashboard;
    }

    /** @return array{active_campaigns: int, awaiting_quotes: int, month_amount?: int} */
    private function salesSummary(User $user, Carbon $now): array
    {
        $visibleSales = Sale::query()->visibleTo($user);

        $summary = [
            'active_campaigns' => (clone $visibleSales)
                ->where('status', SaleStatus::CampaignStarted)
                ->count(),
            'awaiting_quotes' => (clone $visibleSales)
                ->where('status', SaleStatus::Quoted)
                ->count(),
        ];

        if ($user->hasAnyPermission('sales.amounts.view')) {
            $summary['month_amount'] = (int) (clone $visibleSales)
                ->whereIn('status', [SaleStatus::CampaignStarted, SaleStatus::CampaignFinished])
                ->whereBetween('confirmed_at', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth(),
                ])
                ->sum('total_amount');
        }

        return $summary;
    }
}
