<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reconciliation;

use App\Modules\Finance\Enums\BankStatementLineStatus;
use App\Modules\Finance\Enums\ReconciliationMatchStatus;
use App\Modules\Finance\Models\ReconciliationMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmReconciliationMatch
{
    public function handle(ReconciliationMatch $match): ReconciliationMatch
    {
        return DB::transaction(function () use ($match): ReconciliationMatch {
            $match = ReconciliationMatch::query()
                ->with(['bankStatementLine', 'financialMovement'])
                ->lockForUpdate()
                ->findOrFail($match->id);

            if ($match->status !== ReconciliationMatchStatus::Suggested) {
                throw ValidationException::withMessages([
                    'match' => __('Somente sugestoes pendentes podem ser confirmadas.'),
                ]);
            }

            if ($match->bankStatementLine->financial_account_id === null
                || $match->financialMovement->financial_account_id === null
                || (int) $match->bankStatementLine->financial_account_id !== (int) $match->financialMovement->financial_account_id) {
                throw ValidationException::withMessages([
                    'financial_account_id' => __('Linha de extrato e movimento devem pertencer a mesma conta financeira.'),
                ]);
            }

            $now = now();
            $match->bankStatementLine->forceFill([
                'status' => BankStatementLineStatus::Reconciled,
                'reconciled_at' => $now,
            ])->save();
            $match->financialMovement->forceFill(['reconciled_at' => $now])->save();
            $match->forceFill([
                'status' => ReconciliationMatchStatus::Confirmed,
                'confirmed_by_id' => auth()->id(),
                'confirmed_at' => $now,
            ])->save();

            return $match->refresh()->load(['bankStatementLine.financialAccount', 'financialMovement.financialAccount']);
        }, attempts: 3);
    }
}
