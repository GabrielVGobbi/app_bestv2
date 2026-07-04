<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancialMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialMovement */
class FinancialMovementResource extends JsonResource
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
            'financial_account_id' => $this->financial_account_id,
            'direction' => $this->direction?->value ?? $this->direction,
            'amount' => $this->amount,
            'occurred_at' => $this->occurred_at,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'reconciled_at' => $this->reconciled_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
