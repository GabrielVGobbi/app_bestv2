<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Actions\Settlements\ReverseFinancialSettlement;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_partial_receivable_settlement_with_allocation_and_inflow_movement(): void
    {
        [$entry, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 100000);
        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);
        $account = $this->financialAccount($company, 100000);

        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'settled_at' => '2026-07-04 09:30:00',
            'financial_account_id' => $account->id,
            'payment_method_id' => $paymentMethod->id,
            'gross_amount' => 40000,
        ]);

        $entry->refresh();
        $installment->refresh();

        $this->assertSame(FinancialSettlementStatus::Posted, $settlement->status);
        $this->assertSame(40000, $settlement->gross_amount);
        $this->assertSame(40000, $settlement->net_amount);
        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $installment->status);
        $this->assertSame(40000, $installment->settled_amount);
        $this->assertSame(60000, $installment->balance_amount);
        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $entry->status);
        $this->assertSame(40000, $entry->settled_amount);
        $this->assertSame(60000, $entry->balance_amount);

        $allocation = $settlement->allocations->sole();
        $this->assertSame($installment->id, $allocation->installment_id);
        $this->assertSame(100000, $allocation->balance_before);
        $this->assertSame(60000, $allocation->balance_after);

        $movement = $settlement->movements->sole();
        $this->assertSame(FinancialMovementDirection::Inflow, $movement->direction);
        $this->assertSame(40000, $movement->amount);
        $this->assertSame($account->id, $movement->financial_account_id);
        $this->assertSame(140000, $account->refresh()->current_balance_amount);
    }

    public function test_registering_settlement_records_activity_for_settlement_and_movement(): void
    {
        $user = User::factory()->create();
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 100000);
        $account = $this->financialAccount($company);

        $this->actingAs($user);

        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'gross_amount' => 50000,
        ]);

        $movement = $settlement->movements->sole();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'finance.settlements',
            'event' => 'created',
            'subject_type' => FinancialSettlement::class,
            'subject_id' => $settlement->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'finance.movements',
            'event' => 'created',
            'subject_type' => FinancialMovement::class,
            'subject_id' => $movement->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);
    }

    public function test_registers_total_payable_settlement_with_outflow_movement(): void
    {
        [$entry, $installment, $company] = $this->manualEntry(FinancialDirection::Payable, 100000);
        $account = $this->financialAccount($company, 120000);

        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'gross_amount' => 100000,
            'discount_amount' => 10000,
        ]);

        $entry->refresh();
        $installment->refresh();

        $this->assertSame(100000, $settlement->gross_amount);
        $this->assertSame(90000, $settlement->net_amount);
        $this->assertSame(FinancialEntryStatus::Paid, $installment->status);
        $this->assertSame(FinancialEntryStatus::Paid, $entry->status);
        $this->assertSame(0, $installment->balance_amount);
        $this->assertNotNull($installment->settled_at);
        $this->assertSame(FinancialMovementDirection::Outflow, $settlement->movements->sole()->direction);
        $this->assertSame(90000, $settlement->movements->sole()->amount);
        $this->assertSame(30000, $account->refresh()->current_balance_amount);
    }

    public function test_blocks_settlement_above_installment_balance(): void
    {
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 50000);
        $account = $this->financialAccount($company);

        $this->expectException(ValidationException::class);

        app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'gross_amount' => 50001,
        ]);
    }

    public function test_blocks_discount_greater_than_gross_even_when_charges_keep_net_positive(): void
    {
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 50000);
        $account = $this->financialAccount($company);

        try {
            app(CreateFinancialSettlement::class)->handle($installment, [
                'financial_account_id' => $account->id,
                'gross_amount' => 10000,
                'discount_amount' => 15000,
                'interest_amount' => 7000,
            ]);

            $this->fail('Desconto maior que o bruto nao deveria ser aceito.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discount_amount', $exception->errors());
        }
    }

    public function test_blocks_inactive_payment_method_on_new_settlement(): void
    {
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 50000);
        $paymentMethod = PaymentMethod::factory()->create([
            'company_id' => $company->id,
            'is_active' => false,
        ]);
        $account = $this->financialAccount($company);

        $this->expectException(ValidationException::class);

        app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'payment_method_id' => $paymentMethod->id,
            'gross_amount' => 50000,
        ]);
    }

    public function test_blocks_new_settlement_without_financial_account(): void
    {
        [, $installment] = $this->manualEntry(FinancialDirection::Receivable, 50000);

        try {
            app(CreateFinancialSettlement::class)->handle($installment, [
                'gross_amount' => 50000,
            ]);

            $this->fail('Nova baixa sem conta financeira nao deveria ser aceita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('financial_account_id', $exception->errors());
        }
    }

    public function test_blocks_new_settlement_with_inactive_financial_account(): void
    {
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 50000);
        $account = $this->financialAccount($company, active: false);

        try {
            app(CreateFinancialSettlement::class)->handle($installment, [
                'financial_account_id' => $account->id,
                'gross_amount' => 50000,
            ]);

            $this->fail('Nova baixa em conta inativa nao deveria ser aceita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('financial_account_id', $exception->errors());
        }
    }

    public function test_blocks_payable_settlement_that_would_leave_account_negative(): void
    {
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Payable, 100000);
        $account = $this->financialAccount($company, 50000);

        try {
            app(CreateFinancialSettlement::class)->handle($installment, [
                'financial_account_id' => $account->id,
                'gross_amount' => 100000,
            ]);

            $this->fail('Saida sem saldo suficiente nao deveria ser aceita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('financial_account_id', $exception->errors());
        }

        $this->assertSame(50000, $account->refresh()->current_balance_amount);
    }

    public function test_reversal_preserves_original_settlement_and_restores_balances(): void
    {
        [$entry, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 100000);
        $account = $this->financialAccount($company, 0);
        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'gross_amount' => 100000,
        ]);

        $reversed = app(ReverseFinancialSettlement::class)->handle($settlement, [
            'reason' => 'Pagamento duplicado',
        ]);

        $entry->refresh();
        $installment->refresh();
        $settlement->refresh();

        $this->assertSame(FinancialSettlementStatus::Reversed, $reversed->status);
        $this->assertSame(FinancialSettlementStatus::Reversed, $settlement->status);
        $this->assertSame('Pagamento duplicado', $settlement->reverse_reason);
        $this->assertDatabaseCount('financial_settlement_allocations', 1);
        $this->assertSame(FinancialEntryStatus::Open, $installment->status);
        $this->assertSame(0, $installment->settled_amount);
        $this->assertSame(100000, $installment->balance_amount);
        $this->assertSame(FinancialEntryStatus::Open, $entry->status);
        $this->assertSame(0, $entry->settled_amount);
        $this->assertSame(100000, $entry->balance_amount);

        $directions = FinancialMovement::query()
            ->where('source_id', $settlement->id)
            ->orderBy('id')
            ->get()
            ->pluck('direction.value')
            ->all();

        $this->assertSame([
            FinancialMovementDirection::Inflow->value,
            FinancialMovementDirection::Outflow->value,
        ], $directions);
        $this->assertSame(0, $account->refresh()->current_balance_amount);
    }

    public function test_reversing_settlement_records_activity_for_reversal_and_inverse_movement(): void
    {
        $user = User::factory()->create();
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 100000);
        $account = $this->financialAccount($company);
        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'gross_amount' => 100000,
        ]);

        $this->actingAs($user);

        $reversed = app(ReverseFinancialSettlement::class)->handle($settlement, [
            'reason' => 'Pagamento duplicado',
        ]);

        $inverseMovement = $reversed->movements
            ->first(fn (FinancialMovement $movement): bool => ($movement->metadata['kind'] ?? null) === 'settlement_reversal');

        $this->assertNotNull($inverseMovement);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'finance.settlements',
            'event' => 'updated',
            'subject_type' => FinancialSettlement::class,
            'subject_id' => $settlement->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'finance.movements',
            'event' => 'created',
            'subject_type' => FinancialMovement::class,
            'subject_id' => $inverseMovement->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);
    }

    public function test_blocks_reversing_settlement_when_any_movement_is_reconciled(): void
    {
        [$entry, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 100000);
        $account = $this->financialAccount($company);
        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'gross_amount' => 100000,
        ]);

        $settlement->movements->sole()->forceFill([
            'reconciled_at' => now(),
        ])->save();

        try {
            app(ReverseFinancialSettlement::class)->handle($settlement, [
                'reason' => 'Pagamento duplicado',
            ]);

            $this->fail('Baixa conciliada nao deveria ser estornada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('settlement_id', $exception->errors());
        }

        $entry->refresh();
        $installment->refresh();
        $settlement->refresh();

        $this->assertSame(FinancialSettlementStatus::Posted, $settlement->status);
        $this->assertSame(FinancialEntryStatus::Paid, $entry->status);
        $this->assertSame(FinancialEntryStatus::Paid, $installment->status);
        $this->assertSame(100000, $installment->settled_amount);
        $this->assertSame(0, $installment->balance_amount);
        $this->assertSame(1, $settlement->movements()->count());
    }

    public function test_reconciled_settlement_cannot_be_reversed_by_policy(): void
    {
        $user = $this->userWithRole('finance');
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 100000);
        $account = $this->financialAccount($company);
        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'financial_account_id' => $account->id,
            'gross_amount' => 100000,
        ]);

        $settlement->movements->sole()->forceFill([
            'reconciled_at' => now(),
        ])->save();

        $this->assertFalse($user->can('reverse', $settlement->refresh()));
    }

    public function test_finance_user_can_register_settlement_from_web_route(): void
    {
        $user = $this->userWithRole('finance');
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 30000);
        $account = $this->financialAccount($company);

        $this->actingAs($user)
            ->post(route('financial-installments.settlements.store', $installment), [
                'financial_account_id' => $account->id,
                'gross_amount' => 30000,
            ])
            ->assertRedirect();

        $this->assertSame(1, FinancialSettlement::query()->count());
        $this->assertSame(1, FinancialMovement::query()->count());
    }

    /**
     * @return array{FinancialEntry, FinancialInstallment, Company}
     */
    private function manualEntry(FinancialDirection $direction, int $amount): array
    {
        $company = Company::factory()->create();
        $counterparty = $direction === FinancialDirection::Receivable
            ? Client::factory()->create(['company_id' => $company->id])
            : Supplier::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => $direction,
            'counterparty_type' => $counterparty::class,
            'counterparty_id' => $counterparty->id,
            'description' => $direction === FinancialDirection::Receivable ? 'Recebível manual' : 'Pagável manual',
            'due_date' => now()->addDays(7)->toDateString(),
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => $amount,
                ],
            ],
        ])->refresh()->load('installments');

        return [$entry, $entry->installments->sole(), $company];
    }

    private function financialAccount(
        Company $company,
        int $balance = 1000000,
        bool $active = true,
        bool $allowsNegativeBalance = false,
    ): FinancialAccount {
        return FinancialAccount::factory()->create([
            'company_id' => $company->id,
            'opening_balance_amount' => $balance,
            'current_balance_amount' => $balance,
            'active' => $active,
            'allows_negative_balance' => $allowsNegativeBalance,
        ]);
    }

    private function userWithRole(string $roleSlug): User
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
