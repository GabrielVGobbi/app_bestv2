<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Accounts;

use App\Models\User;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReconcileFinancialAccountBalance
{
    public function __construct(private readonly FinancialCompanyAccess $companyAccess) {}

    /**
     * @return array{account_id: int, opening_balance_amount: int, movement_balance_amount: int, expected_balance_amount: int, current_balance_amount: int, drift_amount: int, rebuilt: bool}
     */
    public function handle(FinancialAccount $account, bool $rebuild = false, ?User $actor = null): array
    {
        if ($rebuild && ! app()->environment(['local', 'testing'])) {
            throw new LogicException('Balance rebuild is allowed only in local or testing environments.');
        }

        return DB::transaction(function () use ($account, $rebuild, $actor): array {
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($account->id);

            if ($actor !== null) {
                $this->companyAccess->authorize($actor, (int) $account->company_id);
            }

            $movements = $account->movements()
                ->select(['id', 'direction', 'amount'])
                ->lockForUpdate()
                ->get();
            $movementBalance = (int) $movements->sum(
                fn ($movement): int => ($movement->direction?->value ?? $movement->direction) === 'inflow'
                    ? (int) $movement->amount
                    : -((int) $movement->amount),
            );
            $openingBalance = (int) $account->opening_balance_amount;
            $currentBalance = (int) $account->current_balance_amount;
            $expectedBalance = $openingBalance + $movementBalance;
            $drift = $currentBalance - $expectedBalance;
            $rebuilt = $rebuild && $drift !== 0;

            if ($rebuilt) {
                $account->forceFill(['current_balance_amount' => $expectedBalance])->save();
                $account->logEvent('financial_account_balance_rebuilt', [
                    'before' => $currentBalance,
                    'after' => $expectedBalance,
                    'drift' => $drift,
                ]);
            }

            return [
                'account_id' => (int) $account->id,
                'opening_balance_amount' => $openingBalance,
                'movement_balance_amount' => $movementBalance,
                'expected_balance_amount' => $expectedBalance,
                'current_balance_amount' => $rebuilt ? $expectedBalance : $currentBalance,
                'drift_amount' => $rebuilt ? 0 : $drift,
                'rebuilt' => $rebuilt,
            ];
        }, attempts: 3);
    }
}
