<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\FinancialEntry;
use App\Modules\Finance\Models\FinancialSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/** @mixin FinancialEntry */
class FinancialEntryResource extends JsonResource
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
            'created_by_id' => $this->created_by_id,
            'direction' => $this->direction?->value ?? $this->direction,
            'direction_label' => $this->directionLabel(),
            'status' => $this->status?->value ?? $this->status,
            'status_presenter' => $this->status?->toPresenterArray(),
            'counterparty_type' => $this->counterparty_type,
            'counterparty_id' => $this->counterparty_id,
            'counterparty_label' => $this->counterpartyLabel(),
            'originable_type' => $this->originable_type,
            'originable_id' => $this->originable_id,
            'origin_kind' => $this->originKind(),
            'origin_label' => $this->originLabel(),
            'origin_display_name' => $this->originDisplayName(),
            'origin_url' => $this->originUrl(),
            'category_id' => $this->category_id,
            'category' => new FinancialCategoryResource($this->whenLoaded('category')),
            'description' => $this->description,
            'issue_date' => $this->issue_date?->toDateString(),
            'competence_date' => $this->competence_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'gross_amount' => $this->gross_amount,
            'addition_amount' => $this->addition_amount,
            'deduction_amount' => $this->deduction_amount,
            'net_amount' => $this->net_amount,
            'settled_amount' => $this->settled_amount,
            'balance_amount' => $this->balance_amount,
            'currency' => $this->currency,
            'metadata' => $this->metadata,
            'amount_components' => FinancialAmountComponentResource::collection($this->whenLoaded('amountComponents')),
            'installments' => FinancialInstallmentResource::collection($this->whenLoaded('installments')),
            'settlements' => FinancialSettlementResource::collection($this->whenLoaded('settlements')),
            'permissions' => [
                'can_update' => $request->user()?->can('update', $this->resource) ?? false,
                'can_cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
                'can_manage_attachments' => $request->user()?->can('upload', $this->resource) ?? false,
                'can_settle' => ($request->user()?->can('create', FinancialSettlement::class) ?? false)
                    && (int) $this->balance_amount > 0,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function directionLabel(): string
    {
        return match ($this->direction?->value ?? $this->direction) {
            'receivable' => 'A receber',
            'payable' => 'A pagar',
            default => 'Financeiro',
        };
    }

    private function counterpartyLabel(): ?string
    {
        $counterparty = $this->whenLoaded('counterparty');

        if (! $counterparty || $counterparty instanceof MissingValue) {
            return null;
        }

        foreach (['name', 'company_name', 'title', 'uuid'] as $attribute) {
            if (filled($counterparty->{$attribute} ?? null)) {
                return (string) $counterparty->{$attribute};
            }
        }

        return null;
    }
}
