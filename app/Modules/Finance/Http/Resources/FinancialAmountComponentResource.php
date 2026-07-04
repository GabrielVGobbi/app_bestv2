<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

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
            'label' => $this->label,
            'operation' => $this->operation?->value ?? $this->operation,
            'operation_label' => $this->operationLabel(),
            'amount' => $this->amount,
            'position' => $this->position,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function codeLabel(): string
    {
        return match ($this->code?->value ?? $this->code) {
            'principal' => 'Principal',
            'discount' => 'Desconto',
            'tax' => 'Imposto',
            'interest' => 'Juros',
            'penalty' => 'Multa',
            'fee' => 'Taxa',
            'withholding' => 'Retenção',
            'adjustment' => 'Ajuste',
            default => 'Outro',
        };
    }

    private function operationLabel(): string
    {
        return match ($this->operation?->value ?? $this->operation) {
            'add' => 'Soma',
            'subtract' => 'Subtrai',
            default => 'Operação',
        };
    }
}
