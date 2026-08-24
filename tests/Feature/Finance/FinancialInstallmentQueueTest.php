<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialInstallmentQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_renders_current_month_and_direction_shortcuts_redirect_to_it(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance');

        $this->actingAs($user)
            ->get(route('financial-entries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-entries/index')
                ->where('direction', 'all')
                ->where('period.month', today()->format('Y-m'))
                ->has('period.starts_at')
                ->has('period.ends_at')
                ->has('summary.receivable.amount')
                ->has('summary.payable.amount')
                ->has('summary.projected_balance')
                ->has('summary.overdue_count'));

        $this->actingAs($user)
            ->get(route('financial-installments.receivable.index'))
            ->assertRedirectToRoute('financial-entries.index', [
                'direction' => FinancialDirection::Receivable->value,
            ]);

        $this->actingAs($user)
            ->get(route('financial-installments.payable.index'))
            ->assertRedirectToRoute('financial-entries.index', [
                'direction' => FinancialDirection::Payable->value,
            ]);

        $this->actingAs($user)
            ->get(route('financial-entries.index', [
                'direction' => FinancialDirection::Payable->value,
                'month' => '2026-08',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-entries/index')
                ->where('direction', FinancialDirection::Payable->value)
                ->where('period.month', '2026-08'));
    }

    public function test_monthly_summary_uses_only_active_installment_balances(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $dueDate = today()->startOfMonth()->toDateString();

        $receivable = $this->createReceivable($company, 100000, [100000], [$dueDate]);
        $this->createPayable($company, 40000, [40000], [$dueDate]);
        $paid = $this->createReceivable($company, 30000, [30000], [$dueDate]);
        $canceled = $this->createPayable($company, 20000, [20000], [$dueDate]);

        $paid->installments()->update([
            'status' => FinancialEntryStatus::Paid,
            'settled_amount' => 30000,
            'balance_amount' => 0,
        ]);
        $canceled->installments()->update([
            'status' => FinancialEntryStatus::Canceled,
        ]);

        $this->actingAs($user)
            ->get(route('financial-entries.index', [
                'month' => today()->format('Y-m'),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.receivable.amount', 100000)
                ->where('summary.receivable.count', 1)
                ->where('summary.payable.amount', 40000)
                ->where('summary.payable.count', 1)
                ->where('summary.projected_balance', 60000)
                ->where('summary.overdue_count', today()->isStartOfMonth() ? 0 : 2));

        $this->assertSame(100000, $receivable->installments()->sole()->balance_amount);
    }

    public function test_queue_lists_one_row_per_installment_not_per_entry(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();

        $this->createReceivable($company, netAmount: 90000, installmentAmounts: [30000, 30000, 30000]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.financial-installments.index', [
            'direction' => FinancialDirection::Receivable->value,
        ]))->assertOk();

        $this->assertCount(3, $response->json('data'));
        $this->assertSame([1, 2, 3], array_column($response->json('data'), 'installment_number'));
        $this->assertSame(['1/3', '2/3', '3/3'], array_column($response->json('data'), 'position_label'));
    }

    public function test_consolidated_api_filters_month_boundaries_and_direction(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $month = today()->startOfMonth();

        $receivable = $this->createReceivable(
            $company,
            30000,
            [30000],
            [$month->toDateString()],
        );
        $payable = $this->createPayable(
            $company,
            40000,
            [40000],
            [$month->endOfMonth()->toDateString()],
        );
        $this->createReceivable(
            $company,
            50000,
            [50000],
            [$month->addMonthNoOverflow()->startOfMonth()->toDateString()],
        );

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.financial-installments.index', [
            'direction' => 'all',
            'month' => $month->format('Y-m'),
        ]))->assertOk();

        $this->assertSame(
            [
                $receivable->installments()->sole()->uuid,
                $payable->installments()->sole()->uuid,
            ],
            array_column($response->json('data'), 'uuid'),
        );
        $this->assertEqualsCanonicalizing(
            [FinancialDirection::Receivable->value, FinancialDirection::Payable->value],
            array_column($response->json('data'), 'direction'),
        );

        $this->getJson(route('api.financial-installments.index', [
            'direction' => FinancialDirection::Payable->value,
            'month' => $month->format('Y-m'),
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', FinancialDirection::Payable->value);
    }

    public function test_agenda_and_api_reject_invalid_months(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance');

        $this->actingAs($user)
            ->get(route('financial-entries.index', ['month' => '2026-13']))
            ->assertSessionHasErrors('month');

        Sanctum::actingAs($user);

        $this->getJson(route('api.financial-installments.index', [
            'direction' => 'all',
            'month' => 'invalid',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('month');
    }

    public function test_payable_queue_never_returns_receivables_even_when_client_forces_the_filter(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();

        $this->createReceivable($company, netAmount: 50000, installmentAmounts: [50000]);
        $this->createPayable($company, netAmount: 70000, installmentAmounts: [70000]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.financial-installments.index', [
            'direction' => FinancialDirection::Payable->value,
            'filter' => ['direction' => FinancialDirection::Receivable->value],
        ]))->assertOk();

        $directions = array_column($response->json('data'), 'direction');

        $this->assertNotEmpty($directions);
        $this->assertSame([FinancialDirection::Payable->value], array_unique($directions));
    }

    public function test_queue_filters_by_due_date_window(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();

        $this->createReceivable(
            $company,
            netAmount: 90000,
            installmentAmounts: [30000, 30000, 30000],
            dueDates: [
                now()->addDays(10)->toDateString(),
                now()->addDays(70)->toDateString(),
                now()->addDays(200)->toDateString(),
            ],
        );

        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.financial-installments.index', [
            'direction' => FinancialDirection::Receivable->value,
            'filter' => [
                'due_date' => [
                    'operator' => 'between',
                    'value' => [
                        'from' => now()->toDateString(),
                        'to' => now()->addDays(90)->toDateString(),
                    ],
                ],
            ],
        ]))->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_overdue_scope_is_derived_independently_from_operational_status(): void
    {
        $company = Company::factory()->create();

        $this->createReceivable(
            $company,
            netAmount: 60000,
            installmentAmounts: [30000, 30000],
            dueDates: [
                now()->subDays(5)->toDateString(),
                now()->addDays(5)->toDateString(),
            ],
        );

        $overdueIds = FinancialInstallment::query()->overdue()->pluck('id')->all();

        $operationallyOpenIds = FinancialInstallment::query()
            ->get()
            ->filter(fn (FinancialInstallment $installment): bool => $installment->calculatedStatus() === FinancialEntryStatus::Open)
            ->pluck('id')
            ->all();

        $this->assertContains($overdueIds[0], $operationallyOpenIds);
        $this->assertCount(1, $overdueIds);
    }

    public function test_queue_is_forbidden_without_permission(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('user');

        $this->actingAs($user)
            ->get(route('financial-entries.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('financial-installments.receivable.index'))
            ->assertForbidden();

        Sanctum::actingAs($user);

        $this->getJson(route('api.financial-installments.index', [
            'direction' => FinancialDirection::Receivable->value,
        ]))->assertForbidden();
    }

    public function test_api_rejects_an_unknown_direction(): void
    {
        $user = $this->userWithRole('finance');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/financial-installments/whatever')->assertNotFound();
    }

    /**
     * @param  array<int, int>  $installmentAmounts
     * @param  array<int, string>|null  $dueDates
     */
    private function createReceivable(Company $company, int $netAmount, array $installmentAmounts, ?array $dueDates = null): FinancialEntry
    {
        $client = Client::factory()->create(['company_id' => $company->id]);

        return $this->createEntry(
            $company,
            FinancialDirection::Receivable,
            Client::class,
            $client->id,
            $netAmount,
            $installmentAmounts,
            $dueDates,
        );
    }

    /**
     * @param  array<int, int>  $installmentAmounts
     * @param  array<int, string>|null  $dueDates
     */
    private function createPayable(Company $company, int $netAmount, array $installmentAmounts, ?array $dueDates = null): FinancialEntry
    {
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        return $this->createEntry(
            $company,
            FinancialDirection::Payable,
            Supplier::class,
            $supplier->id,
            $netAmount,
            $installmentAmounts,
            $dueDates,
        );
    }

    /**
     * @param  class-string  $counterpartyType
     * @param  array<int, int>  $installmentAmounts
     * @param  array<int, string>|null  $dueDates
     */
    private function createEntry(
        Company $company,
        FinancialDirection $direction,
        string $counterpartyType,
        int $counterpartyId,
        int $netAmount,
        array $installmentAmounts,
        ?array $dueDates,
    ): FinancialEntry {
        $installments = [];

        foreach ($installmentAmounts as $index => $amount) {
            $installments[] = [
                'installment_number' => $index + 1,
                'due_date' => $dueDates[$index] ?? now()->addDays(30 * ($index + 1))->toDateString(),
                'amount' => $amount,
            ];
        }

        return app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => $direction,
            'counterparty_type' => $counterpartyType,
            'counterparty_id' => $counterpartyId,
            'description' => 'Lancamento da fila de parcelas',
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => $netAmount,
                ],
            ],
            'installments' => $installments,
        ]);
    }

    private function userWithRole(string $roleSlug): User
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);

        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // `User::booted()` ja anexa o papel padrao `user` na criacao, entao um
        // attach direto duplicaria a chave primaria quando o papel pedido for ele.
        $user->roles()->syncWithoutDetaching($role);

        return $user;
    }
}
