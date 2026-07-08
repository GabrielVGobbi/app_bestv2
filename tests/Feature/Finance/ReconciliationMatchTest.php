<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\ReconciliationMatch;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Permissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReconciliationMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_suggests_and_confirms_reconciliation_match(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $account = FinancialAccount::factory()->create(['company_id' => $company->id]);
        $movement = FinancialMovement::factory()->create([
            'company_id' => $company->id,
            'financial_account_id' => $account->id,
            'direction' => FinancialMovementDirection::Inflow,
            'amount' => 10050,
            'occurred_at' => '2026-07-05 12:00:00',
            'reconciled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'extrato.csv',
                    "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n"
                ),
                'source' => 'csv',
                'financial_account_id' => $account->id,
            ])
            ->assertRedirect(route('financial-reconciliation.imports.index'));

        $line = BankStatementLine::query()->sole();
        $match = ReconciliationMatch::query()->sole();

        $this->assertSame($line->id, $match->bank_statement_line_id);
        $this->assertSame($movement->id, $match->financial_movement_id);
        $this->assertSame('suggested', $match->status->value);

        Sanctum::actingAs($user);

        $this->postJson(route('api.reconciliation-matches.confirm', $match))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertNotNull($line->refresh()->reconciled_at);
        $this->assertNotNull($movement->refresh()->reconciled_at);
    }

    public function test_rejecting_match_keeps_movement_unreconciled(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $account = FinancialAccount::factory()->create(['company_id' => $company->id]);
        FinancialMovement::factory()->create([
            'company_id' => $company->id,
            'financial_account_id' => $account->id,
            'amount' => 10050,
            'occurred_at' => '2026-07-05 12:00:00',
            'reconciled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'extrato.csv',
                    "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n"
                ),
                'source' => 'csv',
                'financial_account_id' => $account->id,
            ]);

        $match = ReconciliationMatch::query()->sole();

        Sanctum::actingAs($user);

        $this->postJson(route('api.reconciliation-matches.reject', $match))
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertNull(FinancialMovement::query()->sole()->reconciled_at);
    }

    public function test_reversing_confirmed_match_releases_line_and_movement_with_audit(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $account = FinancialAccount::factory()->create(['company_id' => $company->id]);
        FinancialMovement::factory()->create([
            'company_id' => $company->id,
            'financial_account_id' => $account->id,
            'amount' => 10050,
            'occurred_at' => '2026-07-05 12:00:00',
        ]);

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'extrato.csv',
                    "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n"
                ),
                'source' => 'csv',
                'financial_account_id' => $account->id,
            ]);

        $match = ReconciliationMatch::query()->sole();

        Sanctum::actingAs($user);

        $this->postJson(route('api.reconciliation-matches.confirm', $match))->assertOk();
        $this->postJson(route('api.reconciliation-matches.reverse', $match), [
            'reason' => 'Conciliacao incorreta',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reversed');

        $this->assertNull(BankStatementLine::query()->sole()->reconciled_at);
        $this->assertNull(FinancialMovement::query()->sole()->reconciled_at);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'finance.reconciliation',
            'event' => 'reconciliation_reversed',
            'subject_type' => ReconciliationMatch::class,
            'subject_id' => $match->id,
        ]);
    }

    public function test_import_only_suggests_matches_from_same_financial_account(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $movementAccount = FinancialAccount::factory()->create(['company_id' => $company->id]);
        $statementAccount = FinancialAccount::factory()->create(['company_id' => $company->id]);

        FinancialMovement::factory()->create([
            'company_id' => $company->id,
            'financial_account_id' => $movementAccount->id,
            'direction' => FinancialMovementDirection::Inflow,
            'amount' => 10050,
            'occurred_at' => '2026-07-05 12:00:00',
            'reconciled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'extrato.csv',
                    "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n"
                ),
                'source' => 'csv',
                'financial_account_id' => $statementAccount->id,
            ])
            ->assertRedirect(route('financial-reconciliation.imports.index'));

        $this->assertSame(1, BankStatementLine::query()->count());
        $this->assertSame(0, ReconciliationMatch::query()->count());
    }

    public function test_confirming_match_between_different_financial_accounts_fails(): void
    {
        $user = $this->userWithRole('finance');
        $company = Company::factory()->create();
        $movementAccount = FinancialAccount::factory()->create(['company_id' => $company->id]);
        $statementAccount = FinancialAccount::factory()->create(['company_id' => $company->id]);

        $movement = FinancialMovement::factory()->create([
            'company_id' => $company->id,
            'financial_account_id' => $movementAccount->id,
            'direction' => FinancialMovementDirection::Inflow,
            'amount' => 10050,
            'occurred_at' => '2026-07-05 12:00:00',
            'reconciled_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('financial-reconciliation.imports.store'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'extrato.csv',
                    "date,description,amount,reference\n2026-07-05,Cliente A,100.50,TRX-1\n"
                ),
                'source' => 'csv',
                'financial_account_id' => $statementAccount->id,
            ])
            ->assertRedirect(route('financial-reconciliation.imports.index'));

        $match = ReconciliationMatch::query()->create([
            'company_id' => $company->id,
            'bank_statement_line_id' => BankStatementLine::query()->sole()->id,
            'financial_movement_id' => $movement->id,
            'score' => 100,
            'reasons' => ['manual_test'],
        ]);

        Sanctum::actingAs($user);

        $this->postJson(route('api.reconciliation-matches.confirm', $match))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('financial_account_id');

        $this->assertNull(BankStatementLine::query()->sole()->reconciled_at);
        $this->assertNull($movement->refresh()->reconciled_at);
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
