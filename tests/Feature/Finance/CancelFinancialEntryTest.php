<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CancelFinancialEntry;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Actions\Entries\RecalculateFinancialEntryStatus;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancelFinancialEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_action_cancels_open_installments_and_zeroes_balance(): void
    {
        $user = User::factory()->create();
        [$entry] = $this->manualEntry(100000);

        $this->actingAs($user);

        $entry = app(CancelFinancialEntry::class)->handle($entry, 'Lançamento duplicado');

        $installment = $entry->installments->sole();

        $this->assertSame(FinancialEntryStatus::Canceled, $entry->status);
        $this->assertSame(0, $entry->balance_amount);
        $this->assertSame(FinancialEntryStatus::Canceled, $installment->status);
        $this->assertSame(0, $installment->balance_amount);
        $this->assertSame(100000, $installment->metadata['cancellation']['canceled_balance_amount']);
        $this->assertSame('Lançamento duplicado', $entry->metadata['cancellation']['reason']);
        $this->assertFalse($entry->metadata['cancellation']['requires_review']);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'finance.entries',
            'event' => 'canceled',
            'subject_type' => FinancialEntry::class,
            'subject_id' => $entry->id,
        ]);
    }

    public function test_recalculation_keeps_entry_canceled(): void
    {
        [$entry] = $this->manualEntry(50000);

        $entry = app(CancelFinancialEntry::class)->handle($entry, 'Duplicado');
        $entry = app(RecalculateFinancialEntryStatus::class)->handle($entry);

        $this->assertSame(FinancialEntryStatus::Canceled, $entry->status);
    }

    public function test_cancel_is_blocked_when_entry_has_settlement(): void
    {
        [$entry, $company] = $this->manualEntry(100000);
        $account = FinancialAccount::factory()->create([
            'company_id' => $company->id,
            'opening_balance_amount' => 0,
            'current_balance_amount' => 0,
            'active' => true,
        ]);

        app(CreateFinancialSettlement::class)->handle($entry->installments->sole(), [
            'financial_account_id' => $account->id,
            'gross_amount' => 40000,
        ]);

        try {
            app(CancelFinancialEntry::class)->handle($entry->refresh(), 'Tentativa inválida');

            $this->fail('Lançamento com baixa não deveria ser cancelado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(FinancialEntryStatus::PartiallyPaid, $entry->refresh()->status);
    }

    public function test_cancel_is_blocked_when_origin_sale_is_active(): void
    {
        $sale = Sale::factory()->create();
        [$entry] = $this->manualEntry(100000, $sale);

        $this->expectException(ValidationException::class);

        app(CancelFinancialEntry::class)->handle($entry, 'Tentativa inválida');
    }

    public function test_cancel_route_cancels_entry_with_reason(): void
    {
        $user = $this->userWithPermissions(['finance.entries.view', 'finance.entries.cancel']);
        [$entry] = $this->manualEntry(100000);

        $this->actingAs($user)
            ->patch(route('financial-entries.cancel', $entry), [
                'reason' => 'Lançamento duplicado',
            ])
            ->assertRedirect();

        $this->assertSame(FinancialEntryStatus::Canceled, $entry->refresh()->status);
    }

    public function test_cancel_route_requires_reason(): void
    {
        $user = $this->userWithPermissions(['finance.entries.view', 'finance.entries.cancel']);
        [$entry] = $this->manualEntry(100000);

        $this->actingAs($user)
            ->patch(route('financial-entries.cancel', $entry))
            ->assertSessionHasErrors('reason');

        $this->assertSame(FinancialEntryStatus::Open, $entry->refresh()->status);
    }

    public function test_cancel_route_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermissions(['finance.entries.view']);
        [$entry] = $this->manualEntry(100000);

        $this->actingAs($user)
            ->patch(route('financial-entries.cancel', $entry), [
                'reason' => 'Sem permissão',
            ])
            ->assertForbidden();
    }

    public function test_canceled_entry_cannot_be_canceled_again(): void
    {
        [$entry] = $this->manualEntry(100000);

        $entry = app(CancelFinancialEntry::class)->handle($entry, 'Duplicado');

        $this->expectException(ValidationException::class);

        app(CancelFinancialEntry::class)->handle($entry, 'De novo');
    }

    /**
     * @return array{FinancialEntry, Company}
     */
    private function manualEntry(int $amount, ?Sale $sale = null): array
    {
        $company = $sale?->company ?? Company::factory()->create();
        $client = $sale?->client ?? Client::factory()->create(['company_id' => $company->id]);

        $entry = app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => FinancialDirection::Receivable,
            'counterparty_type' => Client::class,
            'counterparty_id' => $client->id,
            'originable_type' => $sale?->getMorphClass(),
            'originable_id' => $sale?->id,
            'description' => 'Recebível de teste',
            'due_date' => now()->addDays(7)->toDateString(),
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => $amount,
                ],
            ],
        ])->refresh()->load('installments');

        return [$entry, $company];
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
