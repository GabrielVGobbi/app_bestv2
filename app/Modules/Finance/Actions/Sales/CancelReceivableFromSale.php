<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Sales;

use App\Modules\Finance\Actions\Entries\CancelFinancialEntry;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Sales\Models\Sale;

class CancelReceivableFromSale
{
    public function __construct(private readonly CancelFinancialEntry $cancelFinancialEntry) {}

    public function handle(Sale $sale): ?FinancialEntry
    {
        $entry = FinancialEntry::query()
            ->where('company_id', $sale->company_id)
            ->where('direction', FinancialDirection::Receivable)
            ->where('originable_type', $sale->getMorphClass())
            ->where('originable_id', $sale->id)
            ->first();

        if (! $entry instanceof FinancialEntry) {
            return null;
        }

        if (in_array($entry->status, [FinancialEntryStatus::Canceled, FinancialEntryStatus::Renegotiated], true)) {
            return $entry;
        }

        return $this->cancelFinancialEntry->handle(
            entry: $entry,
            reason: $sale->cancellation_reason ?: __('Venda :order_number cancelada.', ['order_number' => $sale->order_number]),
            allowPartiallySettled: true,
        );
    }
}
