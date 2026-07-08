<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reconciliation;

use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Models\BankStatementLine;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\ReconciliationMatch;
use Illuminate\Support\Carbon;

class SuggestReconciliationMatches
{
    public function handle(BankStatementLine $line): void
    {
        $amount = abs((int) $line->amount);
        $direction = (int) $line->amount >= 0
            ? FinancialMovementDirection::Inflow
            : FinancialMovementDirection::Outflow;
        $postedAt = Carbon::parse($line->posted_at);

        if ($line->financial_account_id === null) {
            return;
        }

        FinancialMovement::query()
            ->whereNull('reconciled_at')
            ->where('financial_account_id', $line->financial_account_id)
            ->where('amount', $amount)
            ->where('direction', $direction)
            ->when($line->company_id !== null, fn ($query) => $query->where('company_id', $line->company_id))
            ->whereBetween('occurred_at', [
                $postedAt->copy()->subDays(2)->startOfDay(),
                $postedAt->copy()->addDays(2)->endOfDay(),
            ])
            ->limit(5)
            ->get()
            ->each(function (FinancialMovement $movement) use ($line): void {
                ReconciliationMatch::query()->firstOrCreate(
                    [
                        'bank_statement_line_id' => $line->id,
                        'financial_movement_id' => $movement->id,
                    ],
                    [
                        'company_id' => $line->company_id ?? $movement->company_id,
                        'score' => 90,
                        'reasons' => ['amount', 'date'],
                    ],
                );
            });
    }
}
