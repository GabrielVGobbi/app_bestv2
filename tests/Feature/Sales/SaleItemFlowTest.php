<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Sales\Actions\Items\RemoveSaleItem;
use App\Modules\Sales\Actions\Items\SyncSaleTotals;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaleItemFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_sale_item_syncs_sale_totals(): void
    {
        $sale = Sale::factory()->create(['discount_amount' => 100]);
        $removedItem = SaleItem::factory()->for($sale)->create(['total_amount' => 1000]);
        SaleItem::factory()->for($sale)->create(['total_amount' => 2500]);
        app(SyncSaleTotals::class)->handle($sale);

        app(RemoveSaleItem::class)->handle($sale, $removedItem);

        $sale->refresh();

        $this->assertSoftDeleted($removedItem);
        $this->assertSame(2500, $sale->subtotal_amount);
        $this->assertSame(2400, $sale->total_amount);
    }

    public function test_remove_sale_item_blocks_non_editable_sale(): void
    {
        $sale = Sale::factory()->create();
        $sale->applySystemStatus(SaleStatus::CampaignStarted);
        $item = SaleItem::factory()->for($sale)->create();

        $this->expectException(ValidationException::class);

        app(RemoveSaleItem::class)->handle($sale, $item);
    }

    public function test_sale_item_routes_require_item_permission(): void
    {
        $user = $this->userWithPermissions(['sales.update']);
        $sale = Sale::factory()->create(['seller_id' => $user->id]);
        $item = SaleItem::factory()->for($sale)->create();

        $this->actingAs($user)
            ->post(route('sales.items.store', $sale), ['type' => 'service'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('sales.items.destroy', [$sale, $item]))
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
