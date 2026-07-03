<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'company_name' => $this->company_name,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'permissions' => [
                'can_update' => $request->user()?->can('update', $this->resource) ?? false,
                'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
            'deletion_blockers' => $this->when(
                method_exists($this->resource, 'deletionBlockers'),
                fn (): array => $this->resource->deletionBlockers(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
