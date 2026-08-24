<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Finance\Actions\Accounts\ReconcileFinancialAccountBalance;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class FinancialAccountBalanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_drift_without_changing_account_balance(): void
    {
        $account = $this->accountWithMovements(currentBalance: 99900);

        $result = app(ReconcileFinancialAccountBalance::class)->handle($account);

        $this->assertSame(130000, $result['expected_balance_amount']);
        $this->assertSame(99900, $result['current_balance_amount']);
        $this->assertSame(-30100, $result['drift_amount']);
        $this->assertFalse($result['rebuilt']);
        $this->assertSame(99900, $account->refresh()->current_balance_amount);
    }

    public function test_rebuild_corrects_balance_with_audit_and_is_idempotent(): void
    {
        $account = $this->accountWithMovements(currentBalance: 99900);
        $action = app(ReconcileFinancialAccountBalance::class);

        $rebuilt = $action->handle($account, rebuild: true);

        $this->assertTrue($rebuilt['rebuilt']);
        $this->assertSame(0, $rebuilt['drift_amount']);
        $this->assertSame(130000, $account->refresh()->current_balance_amount);
        $this->assertTrue(Activity::query()
            ->where('log_name', 'finance.accounts')
            ->where('event', 'financial_account_balance_rebuilt')
            ->where('subject_id', $account->id)
            ->exists());

        $secondRun = $action->handle($account, rebuild: true);

        $this->assertFalse($secondRun['rebuilt']);
        $this->assertSame(0, $secondRun['drift_amount']);
    }

    public function test_command_is_dry_run_by_default_and_rebuild_is_explicit(): void
    {
        $account = $this->accountWithMovements(currentBalance: 99900);

        $this->artisan('finance:check-balances', ['--account' => $account->id])
            ->expectsOutputToContain('Dry-run concluído')
            ->assertFailed();

        $this->assertSame(99900, $account->refresh()->current_balance_amount);

        $this->artisan('finance:check-balances', ['--account' => $account->id, '--rebuild' => true])
            ->expectsOutputToContain('Reconstrução concluída')
            ->assertSuccessful();

        $this->assertSame(130000, $account->refresh()->current_balance_amount);
    }

    public function test_rebuild_rejects_actor_without_company_access(): void
    {
        $actor = User::factory()->create();
        $account = $this->accountWithMovements(currentBalance: 99900);
        $actor->companies()->detach();
        $actor->unsetRelation('companies');

        $this->assertFalse(app(FinancialCompanyAccess::class)->canAccess($actor, (int) $account->company_id));

        $this->expectException(AuthorizationException::class);

        try {
            app(ReconcileFinancialAccountBalance::class)->handle($account, rebuild: true, actor: $actor);
        } finally {
            $this->assertSame(99900, $account->refresh()->current_balance_amount);
        }
    }

    private function accountWithMovements(int $currentBalance): FinancialAccount
    {
        $account = FinancialAccount::factory()->create([
            'opening_balance_amount' => 100000,
            'current_balance_amount' => $currentBalance,
        ]);

        FinancialMovement::factory()->create([
            'company_id' => $account->company_id,
            'financial_account_id' => $account->id,
            'direction' => FinancialMovementDirection::Inflow,
            'amount' => 50000,
        ]);
        FinancialMovement::factory()->create([
            'company_id' => $account->company_id,
            'financial_account_id' => $account->id,
            'direction' => FinancialMovementDirection::Outflow,
            'amount' => 20000,
        ]);

        return $account;
    }
}
