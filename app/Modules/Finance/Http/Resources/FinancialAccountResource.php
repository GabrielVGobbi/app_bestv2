<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialAccount */
class FinancialAccountResource extends JsonResource
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
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'type' => $this->type?->value ?? $this->type,
            'type_label' => $this->typeLabel(),
            'institution_name' => $this->institution_name,
            'agency_number' => $this->agency_number,
            'account_number' => $this->account_number,
            'account_digit' => $this->account_digit,
            'document_number' => $this->document_number,
            'opening_balance_amount' => $this->opening_balance_amount,
            'current_balance_amount' => $this->current_balance_amount,
            'balance_date' => $this->balance_date?->toDateString(),
            'currency' => $this->currency,
            'active' => $this->active,
            'allows_negative_balance' => $this->allows_negative_balance,
            'metadata' => $this->metadata,
            'permissions' => [
                'can_update' => $request->user()?->can('update', $this->resource) ?? false,
                'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
