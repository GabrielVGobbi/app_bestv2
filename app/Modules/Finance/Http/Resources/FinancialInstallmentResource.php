<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancialInstallment;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date?->toDateString(),
            'competence_date' => $this->competence_date?->toDateString(),
            'original_amount' => $this->original_amount,
            'settled_amount' => $this->settled_amount,
            'balance_amount' => $this->balance_amount,
            'currency' => $this->currency,
            'settled_at' => $this->settled_at,
            'is_overdue' => $this->isOverdue(),
            'metadata' => $this->metadata,
            'settlement_allocations' => FinancialSettlementAllocationResource::collection($this->whenLoaded('settlementAllocations')),
            'permissions' => [
                'can_settle' => ($request->user()?->can('create', FinancialSettlement::class) ?? false)
                    && (int) $this->balance_amount > 0,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
