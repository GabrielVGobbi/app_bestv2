<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Entries;

use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use Illuminate\Support\Collection;

class RecalculateFinancialEntryStatus
{
    public function handle(FinancialEntry $entry): FinancialEntry
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

        if ($entry->status !== $entryStatus) {
            $entry->forceFill([
                'status' => $entryStatus,
            ])->save();
        }

        return $entry->refresh()->load(['amountComponents', 'installments', 'counterparty']);
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
