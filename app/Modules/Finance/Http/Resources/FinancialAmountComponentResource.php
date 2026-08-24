<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Enums\FinancialAmountComponentCode;
use App\Modules\Finance\Models\FinancialAmountComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialAmountComponent */
class FinancialAmountComponentResource extends JsonResource
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
            'code' => $this->code?->value ?? $this->code,
            'code_label' => $this->codeLabel(),
            'nature' => $this->nature?->value ?? $this->nature,
            'label' => $this->label,
            'operation' => $this->operation?->value ?? $this->operation,
            'operation_label' => $this->operationLabel(),
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

    private function codeLabel(): string
    {
        if ($this->code instanceof FinancialAmountComponentCode) {
            return $this->code->label();
        }

        return FinancialAmountComponentCode::tryFrom((string) $this->code)?->label() ?? 'Outro';
    }

    private function operationLabel(): string
    {
        return match ($this->operation?->value ?? $this->operation) {
            'add' => 'Soma',
            'subtract' => 'Subtrai',
            'none' => 'Não altera',
            default => 'Operação',
        };
    }
}
