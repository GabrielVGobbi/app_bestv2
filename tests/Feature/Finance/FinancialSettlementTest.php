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

        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
            'settled_at' => '2026-07-04 09:30:00',
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
    }

    public function test_registers_total_payable_settlement_with_outflow_movement(): void
    {
        [$entry, $installment] = $this->manualEntry(FinancialDirection::Payable, 100000);

        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
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
    }

    public function test_blocks_settlement_above_installment_balance(): void
    {
        [, $installment] = $this->manualEntry(FinancialDirection::Receivable, 50000);

        $this->expectException(ValidationException::class);

        app(CreateFinancialSettlement::class)->handle($installment, [
            'gross_amount' => 50001,
        ]);
    }

    public function test_blocks_inactive_payment_method_on_new_settlement(): void
    {
        [, $installment, $company] = $this->manualEntry(FinancialDirection::Receivable, 50000);
        $paymentMethod = PaymentMethod::factory()->create([
            'company_id' => $company->id,
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateFinancialSettlement::class)->handle($installment, [
            'payment_method_id' => $paymentMethod->id,
            'gross_amount' => 50000,
        ]);
    }

    public function test_reversal_preserves_original_settlement_and_restores_balances(): void
    {
        [$entry, $installment] = $this->manualEntry(FinancialDirection::Receivable, 100000);
        $settlement = app(CreateFinancialSettlement::class)->handle($installment, [
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
    }

    public function test_finance_user_can_register_settlement_from_web_route(): void
    {
        $user = $this->userWithRole('finance');
        [, $installment] = $this->manualEntry(FinancialDirection::Receivable, 30000);

        $this->actingAs($user)
            ->post(route('financial-installments.settlements.store', $installment), [
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
