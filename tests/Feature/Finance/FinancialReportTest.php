<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Entries\CreateManualFinancialEntry;
use App\Modules\Finance\Actions\Settlements\CreateFinancialSettlement;
use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Enums\FinancialAmountOperation;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialCategory;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_reports_page_renders_for_authorized_user(): void
    {
        $this->withoutVite();

        $user = $this->userWithRole('finance');

        $this->actingAs($user)
            ->get(route('financial-reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('app/financial-reports/index')
                ->has('dashboard'));
    }

    public function test_dashboard_summarizes_open_overdue_and_monthly_settlements(): void
    {
        Carbon::setTestNow('2026-07-06 10:00:00');

        try {
            $user = $this->userWithRole('finance');
            $company = Company::factory()->create();

            $this->manualEntry($company, FinancialDirection::Receivable, 100000, '2026-07-20');
            $this->manualEntry($company, FinancialDirection::Receivable, 50000, '2026-07-01');
            $this->manualEntry($company, FinancialDirection::Payable, 30000, '2026-07-18');
            $payable = $this->manualEntry($company, FinancialDirection::Payable, 10000, '2026-07-03');
            $account = FinancialAccount::factory()->create([
                'company_id' => $company->id,
                'opening_balance_amount' => 10000,
                'current_balance_amount' => 10000,
            ]);

            app(CreateFinancialSettlement::class)->handle($payable->installments->sole(), [
                'financial_account_id' => $account->id,
                'gross_amount' => 4000,
                'settled_at' => '2026-07-05 09:00:00',
            ]);

            Sanctum::actingAs($user);

            $this->getJson(route('api.financial-reports.dashboard'))
                ->assertOk()
                ->assertJsonPath('data.cards.open_receivables.amount', 150000)
                ->assertJsonPath('data.cards.overdue_receivables.amount', 50000)
                ->assertJsonPath('data.cards.open_payables.amount', 36000)
                ->assertJsonPath('data.cards.overdue_payables.amount', 6000)
                ->assertJsonPath('data.cards.monthly_settlements.amount', 4000)
                ->assertJsonPath('data.account_balances.0.current_balance_amount', 6000);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_receivables_report_includes_descendant_categories_and_full_path(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $parent = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
            'name' => 'Receitas',
        ]);
        $child = FinancialCategory::factory()->income()->create([
            'company_id' => $company->id,
            'parent_id' => $parent->id,
            'name' => 'Campanhas',
        ]);

        $entry = $this->manualEntry(
            company: $company,
            direction: FinancialDirection::Receivable,
            amount: 75000,
            dueDate: '2026-07-20',
            category: $child,
        );

        $this->manualEntry($company, FinancialDirection::Receivable, 12000, '2026-07-20');

        Sanctum::actingAs($user);

        $this->getJson(route('api.financial-reports.open-installments', [
            'direction' => FinancialDirection::Receivable->value,
            'category_id' => $parent->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.entry.uuid', $entry->uuid)
            ->assertJsonPath('data.items.0.direction', FinancialDirection::Receivable->value)
            ->assertJsonPath('data.items.0.category.full_path', 'Receitas > Campanhas')
            ->assertJsonPath('data.summary.open_amount', 75000);
    }

    public function test_installments_schedule_report_uses_installments_and_descendant_categories(): void
    {
        Carbon::setTestNow('2026-07-06 10:00:00');

        try {
            $user = $this->userWithRole('finance');
            $company = Company::factory()->create();
            $parent = FinancialCategory::factory()->income()->create([
                'company_id' => $company->id,
                'name' => 'Receitas',
            ]);
            $child = FinancialCategory::factory()->income()->create([
                'company_id' => $company->id,
                'parent_id' => $parent->id,
                'name' => 'Renovações',
            ]);

            $entry = $this->manualEntry(
                company: $company,
                direction: FinancialDirection::Receivable,
                amount: 75000,
                dueDate: '2026-07-10',
                category: $child,
            );

            $this->manualEntry($company, FinancialDirection::Payable, 30000, '2026-07-09');
            $this->manualEntry($company, FinancialDirection::Receivable, 12000, '2026-08-05');

            Sanctum::actingAs($user);

            $this->getJson(route('api.financial-reports.installments-schedule', [
                'company_id' => $company->id,
                'direction' => FinancialDirection::Receivable->value,
                'category_id' => $parent->id,
                'due_from' => '2026-07-01',
                'due_to' => '2026-07-31',
            ]))
                ->assertOk()
                ->assertJsonCount(1, 'data.items')
                ->assertJsonPath('data.summary.count', 1)
                ->assertJsonPath('data.summary.open_amount', 75000)
                ->assertJsonPath('data.items.0.entry.uuid', $entry->uuid)
                ->assertJsonPath('data.items.0.installment_number', 1)
                ->assertJsonPath('data.items.0.direction', 'receivable')
                ->assertJsonPath('data.items.0.category.full_path', 'Receitas > Renovações');
        } finally {
            Carbon::setTestNow();
        }
    }

    private function manualEntry(
        Company $company,
        FinancialDirection $direction,
        int $amount,
        string $dueDate,
        ?FinancialCategory $category = null,
    ): FinancialEntry {
        $counterparty = $direction === FinancialDirection::Receivable
            ? Client::factory()->create(['company_id' => $company->id])
            : Supplier::factory()->create(['company_id' => $company->id]);

        return app(CreateManualFinancialEntry::class)->handle([
            'company_id' => $company->id,
            'direction' => $direction,
            'counterparty_type' => $counterparty::class,
            'counterparty_id' => $counterparty->id,
            'category_id' => $category?->id,
            'description' => $direction === FinancialDirection::Receivable ? 'Recebivel relatorio' : 'Pagavel relatorio',
            'due_date' => $dueDate,
            'components' => [
                [
                    'code' => FinancialAmountComponentCode::Principal,
                    'operation' => FinancialAmountOperation::Add,
                    'amount' => $amount,
                ],
            ],
        ])->refresh()->load(['installments', 'category']);
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
