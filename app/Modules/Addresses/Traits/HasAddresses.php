<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Traits;

use App\Modules\Addresses\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAddresses
{
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable')->latest();
    }

    public function defaultAddress(): ?Address
    {
        return $this->addresses()->where('is_default', true)->first();
    }
}
