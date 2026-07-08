<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\BankStatementLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankStatementLine */
class BankStatementLineResource extends JsonResource
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
            'financial_account' => new FinancialAccountResource($this->whenLoaded('financialAccount')),
            'status' => $this->status?->value ?? $this->status,
            'status_presenter' => $this->status?->toPresenterArray(),
            'posted_at' => $this->posted_at?->toDateString(),
            'description' => $this->description,
            'amount' => $this->amount,
            'reference' => $this->reference,
            'reconciled_at' => $this->reconciled_at,
            'matches' => ReconciliationMatchResource::collection($this->whenLoaded('matches')),
        ];
    }
}
