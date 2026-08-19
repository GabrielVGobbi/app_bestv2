<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Playlists\Enums\PlaylistDeliveryTaskStatus;
use App\Modules\Playlists\Enums\PlaylistSlotStatus;
use App\Modules\Playlists\Models\PlaylistDeliveryTask;
use App\Modules\Playlists\Models\PlaylistSlot;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_domain_permissions_receives_no_operational_blocks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                    ->has('operationalDashboard.generated_at')
                    ->missing('operationalDashboard.sales')
                    ->missing('operationalDashboard.playlists')
                    ->missing('operationalDashboard.finance')));
    }

    public function test_sales_summary_respects_view_own_scope_and_amount_permission(): void
    {
        Carbon::setTestNow('2026-08-14 10:00:00');

        try {
            $seller = $this->userWithPermissions(['sales.view-own', 'sales.amounts.view']);
            $otherSeller = User::factory()->create();

            Sale::factory()->create([
                'seller_id' => $seller->id,
                'status' => SaleStatus::CampaignStarted,
                'confirmed_at' => now(),
                'total_amount' => 125_00,
            ]);
            Sale::factory()->create([
                'seller_id' => $seller->id,
                'status' => SaleStatus::Quoted,
            ]);
            Sale::factory()->create([
                'seller_id' => $otherSeller->id,
                'status' => SaleStatus::CampaignStarted,
                'confirmed_at' => now(),
                'total_amount' => 999_00,
            ]);

            $this->actingAs($seller)
                ->get(route('app.dashboard'))
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                        ->where('operationalDashboard.sales.active_campaigns', 1)
                        ->where('operationalDashboard.sales.awaiting_quotes', 1)
                        ->where('operationalDashboard.sales.month_amount', 125_00)));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_amount_is_omitted_without_amount_permission(): void
    {
        $user = $this->userWithPermissions(['sales.view']);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                    ->has('operationalDashboard.sales')
                    ->missing('operationalDashboard.sales.month_amount')));
    }

    public function test_playlist_summary_requires_both_permissions_and_returns_counts(): void
    {
        $user = $this->userWithPermissions([
            'playlist-delivery-tasks.view',
            'playlist-slots.view',
        ]);

        PlaylistDeliveryTask::factory()->create(['status' => PlaylistDeliveryTaskStatus::Pending]);
        PlaylistSlot::factory()->create(['status' => PlaylistSlotStatus::Active]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps(fn (Assert $deferred): Assert => $deferred
                    ->where('operationalDashboard.playlists.pending_deliveries', 1)
                    ->where('operationalDashboard.playlists.active_slots', 1)));
    }

    /** @param array<int, string> $permissionSlugs */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($permissionSlugs)
            ->map(fn (string $slug): int => Permission::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'group' => 'Dashboard',
            ])->id);

        $user->permissions()->sync($permissionIds);

        return $user;
    }
}
