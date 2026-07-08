<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\ReconciliationMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReconciliationMatch */
class ReconciliationMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status?->value ?? $this->status,
            'status_presenter' => $this->status?->toPresenterArray(),
            'score' => $this->score,
            'reasons' => $this->reasons,
            'bank_statement_line_id' => $this->bank_statement_line_id,
            'financial_movement_id' => $this->financial_movement_id,
            'confirmed_at' => $this->confirmed_at,
            'rejected_at' => $this->rejected_at,
            'reversed_at' => $this->reversed_at,
            'reverse_reason' => $this->reverse_reason,
        ];
    }
}
