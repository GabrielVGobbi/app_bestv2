<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancialSettlementComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialSettlementComponent */
class FinancialSettlementComponentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'company_id' => $this->company_id,
            'settlement_id' => $this->settlement_id,
            'code' => $this->code?->value ?? $this->code,
            'code_label' => $this->code?->label() ?? 'Outro',
            'nature' => $this->nature?->value ?? $this->nature,
            'label' => $this->label,
            'operation' => $this->operation?->value ?? $this->operation,
            'affects_net' => $this->affects_net,
            'amount' => $this->amount,
            'origin' => $this->origin,
            'base_amount' => $this->base_amount,
            'rate_basis_points' => $this->rate_basis_points,
            'calculation_method' => $this->calculation_method,
            'position' => $this->position,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
