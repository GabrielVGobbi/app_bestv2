<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancialInstallment;
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
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'company_id' => $this->company_id,
            'financial_entry_id' => $this->financial_entry_id,
            'status' => $this->status?->value ?? $this->status,
            'status_presenter' => $this->status?->toPresenterArray(),
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date?->toDateString(),
            'original_amount' => $this->original_amount,
            'settled_amount' => $this->settled_amount,
            'balance_amount' => $this->balance_amount,
            'currency' => $this->currency,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
