<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reconciliation;

use App\Modules\Finance\Enums\BankStatementLineStatus;
use App\Modules\Finance\Enums\ReconciliationMatchStatus;
use App\Modules\Finance\Models\ReconciliationMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseReconciliationMatch
{
    /**
     * @param  array{reason?: string|null}  $payload
     */
    public function handle(ReconciliationMatch $match, array $payload = []): ReconciliationMatch
    {
        return DB::transaction(function () use ($match, $payload): ReconciliationMatch {
            $match = ReconciliationMatch::query()
                ->with(['bankStatementLine', 'financialMovement'])
                ->lockForUpdate()
                ->findOrFail($match->id);

            if ($match->status !== ReconciliationMatchStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'match' => __('Somente conciliacoes confirmadas podem ser desfeitas.'),
                ]);
            }

            $match->bankStatementLine->forceFill([
                'status' => BankStatementLineStatus::Pending,
                'reconciled_at' => null,
            ])->save();
            $match->financialMovement->forceFill(['reconciled_at' => null])->save();
            $match->forceFill([
                'status' => ReconciliationMatchStatus::Reversed,
                'reversed_by_id' => auth()->id(),
                'reversed_at' => now(),
                'reverse_reason' => blank($payload['reason'] ?? null) ? null : (string) $payload['reason'],
            ])->save();
            $match->logEvent('reconciliation_reversed', [
                'reason' => $match->reverse_reason,
            ]);

            return $match->refresh()->load(['bankStatementLine', 'financialMovement']);
        }, attempts: 3);
    }
}
