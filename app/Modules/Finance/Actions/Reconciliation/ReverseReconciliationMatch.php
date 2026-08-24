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

class ReverseReconciliationMatch
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    /**
     * @param  array{reason?: string|null}  $payload
     */
    public function handle(ReconciliationMatch $match, array $payload = [], ?User $user = null): ReconciliationMatch
    {
        return DB::transaction(function () use ($match, $payload, $user): ReconciliationMatch {
            $match = ReconciliationMatch::query()
                ->lockForUpdate()
                ->findOrFail($match->id);

            if ($user !== null) {
                $this->companyAccess->authorize($user, (int) $match->company_id);
            }

            if ($match->status === ReconciliationMatchStatus::Reversed) {
                return $this->loadResult($match);
            }

            if ($match->status !== ReconciliationMatchStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'match' => __('Somente conciliacoes confirmadas podem ser desfeitas.'),
                ]);
            }

            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($match->bank_statement_line_id);
            $movement = FinancialMovement::query()->lockForUpdate()->findOrFail($match->financial_movement_id);

            $match->forceFill([
                'status' => ReconciliationMatchStatus::Reversed,
                'confirmed_bank_statement_line_id' => null,
                'confirmed_financial_movement_id' => null,
                'reversed_by_id' => $user?->getKey(),
                'reversed_at' => now(),
                'reverse_reason' => blank($payload['reason'] ?? null) ? null : (string) $payload['reason'],
            ])->save();

            $lineStillReconciled = ReconciliationMatch::query()
                ->whereKeyNot($match->id)
                ->where('status', ReconciliationMatchStatus::Confirmed->value)
                ->where('confirmed_bank_statement_line_id', $line->id)
                ->exists();
            $movementStillReconciled = ReconciliationMatch::query()
                ->whereKeyNot($match->id)
                ->where('status', ReconciliationMatchStatus::Confirmed->value)
                ->where('confirmed_financial_movement_id', $movement->id)
                ->exists();

            if (! $lineStillReconciled) {
                $line->forceFill([
                    'status' => BankStatementLineStatus::Pending,
                    'reconciled_at' => null,
                ])->save();
            }

            if (! $movementStillReconciled) {
                $movement->forceFill(['reconciled_at' => null])->save();
            }

            $match->logEvent('reconciliation_reversed', [
                'reason' => $match->reverse_reason,
            ]);

            return $this->loadResult($match);
        }, attempts: 3);
    }

    private function loadResult(ReconciliationMatch $match): ReconciliationMatch
    {
        return $match->refresh()->load(['bankStatementLine', 'financialMovement']);
    }
}
