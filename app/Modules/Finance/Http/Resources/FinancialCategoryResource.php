<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialCategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type?->value ?? $this->type,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'breadcrumb' => collect($this->breadcrumb())
                ->map(fn ($category): array => [
                    'id' => $category->id,
                    'uuid' => $category->uuid,
                    'name' => $category->name,
                    'color' => $category->color,
                ])
                ->values()
                ->all(),
            'full_path' => $this->fullPath(),
            'depth' => $this->depth(),
            'has_children' => $this->hasChildren(),
            'children' => FinancialCategoryResource::collection($this->whenLoaded('children')),
            'permissions' => [
                'can_update' => $request->user()?->can('update', $this->resource) ?? false,
                'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
            'deletion_blockers' => $this->deletionBlockers(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
