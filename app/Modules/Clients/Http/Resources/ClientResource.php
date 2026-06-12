<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Resources;

use App\Http\Resources\UserResource;
use App\Modules\Addresses\Http\Resources\AddressResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'responsible_user_id' => $this->responsible_user_id,
            'responsible_user' => new UserResource($this->whenLoaded('responsibleUser')),
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'document' => $this->document,
            'musical_genre' => $this->musical_genre,
            'is_agent' => $this->is_agent,
            'notes' => $this->notes,
            'classification' => $this->classification,
            'source_id' => $this->source_id,
            'status' => $this->status,
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
