<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Finance\Actions\Reconciliation\ConfirmReconciliationMatch;
use App\Modules\Finance\Actions\Reconciliation\ReverseReconciliationMatch;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Models\BankStatementImport;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\ReconciliationMatch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReconciliationExclusivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_statement_line_cannot_confirm_two_movements(): void
    {
        [$company, $account, $line] = $this->statementLine();
        $firstMovement = $this->movement($company, $account);
        $secondMovement = $this->movement($company, $account);
        $firstMatch = $this->match($company, $line, $firstMovement);
        $secondMatch = $this->match($company, $line, $secondMovement);

        app(ConfirmReconciliationMatch::class)->handle($firstMatch);

        try {
            app(ConfirmReconciliationMatch::class)->handle($secondMatch);
            $this->fail('A second confirmed match for the same statement line should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('match', $exception->errors());
        }

        $this->assertSame('suggested', $secondMatch->refresh()->status->value);
    }

    public function test_same_movement_cannot_confirm_two_statement_lines(): void
    {
        [$company, $account, $firstLine] = $this->statementLine();
        [, , $secondLine] = $this->statementLine($company, $account, 'second-line');
        $movement = $this->movement($company, $account);
        $firstMatch = $this->match($company, $firstLine, $movement);
        $secondMatch = $this->match($company, $secondLine, $movement);

        app(ConfirmReconciliationMatch::class)->handle($firstMatch);

        $this->expectException(ValidationException::class);
        app(ConfirmReconciliationMatch::class)->handle($secondMatch);
    }

    public function test_confirm_and_reverse_retries_are_idempotent(): void
    {
        [$company, $account, $line] = $this->statementLine();
        $movement = $this->movement($company, $account);
        $match = $this->match($company, $line, $movement);

        $firstConfirmation = app(ConfirmReconciliationMatch::class)->handle($match);
        $secondConfirmation = app(ConfirmReconciliationMatch::class)->handle($match);

        $this->assertSame($firstConfirmation->id, $secondConfirmation->id);
        $this->assertSame($line->id, $secondConfirmation->confirmed_bank_statement_line_id);
        $this->assertSame($movement->id, $secondConfirmation->confirmed_financial_movement_id);

        $firstReversal = app(ReverseReconciliationMatch::class)->handle($match, ['reason' => 'retry test']);
        $secondReversal = app(ReverseReconciliationMatch::class)->handle($match, ['reason' => 'retry test']);

        $this->assertSame($firstReversal->id, $secondReversal->id);
        $this->assertNull($secondReversal->confirmed_bank_statement_line_id);
        $this->assertNull($secondReversal->confirmed_financial_movement_id);
        $this->assertNull($line->refresh()->reconciled_at);
        $this->assertNull($movement->refresh()->reconciled_at);
    }

    public function test_company_scope_is_enforced_inside_confirmation_action(): void
    {
        $authorizedCompany = Company::factory()->create();
        [$otherCompany, $account, $line] = $this->statementLine();
        $movement = $this->movement($otherCompany, $account);
        $match = $this->match($otherCompany, $line, $movement);
        $user = User::factory()->create();
        $user->companies()->sync([$authorizedCompany->id]);

        $this->expectException(AuthorizationException::class);
        app(ConfirmReconciliationMatch::class)->handle($match, $user);
    }

    public function test_database_constraint_rejects_duplicate_confirmed_guard(): void
    {
        [$company, $account, $line] = $this->statementLine();
        $firstMovement = $this->movement($company, $account);
        $secondMovement = $this->movement($company, $account);
        $this->match($company, $line, $firstMovement, confirmed: true);

        $this->expectException(QueryException::class);
        $this->match($company, $line, $secondMovement, confirmed: true);
    }

    /**
     * @return array{Company, FinancialAccount, BankStatementLine}
     */
    private function statementLine(
        ?Company $company = null,
        ?FinancialAccount $account = null,
        string $reference = 'statement-line',
    ): array {
        $company ??= Company::factory()->create();
        $account ??= FinancialAccount::factory()->create(['company_id' => $company->id]);
        $import = BankStatementImport::query()->create([
            'company_id' => $company->id,
            'financial_account_id' => $account->id,
            'source' => 'csv',
            'status' => 'processed',
            'original_name' => "{$reference}.csv",
            'file_hash' => hash('sha256', $reference),
        ]);
        $line = BankStatementLine::query()->create([
            'bank_statement_import_id' => $import->id,
            'company_id' => $company->id,
            'financial_account_id' => $account->id,
            'posted_at' => '2026-08-24',
            'description' => $reference,
            'amount' => 10000,
            'reference' => $reference,
            'line_hash' => hash('sha256', "{$company->id}:{$account->id}:{$reference}"),
        ]);

        return [$company, $account, $line];
    }

    private function movement(Company $company, FinancialAccount $account): FinancialMovement
    {
        return FinancialMovement::factory()->create([
            'company_id' => $company->id,
            'financial_account_id' => $account->id,
            'direction' => FinancialMovementDirection::Inflow,
            'amount' => 10000,
            'occurred_at' => '2026-08-24 12:00:00',
            'reconciled_at' => null,
        ]);
    }

    private function match(
        Company $company,
        BankStatementLine $line,
        FinancialMovement $movement,
        bool $confirmed = false,
    ): ReconciliationMatch {
        return ReconciliationMatch::query()->create([
            'company_id' => $company->id,
            'bank_statement_line_id' => $line->id,
            'financial_movement_id' => $movement->id,
            'status' => $confirmed ? 'confirmed' : 'suggested',
            'confirmed_bank_statement_line_id' => $confirmed ? $line->id : null,
            'confirmed_financial_movement_id' => $confirmed ? $movement->id : null,
            'confirmed_at' => $confirmed ? now() : null,
            'score' => 100,
            'reasons' => ['test'],
        ]);
    }
}
