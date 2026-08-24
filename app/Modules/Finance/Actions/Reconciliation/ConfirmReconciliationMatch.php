<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reconciliation;

use App\Models\User;
use App\Modules\Finance\Enums\BankStatementLineStatus;
use App\Modules\Finance\Enums\ReconciliationMatchStatus;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\ReconciliationMatch;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmReconciliationMatch
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    public function handle(ReconciliationMatch $match, ?User $user = null): ReconciliationMatch
    {
        return DB::transaction(function () use ($match, $user): ReconciliationMatch {
            $match = ReconciliationMatch::query()
                ->lockForUpdate()
                ->findOrFail($match->id);

            if ($user !== null) {
                $this->companyAccess->authorize($user, (int) $match->company_id);
            }

            if ($match->status === ReconciliationMatchStatus::Confirmed) {
                return $this->loadResult($match);
            }

            if ($match->status !== ReconciliationMatchStatus::Suggested) {
                throw ValidationException::withMessages([
                    'match' => __('Somente sugestoes pendentes podem ser confirmadas.'),
                ]);
            }

            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($match->bank_statement_line_id);
            $movement = FinancialMovement::query()->lockForUpdate()->findOrFail($match->financial_movement_id);

            if ((int) $line->company_id !== (int) $match->company_id
                || (int) $movement->company_id !== (int) $match->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => __('Linha, movimento e conciliacao devem pertencer a mesma empresa.'),
                ]);
            }

            if ($line->financial_account_id === null
                || $movement->financial_account_id === null
                || (int) $line->financial_account_id !== (int) $movement->financial_account_id) {
                throw ValidationException::withMessages([
                    'financial_account_id' => __('Linha de extrato e movimento devem pertencer a mesma conta financeira.'),
                ]);
            }

            $conflict = ReconciliationMatch::query()
                ->whereKeyNot($match->id)
                ->where('status', ReconciliationMatchStatus::Confirmed->value)
                ->where(function ($query) use ($line, $movement): void {
                    $query
                        ->where('confirmed_bank_statement_line_id', $line->id)
                        ->orWhere('confirmed_financial_movement_id', $movement->id);
                })
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'match' => __('A linha de extrato ou o movimento ja possui conciliacao confirmada.'),
                ]);
            }

            $now = now();
            $line->forceFill([
                'status' => BankStatementLineStatus::Reconciled,
                'reconciled_at' => $now,
            ])->save();
            $movement->forceFill(['reconciled_at' => $now])->save();
            $match->forceFill([
                'status' => ReconciliationMatchStatus::Confirmed,
                'confirmed_bank_statement_line_id' => $line->id,
                'confirmed_financial_movement_id' => $movement->id,
                'confirmed_by_id' => $user?->getKey(),
                'confirmed_at' => $now,
            ])->save();

            return $this->loadResult($match);
        }, attempts: 3);
    }

    private function loadResult(ReconciliationMatch $match): ReconciliationMatch
    {
        return $match->refresh()->load(['bankStatementLine.financialAccount', 'financialMovement.financialAccount']);
    }
}
