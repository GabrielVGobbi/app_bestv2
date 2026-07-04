<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Settlements;

use App\Modules\Finance\Actions\Entries\RecalculateFinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateFinancialSettlement
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(FinancialInstallment $installment, array $payload): FinancialSettlement
    {
        return DB::transaction(function () use ($installment, $payload): FinancialSettlement {
            $installment = FinancialInstallment::query()
                ->with('financialEntry')
                ->lockForUpdate()
                ->findOrFail($installment->id);

            $entry = $installment->financialEntry;

            if (! $entry) {
                throw ValidationException::withMessages([
                    'installment_id' => __('Parcela financeira sem lançamento vinculado.'),
                ]);
            }

            $grossAmount = $this->positiveInteger($payload['gross_amount'] ?? $payload['amount'] ?? null, 'gross_amount');
            $discountAmount = $this->nonNegativeInteger($payload['discount_amount'] ?? 0, 'discount_amount');
            $interestAmount = $this->nonNegativeInteger($payload['interest_amount'] ?? 0, 'interest_amount');
            $penaltyAmount = $this->nonNegativeInteger($payload['penalty_amount'] ?? 0, 'penalty_amount');
            $netAmount = $grossAmount - $discountAmount + $interestAmount + $penaltyAmount;

            if ($netAmount <= 0) {
                throw ValidationException::withMessages([
                    'gross_amount' => __('O valor líquido da baixa deve ser maior que zero.'),
                ]);
            }

            if ((int) $installment->balance_amount <= 0) {
                throw ValidationException::withMessages([
                    'installment_id' => __('Parcela já liquidada.'),
                ]);
            }

            if (in_array($installment->status, [
                FinancialEntryStatus::Canceled,
                FinancialEntryStatus::Reversed,
                FinancialEntryStatus::Renegotiated,
            ], true)) {
                throw ValidationException::withMessages([
                    'installment_id' => __('Parcela não aceita baixa no status atual.'),
                ]);
            }

            if ($grossAmount > (int) $installment->balance_amount) {
                throw ValidationException::withMessages([
                    'gross_amount' => __('A baixa não pode exceder o saldo da parcela.'),
                ]);
            }

            $paymentMethod = $this->resolvePaymentMethod($payload['payment_method_id'] ?? null, (int) $entry->company_id);
            $settledAt = $this->dateTimeOrNow($payload['settled_at'] ?? null);
            $balanceBefore = (int) $installment->balance_amount;
            $balanceAfter = $balanceBefore - $grossAmount;

            $settlement = FinancialSettlement::query()->create([
                'company_id' => $entry->company_id,
                'financial_entry_id' => $entry->id,
                'direction' => $entry->direction,
                'settled_at' => $settledAt,
                'financial_account_id' => $this->nullablePositiveInteger($payload['financial_account_id'] ?? null, 'financial_account_id'),
                'payment_method_id' => $paymentMethod?->id,
                'gross_amount' => $grossAmount,
                'discount_amount' => $discountAmount,
                'interest_amount' => $interestAmount,
                'penalty_amount' => $penaltyAmount,
                'net_amount' => $netAmount,
                'origin' => blank($payload['origin'] ?? null) ? 'manual' : (string) $payload['origin'],
                'status' => FinancialSettlementStatus::Posted,
                'created_by_id' => auth()->id(),
                'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
            ]);

            $settlement->allocations()->create([
                'company_id' => $entry->company_id,
                'installment_id' => $installment->id,
                'amount' => $grossAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            $installment->forceFill([
                'settled_amount' => (int) $installment->settled_amount + $grossAmount,
                'balance_amount' => $balanceAfter,
                'settled_at' => $balanceAfter === 0 ? $settledAt : null,
            ])->save();

            $calculatedStatus = $installment->fresh()->calculatedStatus();
            $installment->forceFill(['status' => $calculatedStatus])->save();

            FinancialMovement::query()->create([
                'company_id' => $entry->company_id,
                'financial_account_id' => $settlement->financial_account_id,
                'direction' => $this->movementDirection($entry->direction),
                'amount' => $settlement->net_amount,
                'occurred_at' => $settledAt,
                'source_type' => $settlement->getMorphClass(),
                'source_id' => $settlement->id,
                'metadata' => [
                    'kind' => 'settlement',
                    'settlement_uuid' => $settlement->uuid,
                    'financial_entry_uuid' => $entry->uuid,
                    'financial_installment_uuid' => $installment->uuid,
                ],
            ]);

            app(RecalculateFinancialEntryStatus::class)->handle($entry);

            return $settlement->refresh()->load(['financialEntry', 'allocations.installment', 'movements', 'paymentMethod']);
        }, attempts: 3);
    }

    private function resolvePaymentMethod(mixed $value, int $companyId): ?PaymentMethod
    {
        if (blank($value)) {
            return null;
        }

        $paymentMethod = PaymentMethod::query()->find($this->positiveInteger($value, 'payment_method_id'));

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('Método de pagamento não encontrado.'),
            ]);
        }

        if ($paymentMethod->company_id !== null && (int) $paymentMethod->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('Método de pagamento pertence a outra empresa.'),
            ]);
        }

        if (! $paymentMethod->is_active) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('Método de pagamento inativo não pode ser usado em nova baixa.'),
            ]);
        }

        return $paymentMethod;
    }

    private function movementDirection(FinancialDirection $direction): FinancialMovementDirection
    {
        return match ($direction) {
            FinancialDirection::Receivable => FinancialMovementDirection::Inflow,
            FinancialDirection::Payable => FinancialMovementDirection::Outflow,
        };
    }

    private function dateTimeOrNow(mixed $value): Carbon
    {
        if (blank($value)) {
            return Carbon::now();
        }

        return Carbon::parse($value);
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value <= 0) {
            throw ValidationException::withMessages([
                $field => __('Informe um valor inteiro positivo em centavos.'),
            ]);
        }

        return $value;
    }

    private function nonNegativeInteger(mixed $value, string $field): int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0) {
            throw ValidationException::withMessages([
                $field => __('Informe um valor inteiro em centavos.'),
            ]);
        }

        return $value;
    }

    private function nullablePositiveInteger(mixed $value, string $field): ?int
    {
        if (blank($value)) {
            return null;
        }

        return $this->positiveInteger($value, $field);
    }
}
