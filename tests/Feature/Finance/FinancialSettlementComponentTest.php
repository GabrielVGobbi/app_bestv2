<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Actions\Settlements\ReverseFinancialSettlement;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialSettlementComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_realized_components_drive_allocation_movement_and_reversal(): void
    {
        [$installment, $account] = $this->context();

        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'idempotency_key' => 'realized-composition-1',
            'financial_account_id' => $account->id,
            'components' => [
                ['code' => 'principal', 'nature' => 'principal', 'amount' => 4000],
                ['code' => 'fee', 'nature' => 'addition', 'amount' => 200],
                ['code' => 'withholding', 'nature' => 'deduction', 'amount' => 100],
                ['code' => 'tax', 'nature' => 'informational', 'operation' => 'none', 'affects_net' => false, 'amount' => 720],
            ],
        ]);

        $this->assertSame(4000, $settlement->gross_amount);
        $this->assertSame(200, $settlement->addition_amount);
        $this->assertSame(100, $settlement->deduction_amount);
        $this->assertSame(4100, $settlement->net_amount);
        $this->assertSame(4000, $settlement->allocations->sole()->amount);
        $this->assertSame(4100, $settlement->movements->sole()->amount);
        $this->assertCount(4, $settlement->components);
        $this->assertSame(4100, $account->refresh()->current_balance_amount);

        $reversed = app(ReverseFinancialSettlement::class)->handle($settlement, ['reason' => 'teste']);

        $this->assertSame('reversed', $reversed->status->value);
        $this->assertSame(0, $account->refresh()->current_balance_amount);
        $this->assertCount(4, $reversed->components);
    }

    public function test_rejects_invalid_realized_component_nature(): void
    {
        [$installment, $account] = $this->context();

        $this->expectException(ValidationException::class);

        app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'components' => [
                ['code' => 'principal', 'amount' => 4000],
                ['code' => 'discount', 'nature' => 'addition', 'amount' => 100],
            ],
        ]);
    }

    public function test_legacy_settlement_backfill_creates_realized_components_and_consolidates_totals(): void
    {
        $company = Company::factory()->create();
        $entry = FinancialEntry::factory()->create(['company_id' => $company->id]);
        $settlement = FinancialSettlement::factory()->create([
            'company_id' => $company->id,
            'financial_entry_id' => $entry->id,
            'gross_amount' => 10000,
            'addition_amount' => 0,
            'deduction_amount' => 0,
            'discount_amount' => 500,
            'interest_amount' => 200,
            'penalty_amount' => 100,
            'net_amount' => 9800,
        ]);

        $migration = require app_path('Modules/Finance/Database/Migrations/2026_08_24_145411_backfill_financial_settlement_components.php');
        $migration->up();
        $settlement->refresh();

        $this->assertSame(300, $settlement->addition_amount);
        $this->assertSame(500, $settlement->deduction_amount);
        $this->assertSame(9800, $settlement->net_amount);
        $this->assertCount(4, $settlement->components()->get());
    }

    /** @return array{FinancialInstallment, FinancialAccount} */
    private function context(): array
    {
        $company = Company::factory()->create();
        $entry = FinancialEntry::factory()->create([
            'company_id' => $company->id,
            'gross_amount' => 10000,
            'net_amount' => 10000,
            'balance_amount' => 10000,
        ]);
        $installment = FinancialInstallment::factory()->create([
            'company_id' => $company->id,
            'financial_entry_id' => $entry->id,
            'original_amount' => 10000,
            'balance_amount' => 10000,
        ]);
        $account = FinancialAccount::factory()->create([
            'company_id' => $company->id,
            'opening_balance_amount' => 0,
            'current_balance_amount' => 0,
        ]);

        return [$installment, $account];
    }
}
