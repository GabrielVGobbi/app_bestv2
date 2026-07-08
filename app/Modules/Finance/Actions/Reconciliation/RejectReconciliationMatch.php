<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reconciliation;

use App\Modules\Finance\Enums\ReconciliationMatchStatus;
use App\Modules\Finance\Models\ReconciliationMatch;
use Illuminate\Validation\ValidationException;

class RejectReconciliationMatch
{
    public function handle(ReconciliationMatch $match): ReconciliationMatch
    {
        if ($match->status !== ReconciliationMatchStatus::Suggested) {
            throw ValidationException::withMessages([
                'match' => __('Somente sugestoes pendentes podem ser rejeitadas.'),
            ]);
        }

        $match->forceFill([
            'status' => ReconciliationMatchStatus::Rejected,
            'rejected_by_id' => auth()->id(),
            'rejected_at' => now(),
        ])->save();

        return $match->refresh()->load(['bankStatementLine', 'financialMovement']);
    }
}
