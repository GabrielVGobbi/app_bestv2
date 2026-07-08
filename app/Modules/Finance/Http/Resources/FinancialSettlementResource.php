<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialSettlement */
class FinancialSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'company_id' => $this->company_id,
            'financial_entry_id' => $this->financial_entry_id,
            'direction' => $this->direction?->value ?? $this->direction,
            'settled_at' => $this->settled_at,
            'financial_account_id' => $this->financial_account_id,
            'financial_account' => new FinancialAccountResource($this->whenLoaded('financialAccount')),
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'gross_amount' => $this->gross_amount,
            'discount_amount' => $this->discount_amount,
            'interest_amount' => $this->interest_amount,
            'penalty_amount' => $this->penalty_amount,
            'net_amount' => $this->net_amount,
            'origin' => $this->origin,
            'status' => $this->status?->value ?? $this->status,
            'status_presenter' => $this->status?->toPresenterArray(),
            'created_by_id' => $this->created_by_id,
            'reversed_at' => $this->reversed_at,
            'reversed_by_id' => $this->reversed_by_id,
            'reverse_reason' => $this->reverse_reason,
            'metadata' => $this->metadata,
            'allocations' => FinancialSettlementAllocationResource::collection($this->whenLoaded('allocations')),
            'movements' => FinancialMovementResource::collection($this->whenLoaded('movements')),
            'permissions' => [
                'can_reverse' => $request->user()?->can('reverse', $this->resource) ?? false,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
