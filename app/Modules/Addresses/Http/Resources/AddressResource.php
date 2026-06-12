<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
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
            'full_address' => $this->full_address,
            'zipcode' => $this->zipcode,
            'street' => $this->street,
            'number' => $this->number,
            'district' => $this->district,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'city_code' => $this->city_code,
            'state' => $this->state,
            'complement' => $this->complement,
            'country' => $this->country,
            'state_registration' => $this->state_registration,
            'is_default' => $this->is_default,
            'is_primary' => $this->is_default,
        ];
    }
}
