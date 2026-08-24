<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Entries;

use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Sale;
use App\Supports\ActivityActions\ActivityActionLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelFinancialEntry
{
    public function __construct(
        private readonly RecalculateFinancialEntryStatus $recalculateFinancialEntryStatus,
        private readonly ActivityActionLogger $activityLogger,
    ) {}

    /**
     * Cancela um lançamento financeiro zerando o saldo das parcelas sem baixa.
     *
     * Com `$allowPartiallySettled = false` (cancelamento manual), qualquer valor já
     * baixado bloqueia o cancelamento. Com `true` (cancelamento derivado de venda),
     * as parcelas já liquidadas são preservadas e o lançamento fica marcado para revisão.
     */
    public function handle(FinancialEntry $entry, ?string $reason = null, bool $allowPartiallySettled = false): FinancialEntry
    {
        return DB::transaction(function () use ($entry, $reason, $allowPartiallySettled): FinancialEntry {
            $entry = FinancialEntry::query()
                ->with('installments')
                ->lockForUpdate()
                ->findOrFail($entry->id);

            if (in_array($entry->status, [FinancialEntryStatus::Canceled, FinancialEntryStatus::Renegotiated], true)) {
                throw ValidationException::withMessages([
                    'status' => __('Lançamento não aceita cancelamento no status atual.'),
                ]);
            }

            $settledAmount = (int) $entry->installments->sum('settled_amount');

            if ($settledAmount > 0 && ! $allowPartiallySettled) {
                throw ValidationException::withMessages([
                    'status' => __('Lançamento com baixa registrada não pode ser cancelado. Estorne as baixas antes de cancelar.'),
                ]);
            }

            if (! $allowPartiallySettled && $this->hasActiveSaleOrigin($entry)) {
                throw ValidationException::withMessages([
                    'status' => __('Este lançamento pertence a uma venda ativa. Cancele a venda para cancelar o recebível.'),
                ]);
            }

            $canceledAt = now();
            $canceledInstallments = 0;

            foreach ($entry->installments as $installment) {
                if (! $this->isCancelable($installment)) {
                    continue;
                }

                $installment->forceFill([
                    'status' => FinancialEntryStatus::Canceled,
                    'balance_amount' => 0,
                    'metadata' => [
                        ...($installment->metadata ?? []),
                        'cancellation' => [
                            'reason' => $reason,
                            'canceled_at' => $canceledAt->toIso8601String(),
                            'canceled_balance_amount' => (int) $installment->balance_amount,
                        ],
                    ],
                ])->save();

                $canceledInstallments++;
            }

            $requiresReview = $settledAmount > 0;

            $entry->forceFill([
                'metadata' => [
                    ...($entry->metadata ?? []),
                    'cancellation' => [
                        'reason' => $reason,
                        'canceled_at' => $canceledAt->toIso8601String(),
                        'canceled_by_id' => auth()->id(),
                        'requires_review' => $requiresReview,
                    ],
                ],
            ])->save();

            $entry = $this->recalculateFinancialEntryStatus->handle($entry);

            $this->activityLogger->log(
                actionKey: 'finance.entries.canceled',
                subject: $entry,
                properties: [
                    'reason' => $reason,
                    'canceled_installments' => $canceledInstallments,
                    'settled_amount' => $settledAmount,
                    'requires_review' => $requiresReview,
                ],
            );

            return $entry;
        }, attempts: 3);
    }

    private function isCancelable(FinancialInstallment $installment): bool
    {
        if (in_array($installment->status, [
            FinancialEntryStatus::Canceled,
            FinancialEntryStatus::Reversed,
            FinancialEntryStatus::Renegotiated,
        ], true)) {
            return false;
        }

        return (int) $installment->settled_amount === 0
            && (int) $installment->balance_amount > 0;
    }

    private function hasActiveSaleOrigin(FinancialEntry $entry): bool
    {
        if ($entry->originable_id === null) {
            return false;
        }

        $origin = $entry->originable;

        return $origin instanceof Sale && $origin->status !== SaleStatus::Canceled;
    }
}
