<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Sales\Actions\Status\ConfirmSale;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleConfirmed;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SaleFinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_sale_creates_idempotent_receivable_without_payment_status_on_sale(): void
    {
        Carbon::setTestNow('2026-07-06 10:00:00');

        try {
            $user = User::factory()->create();
            $sale = Sale::factory()->create([
                'seller_id' => $user->id,
                'discount_amount' => 500,
                'status' => SaleStatus::Quoted,
            ]);
            SaleItem::factory()->for($sale)->create([
                'quantity' => 2,
                'unit_amount' => 10000,
                'discount_amount' => 1000,
                'total_amount' => 0,
                'status' => SaleItemStatus::Reserved,
            ]);

            $this->actingAs($user);

            app(ConfirmSale::class)->handle($sale, confirmed: true);
            SaleConfirmed::dispatch($sale->refresh());

            $entry = FinancialEntry::query()
                ->with(['amountComponents', 'installments'])
                ->sole();

            $this->assertSame(FinancialDirection::Receivable, $entry->direction);
            $this->assertSame($sale->getMorphClass(), $entry->originable_type);
            $this->assertSame($sale->id, $entry->originable_id);
            $this->assertSame($sale->client->getMorphClass(), $entry->counterparty_type);
            $this->assertSame($sale->client_id, $entry->counterparty_id);
            $this->assertSame(19000, $entry->gross_amount);
            $this->assertSame(18500, $entry->net_amount);
            $this->assertSame(18500, $entry->balance_amount);
            $this->assertSame('2026-07-06', $entry->due_date->toDateString());
            $this->assertCount(2, $entry->amountComponents);
            $this->assertSame(
                [FinancialAmountComponentCode::Principal->value, FinancialAmountComponentCode::Discount->value],
                $entry->amountComponents->pluck('code.value')->all(),
            );
            $this->assertSame(18500, $entry->installments->sole()->original_amount);
            $this->assertSame(SaleStatus::CampaignStarted, $sale->refresh()->status);
            $this->assertFalse(array_key_exists('payment_status', $sale->getAttributes()));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_sales_from_the_same_origin_do_not_duplicate_financial_entries(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create([
            'seller_id' => $user->id,
            'status' => SaleStatus::Quoted,
        ]);
        SaleItem::factory()->for($sale)->create([
            'quantity' => 1,
            'unit_amount' => 10000,
            'discount_amount' => 0,
        ]);

        $this->actingAs($user);

        app(ConfirmSale::class)->handle($sale, confirmed: true);
        SaleConfirmed::dispatch($sale->refresh());
        SaleConfirmed::dispatch($sale->refresh());

        $this->assertSame(1, FinancialEntry::query()->count());
    }
}
