<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\Sale;
use App\Supports\Contracts\StatusableEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'company_id' => $this->company_id,
            'client_id' => $this->client_id,
            'seller_id' => $this->seller_id,
            'company' => $this->whenLoaded('company', fn (): array => [
                'id' => $this->company->id,
                'uuid' => $this->company->uuid,
                'name' => $this->company->name,
                'nickname' => $this->company->nickname,
                'cnpj' => $this->company->cnpj,
            ]),
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $this->client->id,
                'uuid' => $this->client->uuid,
                'name' => $this->client->name,
                'email' => $this->client->email,
                'phone' => $this->client->phone,
            ]),
            'seller' => $this->whenLoaded('seller', fn (): ?array => $this->seller === null ? null : [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'email' => $this->seller->email,
            ]),
            'status' => $this->status->getValue(),
            'status_presenter' => $this->status->toPresenterArray(),
            'available_transitions' => collect($this->getAvailableStatusTransitions())
                ->map(fn (StatusableEnum $status): array => $status->toTransitionArray())
                ->values()
                ->all(),
            'order_number' => $this->order_number,
            'title' => $this->title,
            'notes' => $this->notes,
            'sale_items' => $this->whenLoaded(
                'saleItems',
                fn () => SaleItemResource::collection($this->saleItems)->resolve($request),
            ),
            'subtotal_amount' => $this->when($this->canViewAmounts($request), $this->subtotal_amount),
            'discount_amount' => $this->when($this->canViewAmounts($request), $this->discount_amount),
            'total_amount' => $this->when($this->canViewAmounts($request), $this->total_amount),
            'currency' => $this->currency,
            'confirmed_at' => $this->confirmed_at,
            'finished_at' => $this->finished_at,
            'canceled_at' => $this->canceled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function canViewAmounts(Request $request): bool
    {
        $user = $request->user() ?? auth()->user();

        return $user?->can('viewAmounts', $this->resource) ?? false;
    }
}
