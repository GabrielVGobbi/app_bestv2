<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceCompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_lists_and_opens_only_entries_from_assigned_companies(): void
    {
        [$user, $assignedCompany, $otherCompany] = $this->restrictedFinanceUser();
        $assignedEntry = FinancialEntry::factory()->create(['company_id' => $assignedCompany->id]);
        $otherEntry = FinancialEntry::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($user);

        $this->getJson(route('api.financial-entries.index'))
            ->assertOk()
            ->assertJsonFragment(['id' => $assignedEntry->id])
            ->assertJsonMissing(['id' => $otherEntry->id]);

        $this->getJson(route('api.financial-entries.show', $assignedEntry))->assertOk();
        $this->getJson(route('api.financial-entries.show', $otherEntry))->assertForbidden();
    }

    public function test_client_company_id_cannot_expand_create_access(): void
    {
        [$user, , $otherCompany] = $this->restrictedFinanceUser();

        $this->actingAs($user)
            ->post(route('financial-entries.store'), [
                'company_id' => $otherCompany->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('financial_entries', 0);
    }

    public function test_settlement_and_reports_reject_an_unassigned_company(): void
    {
        [$user, , $otherCompany] = $this->restrictedFinanceUser();
        $installment = FinancialInstallment::factory()->create(['company_id' => $otherCompany->id]);
        $account = FinancialAccount::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($user)
            ->post(route('financial-installments.settlements.store', $installment), [
                'financial_account_id' => $account->id,
                'gross_amount' => 100,
            ])
            ->assertForbidden();

        Sanctum::actingAs($user);

        $this->getJson(route('api.financial-reports.dashboard', ['company_id' => $otherCompany->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('financial_settlements', 0);
    }

    public function test_global_administrator_keeps_cross_company_access_without_pivot_rows(): void
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);
        $developer = Role::query()->where('slug', 'developer')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($developer);
        $firstEntry = FinancialEntry::factory()->create();
        $secondEntry = FinancialEntry::factory()->create();

        $user->companies()->detach();
        $this->assertSame(0, $user->companies()->count());

        Sanctum::actingAs($user);

        $this->getJson(route('api.financial-entries.index'))
            ->assertOk()
            ->assertJsonFragment(['id' => $firstEntry->id])
            ->assertJsonFragment(['id' => $secondEntry->id]);
    }

    /**
     * @return array{User, Company, Company}
     */
    private function restrictedFinanceUser(): array
    {
        $this->seed([RolesSeeder::class, PermissionsSeeder::class]);
        $finance = Role::query()->where('slug', 'finance')->firstOrFail();
        $assignedCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create();
        $user->roles()->attach($finance);
        $user->companies()->sync([
            $assignedCompany->id => ['access_source' => 'manual'],
        ]);

        return [$user, $assignedCompany, $otherCompany];
    }
}
