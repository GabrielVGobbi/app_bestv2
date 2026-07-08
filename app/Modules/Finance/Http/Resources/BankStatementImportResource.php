<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\BankStatementImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankStatementImport */
class BankStatementImportResource extends JsonResource
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
            'source' => $this->source,
            'status' => $this->status?->value ?? $this->status,
            'status_presenter' => $this->status?->toPresenterArray(),
            'original_name' => $this->original_name,
            'file_hash' => $this->file_hash,
            'line_count' => $this->line_count,
            'duplicate_count' => $this->duplicate_count,
            'lines' => BankStatementLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
        ];
    }
}
