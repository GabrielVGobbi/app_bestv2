<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SettlementIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_equivalent_retry_returns_existing_settlement_without_reapplying_effects(): void
    {
        [$installment, $account] = $this->settlementContext();
        $payload = [
            'idempotency_key' => 'settlement-retry-1',
            'origin' => 'manual',
            'financial_account_id' => $account->id,
            'gross_amount' => 4000,
            'metadata' => ['source' => 'test', 'nested' => ['b' => 2, 'a' => 1]],
        ];

        $first = app(CreateFinancialSettlement::class)->handle($installment, $payload);
        $second = app(CreateFinancialSettlement::class)->handle($installment, [
            ...$payload,
            'metadata' => ['nested' => ['a' => 1, 'b' => 2], 'source' => 'test'],
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('financial_settlements', 1);
        $this->assertDatabaseCount('financial_movements', 1);
        $this->assertSame(6000, $installment->refresh()->balance_amount);
        $this->assertSame(4000, $account->refresh()->current_balance_amount);
    }

    public function test_reusing_key_with_different_payload_returns_explicit_conflict(): void
    {
        [$installment, $account] = $this->settlementContext();
        $basePayload = [
            'idempotency_key' => 'settlement-conflict-1',
            'origin' => 'manual',
            'financial_account_id' => $account->id,
            'gross_amount' => 3000,
        ];

        app(CreateFinancialSettlement::class)->handle($installment, $basePayload);

        try {
            app(CreateFinancialSettlement::class)->handle($installment, [
                ...$basePayload,
                'gross_amount' => 2000,
            ]);
            $this->fail('A divergent retry should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }

        $this->assertDatabaseCount('financial_settlements', 1);
        $this->assertSame(7000, $installment->refresh()->balance_amount);
    }

    public function test_same_key_is_independent_between_companies(): void
    {
        [$firstInstallment, $firstAccount] = $this->settlementContext();
        [$secondInstallment, $secondAccount] = $this->settlementContext();

        $first = app(CreateFinancialSettlement::class)->handle($firstInstallment, [
            'idempotency_key' => 'shared-company-key',
            'financial_account_id' => $firstAccount->id,
            'gross_amount' => 1000,
        ]);
        $second = app(CreateFinancialSettlement::class)->handle($secondInstallment, [
            'idempotency_key' => 'shared-company-key',
            'financial_account_id' => $secondAccount->id,
            'gross_amount' => 1000,
        ]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('financial_settlements', 2);
    }

    /**
     * @return array{FinancialInstallment, FinancialAccount}
     */
    private function settlementContext(): array
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
