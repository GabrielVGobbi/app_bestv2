<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Settlements;

use App\Modules\Finance\Actions\Entries\RecalculateFinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseFinancialSettlement
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(FinancialSettlement $settlement, array $payload = []): FinancialSettlement
    {
        return DB::transaction(function () use ($settlement, $payload): FinancialSettlement {
            $settlement = FinancialSettlement::query()
                ->with(['allocations.installment', 'financialEntry'])
                ->lockForUpdate()
                ->findOrFail($settlement->id);

            if (! $settlement->isPosted()) {
                throw ValidationException::withMessages([
                    'settlement_id' => __('Baixa já estornada.'),
                ]);
            }

            $movements = $settlement->movements()
                ->lockForUpdate()
                ->get();

            if ($movements->contains(fn (FinancialMovement $movement): bool => $movement->reconciled_at !== null)) {
                throw ValidationException::withMessages([
                    'settlement_id' => __('Baixa conciliada não pode ser estornada. Desfaça a conciliação antes de alterar a baixa.'),
                ]);
            }

            $entry = $settlement->financialEntry;
            $originalMovement = $movements->sortBy('id')->first();
            $inverseDirection = $this->oppositeDirection($originalMovement?->direction);
            $financialAccount = $this->resolveFinancialAccount($settlement->financial_account_id);

            if ($financialAccount instanceof FinancialAccount) {
                $this->applyAccountMovement($financialAccount, $inverseDirection, (int) $settlement->net_amount);
            }

            foreach ($settlement->allocations as $allocation) {
                $installment = FinancialInstallment::query()
                    ->lockForUpdate()
                    ->findOrFail($allocation->installment_id);

                $settledAmount = max(0, (int) $installment->settled_amount - (int) $allocation->amount);
                $balanceAmount = (int) $installment->balance_amount + (int) $allocation->amount;

                $installment->forceFill([
                    'settled_amount' => $settledAmount,
                    'balance_amount' => $balanceAmount,
                    'settled_at' => null,
                ])->save();

                $installment->forceFill([
                    'status' => $installment->fresh()->calculatedStatus(),
                ])->save();
            }

            $settlement->forceFill([
                'status' => FinancialSettlementStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by_id' => auth()->id(),
                'reverse_reason' => blank($payload['reason'] ?? null) ? null : (string) $payload['reason'],
            ])->save();

            FinancialMovement::query()->create([
                'company_id' => $settlement->company_id,
                'financial_account_id' => $settlement->financial_account_id,
                'direction' => $inverseDirection,
                'amount' => $settlement->net_amount,
                'occurred_at' => now(),
                'source_type' => $settlement->getMorphClass(),
                'source_id' => $settlement->id,
                'metadata' => [
                    'kind' => 'settlement_reversal',
                    'settlement_uuid' => $settlement->uuid,
                    'original_movement_id' => $originalMovement?->id,
                    'reason' => blank($payload['reason'] ?? null) ? null : (string) $payload['reason'],
                ],
            ]);

            if ($entry) {
                app(RecalculateFinancialEntryStatus::class)->handle($entry);
            }

            return $settlement->refresh()->load(['financialEntry', 'allocations.installment', 'movements.financialAccount', 'paymentMethod', 'financialAccount']);
        }, attempts: 3);
    }

    private function resolveFinancialAccount(?int $financialAccountId): ?FinancialAccount
    {
        if ($financialAccountId === null) {
            return null;
        }

        $financialAccount = FinancialAccount::query()
            ->lockForUpdate()
            ->find($financialAccountId);

        if (! $financialAccount) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('Conta financeira da baixa nÃ£o foi encontrada.'),
            ]);
        }

        return $financialAccount;
    }

    private function oppositeDirection(FinancialMovementDirection|string|null $direction): FinancialMovementDirection
    {
        $value = $direction instanceof FinancialMovementDirection ? $direction->value : $direction;

        return match ($value) {
            FinancialMovementDirection::Inflow->value => FinancialMovementDirection::Outflow,
            default => FinancialMovementDirection::Inflow,
        };
    }

    private function applyAccountMovement(FinancialAccount $financialAccount, FinancialMovementDirection $direction, int $amount): void
    {
        $currentBalance = (int) $financialAccount->current_balance_amount;
        $projectedBalance = match ($direction) {
            FinancialMovementDirection::Inflow => $currentBalance + $amount,
            FinancialMovementDirection::Outflow => $currentBalance - $amount,
        };

        if ($projectedBalance < 0 && ! $financialAccount->allows_negative_balance) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('Saldo insuficiente na conta financeira para estornar esta baixa.'),
            ]);
        }

        $financialAccount->forceFill([
            'current_balance_amount' => $projectedBalance,
        ])->save();
    }
}
