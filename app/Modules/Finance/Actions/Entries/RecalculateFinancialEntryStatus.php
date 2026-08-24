<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Entries;

use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use Illuminate\Support\Collection;

class RecalculateFinancialEntryStatus
{
    /**
     * @param  bool  $eagerLoad  Recarrega componentes, parcelas e contraparte no retorno.
     *                           Util para quem vai serializar o lancamento em seguida,
     *                           desperdicio em processamento de lote (ver o comando
     *                           `finance:recalculate-overdue`).
     */
    public function handle(FinancialEntry $entry, bool $eagerLoad = true): FinancialEntry
    {
        $entry->loadMissing('installments');

        $entry->installments->each(function (FinancialInstallment $installment): void {
            $calculatedStatus = $installment->calculatedStatus();

            if ($installment->status !== $calculatedStatus) {
                $installment->forceFill([
                    'status' => $calculatedStatus,
                ])->save();
            }
        });

        $entry->load('installments');

        $entryStatus = $this->aggregateInstallmentStatus($entry->installments);
        $settledAmount = (int) $entry->installments->sum('settled_amount');
        $balanceAmount = (int) $entry->installments->sum('balance_amount');

        if ($entry->status !== $entryStatus || (int) $entry->settled_amount !== $settledAmount || (int) $entry->balance_amount !== $balanceAmount) {
            $entry->forceFill([
                'status' => $entryStatus,
                'settled_amount' => $settledAmount,
                'balance_amount' => $balanceAmount,
            ])->save();
        }

        $entry->refresh();

        return $eagerLoad
            ? $entry->load(['amountComponents', 'installments', 'counterparty'])
            : $entry;
    }

    /**
     * @param  Collection<int, FinancialInstallment>  $installments
     */
    private function aggregateInstallmentStatus(Collection $installments): FinancialEntryStatus
    {
        $activeInstallments = $installments->reject(fn (FinancialInstallment $installment): bool => in_array($installment->status, [
            FinancialEntryStatus::Canceled,
            FinancialEntryStatus::Reversed,
            FinancialEntryStatus::Renegotiated,
        ], true));

        if ($activeInstallments->isEmpty()) {
            if ($installments->isNotEmpty() && $installments->every(fn (FinancialInstallment $installment): bool => $installment->status === FinancialEntryStatus::Canceled)) {
                return FinancialEntryStatus::Canceled;
            }

            return FinancialEntryStatus::Open;
        }

        if ($activeInstallments->every(fn (FinancialInstallment $installment): bool => $installment->status === FinancialEntryStatus::Paid)) {
            return FinancialEntryStatus::Paid;
        }

        if ($activeInstallments->contains(fn (FinancialInstallment $installment): bool => $installment->status === FinancialEntryStatus::Overdue)) {
            return FinancialEntryStatus::Overdue;
        }

        if ($activeInstallments->contains(fn (FinancialInstallment $installment): bool => $installment->status === FinancialEntryStatus::PartiallyPaid || $installment->settled_amount > 0)) {
            return FinancialEntryStatus::PartiallyPaid;
        }

        return FinancialEntryStatus::Open;
    }
}
