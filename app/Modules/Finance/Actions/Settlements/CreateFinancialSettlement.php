<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Settlements;

use App\Models\User;
use App\Modules\Finance\Actions\Entries\RecalculateFinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialDirection;
use App\Modules\Finance\Enums\FinancialEntryStatus;
use App\Modules\Finance\Enums\FinancialMovementDirection;
use App\Modules\Finance\Enums\FinancialSettlementStatus;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialMovement;
use App\Modules\Finance\Models\FinancialSettlement;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Support\FinancialCompanyAccess;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateFinancialSettlement
{
    public function __construct(
        private readonly FinancialCompanyAccess $companyAccess,
        private readonly RecalculateFinancialEntryStatus $recalculateStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(FinancialInstallment $installment, array $payload, ?User $actor = null): FinancialSettlement
    {
        $command = $this->normalizeCommand($installment, $payload);
        $fingerprint = $command['idempotency_key'] === null ? null : $this->fingerprint($command);

        try {
            return DB::transaction(function () use ($installment, $command, $fingerprint, $actor): FinancialSettlement {
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

                if ($actor instanceof User) {
                    $this->companyAccess->authorize($actor, (int) $entry->company_id);
                }

                if ($command['idempotency_key'] !== null) {
                    $existingSettlement = FinancialSettlement::query()
                        ->where('company_id', $entry->company_id)
                        ->where('origin', $command['origin'])
                        ->where('idempotency_key', $command['idempotency_key'])
                        ->lockForUpdate()
                        ->first();

                    if ($existingSettlement instanceof FinancialSettlement) {
                        return $this->resolveExistingSettlement($existingSettlement, (string) $fingerprint);
                    }
                }

                $grossAmount = $command['gross_amount'];
                $discountAmount = $command['discount_amount'];
                $interestAmount = $command['interest_amount'];
                $penaltyAmount = $command['penalty_amount'];
                $netAmount = $grossAmount - $discountAmount + $interestAmount + $penaltyAmount;

                if ($discountAmount > $grossAmount) {
                    throw ValidationException::withMessages([
                        'discount_amount' => __('O desconto não pode ser maior que o valor bruto da baixa.'),
                    ]);
                }

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

                $paymentMethod = $this->resolvePaymentMethod($command['payment_method_id'], (int) $entry->company_id);
                $financialAccount = $this->resolveFinancialAccount($command['financial_account_id'], (int) $entry->company_id);
                $settledAt = $this->dateTimeOrNow($command['settled_at']);
                $balanceBefore = (int) $installment->balance_amount;
                $balanceAfter = $balanceBefore - $grossAmount;
                $movementDirection = $this->movementDirection($entry->direction);

                $this->applyAccountMovement($financialAccount, $movementDirection, $netAmount);

                $settlement = FinancialSettlement::query()->create([
                    'company_id' => $entry->company_id,
                    'financial_entry_id' => $entry->id,
                    'direction' => $entry->direction,
                    'settled_at' => $settledAt,
                    'financial_account_id' => $financialAccount->id,
                    'payment_method_id' => $paymentMethod?->id,
                    'gross_amount' => $grossAmount,
                    'discount_amount' => $discountAmount,
                    'interest_amount' => $interestAmount,
                    'penalty_amount' => $penaltyAmount,
                    'net_amount' => $netAmount,
                    'origin' => $command['origin'],
                    'idempotency_key' => $command['idempotency_key'],
                    'idempotency_fingerprint' => $fingerprint,
                    'status' => FinancialSettlementStatus::Posted,
                    'created_by_id' => $actor?->getKey(),
                    'metadata' => $command['metadata'],
                ]);

                $settlement->allocations()->create([
                    'company_id' => $entry->company_id,
                    'installment_id' => $installment->id,
                    'amount' => $grossAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ]);

                /**
                 * O saldo da parcela anda pelo principal (`gross_amount`), enquanto a
                 * conta bancaria anda pelo liquido. Desconto, juros e multa acumulam
                 * aqui para que a diferenca entre os dois seja auditavel na parcela,
                 * em vez de existir apenas dentro da baixa.
                 */
                $installment->forceFill([
                    'settled_amount' => (int) $installment->settled_amount + $grossAmount,
                    'balance_amount' => $balanceAfter,
                    'discount_amount' => (int) $installment->discount_amount + $discountAmount,
                    'interest_amount' => (int) $installment->interest_amount + $interestAmount,
                    'penalty_amount' => (int) $installment->penalty_amount + $penaltyAmount,
                    'settled_at' => $balanceAfter === 0 ? $settledAt : null,
                ])->save();

                $calculatedStatus = $installment->fresh()->calculatedStatus();
                $installment->forceFill(['status' => $calculatedStatus])->save();

                FinancialMovement::query()->create([
                    'company_id' => $entry->company_id,
                    'financial_account_id' => $settlement->financial_account_id,
                    'direction' => $movementDirection,
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

                $this->recalculateStatus->handle($entry);

                return $this->loadSettlement($settlement);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($command['idempotency_key'] === null || $fingerprint === null) {
                throw $exception;
            }

            $existingSettlement = FinancialSettlement::query()
                ->where('company_id', $installment->company_id)
                ->where('origin', $command['origin'])
                ->where('idempotency_key', $command['idempotency_key'])
                ->first();

            if (! $existingSettlement instanceof FinancialSettlement) {
                throw $exception;
            }

            return $this->resolveExistingSettlement($existingSettlement, $fingerprint);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{installment_id: int, settled_at: ?string, financial_account_id: int, payment_method_id: ?int, gross_amount: int, discount_amount: int, interest_amount: int, penalty_amount: int, origin: string, idempotency_key: ?string, metadata: ?array}
     */
    private function normalizeCommand(FinancialInstallment $installment, array $payload): array
    {
        $idempotencyKey = blank($payload['idempotency_key'] ?? null)
            ? null
            : trim((string) $payload['idempotency_key']);

        return [
            'installment_id' => (int) $installment->getKey(),
            'settled_at' => blank($payload['settled_at'] ?? null)
                ? null
                : Carbon::parse($payload['settled_at'])->toISOString(),
            'financial_account_id' => $this->positiveInteger($payload['financial_account_id'] ?? null, 'financial_account_id'),
            'payment_method_id' => blank($payload['payment_method_id'] ?? null)
                ? null
                : $this->positiveInteger($payload['payment_method_id'], 'payment_method_id'),
            'gross_amount' => $this->positiveInteger($payload['gross_amount'] ?? $payload['amount'] ?? null, 'gross_amount'),
            'discount_amount' => $this->nonNegativeInteger($payload['discount_amount'] ?? 0, 'discount_amount'),
            'interest_amount' => $this->nonNegativeInteger($payload['interest_amount'] ?? 0, 'interest_amount'),
            'penalty_amount' => $this->nonNegativeInteger($payload['penalty_amount'] ?? 0, 'penalty_amount'),
            'origin' => blank($payload['origin'] ?? null) ? 'manual' : trim((string) $payload['origin']),
            'idempotency_key' => $idempotencyKey,
            'metadata' => is_array($payload['metadata'] ?? null)
                ? $this->canonicalizeArray($payload['metadata'])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $command
     */
    private function fingerprint(array $command): string
    {
        unset($command['idempotency_key']);

        return hash('sha256', json_encode($command, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function resolveExistingSettlement(FinancialSettlement $settlement, string $fingerprint): FinancialSettlement
    {
        if (! hash_equals((string) $settlement->idempotency_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('A chave de idempotência já foi usada com dados diferentes.'),
            ]);
        }

        return $this->loadSettlement($settlement);
    }

    private function loadSettlement(FinancialSettlement $settlement): FinancialSettlement
    {
        return $settlement->refresh()->load([
            'financialEntry',
            'allocations.installment',
            'movements.financialAccount',
            'paymentMethod',
            'financialAccount',
        ]);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalizeArray(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalizeArray($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function resolveFinancialAccount(mixed $value, int $companyId): FinancialAccount
    {
        $financialAccount = FinancialAccount::query()
            ->lockForUpdate()
            ->find($this->positiveInteger($value, 'financial_account_id'));

        if (! $financialAccount) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('Conta financeira não encontrada.'),
            ]);
        }

        if ((int) $financialAccount->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('Conta financeira pertence a outra empresa.'),
            ]);
        }

        if (! $financialAccount->active) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('Conta financeira inativa não pode ser usada em nova baixa.'),
            ]);
        }

        return $financialAccount;
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

    private function applyAccountMovement(FinancialAccount $financialAccount, FinancialMovementDirection $direction, int $amount): void
    {
        $currentBalance = (int) $financialAccount->current_balance_amount;
        $projectedBalance = match ($direction) {
            FinancialMovementDirection::Inflow => $currentBalance + $amount,
            FinancialMovementDirection::Outflow => $currentBalance - $amount,
        };

        if ($projectedBalance < 0 && ! $financialAccount->allows_negative_balance) {
            throw ValidationException::withMessages([
                'financial_account_id' => __('Saldo insuficiente na conta financeira.'),
            ]);
        }

        $financialAccount->forceFill([
            'current_balance_amount' => $projectedBalance,
        ])->save();
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
}
