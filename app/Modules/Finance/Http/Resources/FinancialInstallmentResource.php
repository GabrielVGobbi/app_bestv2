<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/** @mixin FinancialInstallment */
class FinancialInstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->calculatedStatus();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'company_id' => $this->company_id,
            'financial_entry_id' => $this->financial_entry_id,
            'status' => $status->value,
            'status_presenter' => $status->toPresenterArray(),
            'direction' => $this->direction?->value,
            'direction_label' => $this->directionLabel(),
            'installment_number' => $this->installment_number,
            'position_label' => $this->positionLabel(),
            'description' => $this->description,
            'due_date' => $this->due_date?->toDateString(),
            'competence_date' => $this->competence_date?->toDateString(),
            'original_amount' => $this->original_amount,
            'settled_amount' => $this->settled_amount,
            'balance_amount' => $this->balance_amount,
            'discount_amount' => $this->discount_amount,
            'interest_amount' => $this->interest_amount,
            'penalty_amount' => $this->penalty_amount,
            'currency' => $this->currency,
            'settled_at' => $this->settled_at,
            'is_overdue' => $this->isOverdue(),
            'metadata' => $this->metadata,
            'counterparty_type' => $this->counterparty_type,
            'counterparty_id' => $this->counterparty_id,
            'counterparty_label' => $this->counterpartyLabel(),
            'category_id' => $this->category_id,
            'category' => new FinancialCategoryResource($this->whenLoaded('category')),
            'entry' => $this->entryContext(),
            'settlement_allocations' => FinancialSettlementAllocationResource::collection($this->whenLoaded('settlementAllocations')),
            'permissions' => [
                'can_settle' => ($request->user()?->can('create', FinancialSettlement::class) ?? false)
                    && (int) $this->balance_amount > 0,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Contexto minimo do titulo, para a linha da fila ser legivel sem abrir o lancamento.
     *
     * @return array<string, mixed>|null
     */
    private function entryContext(): ?array
    {
        $entry = $this->whenLoaded('financialEntry');

        if (! $entry || $entry instanceof MissingValue) {
            return null;
        }

        return [
            'id' => $entry->id,
            'uuid' => $entry->uuid,
            'description' => $entry->description,
            'origin_label' => $entry->originLabel(),
            'origin_display_name' => $entry->originDisplayName(),
            'installments_count' => $entry->installments_count,
        ];
    }
}
