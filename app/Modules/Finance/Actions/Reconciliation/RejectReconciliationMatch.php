<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reconciliation;

use App\Models\User;
use App\Modules\Finance\Enums\ReconciliationMatchStatus;
use App\Modules\Finance\Models\ReconciliationMatch;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectReconciliationMatch
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    public function handle(ReconciliationMatch $match, ?User $user = null): ReconciliationMatch
    {
        return DB::transaction(function () use ($match, $user): ReconciliationMatch {
            $match = ReconciliationMatch::query()->lockForUpdate()->findOrFail($match->id);

            if ($user !== null) {
                $this->companyAccess->authorize($user, (int) $match->company_id);
            }

            if ($match->status === ReconciliationMatchStatus::Rejected) {
                return $match->load(['bankStatementLine', 'financialMovement']);
            }

            if ($match->status !== ReconciliationMatchStatus::Suggested) {
                throw ValidationException::withMessages([
                    'match' => __('Somente sugestoes pendentes podem ser rejeitadas.'),
                ]);
            }

            $match->forceFill([
                'status' => ReconciliationMatchStatus::Rejected,
                'rejected_by_id' => $user?->getKey(),
                'rejected_at' => now(),
            ])->save();

            return $match->refresh()->load(['bankStatementLine', 'financialMovement']);
        }, attempts: 3);
    }
}
