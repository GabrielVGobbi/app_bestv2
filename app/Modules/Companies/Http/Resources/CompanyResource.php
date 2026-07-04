<?php

declare(strict_types=1);

namespace App\Modules\Companies\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'cnpj' => $this->cnpj,
            'fiscal_metadata' => $this->fiscal_metadata,
            'logo_disk' => $this->logo_disk,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logoUrl(),
            'logo_initials' => $this->logoInitials(),
            'logo_color' => $this->logoColor(),
            'logo_updated_at' => $this->logo_updated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
