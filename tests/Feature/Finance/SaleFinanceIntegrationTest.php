<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Sales\Actions\Status\CancelSale;
use App\Modules\Sales\Actions\Status\ConfirmSale;
use App\Modules\Sales\Enums\SaleItemStatus;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleCanceled;
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

    public function test_canceled_sale_cancels_open_receivable(): void
    {
        $user = User::factory()->create();
        $sale = $this->confirmedSale($user, 10000);

        app(CancelSale::class)->handle($sale, reason: 'Cliente desistiu', confirmed: true);

        $entry = FinancialEntry::query()->with('installments')->sole();
        $installment = $entry->installments->sole();

        $this->assertSame(FinancialEntryStatus::Canceled, $entry->status);
        $this->assertSame(0, $entry->balance_amount);
        $this->assertSame(FinancialEntryStatus::Canceled, $installment->status);
        $this->assertSame(0, $installment->balance_amount);
        $this->assertSame('Cliente desistiu', $entry->metadata['cancellation']['reason']);
        $this->assertFalse($entry->metadata['cancellation']['requires_review']);
    }

    public function test_canceling_sale_twice_keeps_receivable_canceled(): void
    {
        $user = User::factory()->create();
        $sale = $this->confirmedSale($user, 10000);

        app(CancelSale::class)->handle($sale, reason: 'Cliente desistiu', confirmed: true);
        SaleCanceled::dispatch($sale->refresh());

        $entry = FinancialEntry::query()->sole();

        $this->assertSame(FinancialEntryStatus::Canceled, $entry->status);
    }

    public function test_canceled_sale_with_partial_settlement_keeps_paid_amount_and_flags_review(): void
    {
        $user = User::factory()->create();
        $sale = $this->confirmedSale($user, 10000);

        $entry = FinancialEntry::query()->with('installments')->sole();
        $account = FinancialAccount::factory()->create([
            'company_id' => $sale->company_id,
            'opening_balance_amount' => 0,
            'current_balance_amount' => 0,
            'active' => true,
        ]);

        app(CreateFinancialSettlement::class)->handle($entry->installments->sole(), [
            'financial_account_id' => $account->id,
            'gross_amount' => 4000,
        ]);

        app(CancelSale::class)->handle($sale, reason: 'Cliente desistiu', confirmed: true);

        $entry->refresh()->load('installments');
        $installment = $entry->installments->sole();

        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $entry->status);
        $this->assertSame(4000, $entry->settled_amount);
        $this->assertSame(6000, $entry->balance_amount);
        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $installment->status);
        $this->assertTrue($entry->metadata['cancellation']['requires_review']);
    }

    public function test_canceled_sale_without_receivable_does_not_fail(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create(['seller_id' => $user->id]);

        $this->actingAs($user);

        app(CancelSale::class)->handle($sale, reason: 'Cliente desistiu', confirmed: true);

        $this->assertSame(SaleStatus::Canceled, $sale->refresh()->status);
        $this->assertSame(0, FinancialEntry::query()->count());
    }

    private function confirmedSale(User $user, int $unitAmount): Sale
    {
        $sale = Sale::factory()->create([
            'seller_id' => $user->id,
            'status' => SaleStatus::Quoted,
        ]);
        SaleItem::factory()->for($sale)->create([
            'quantity' => 1,
            'unit_amount' => $unitAmount,
            'discount_amount' => 0,
        ]);

        $this->actingAs($user);

        app(ConfirmSale::class)->handle($sale, confirmed: true);

        return $sale->refresh();
    }
}
