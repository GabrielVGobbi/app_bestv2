<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentMethod */
class PaymentMethodResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type?->value ?? $this->type,
            'type_label' => $this->typeLabel(),
            'requires_reference' => $this->requires_reference,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
            'permissions' => [
                'can_update' => $request->user()?->can('update', $this->resource) ?? false,
                'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function typeLabel(): string
    {
        return match ($this->type?->value ?? $this->type) {
            'cash' => 'Dinheiro',
            'pix' => 'Pix',
            'bank_transfer' => 'Transferencia bancaria',
            'boleto' => 'Boleto',
            'credit_card' => 'Cartao de credito',
            'debit_card' => 'Cartao de debito',
            default => 'Outro',
        };
    }
}
