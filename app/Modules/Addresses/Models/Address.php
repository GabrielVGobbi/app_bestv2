<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Models;

use App\Modules\Addresses\Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'street',
        'number',
        'district',
        'neighborhood',
        'city',
        'city_code',
        'state',
        'zipcode',
        'complement',
        'country',
        'is_default',
        'state_registration',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $appends = ['full_address'];

    protected static function booted(): void
    {
        static::creating(function (Address $address): void {
            if (blank($address->uuid)) {
                $address->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return AddressFactory::new();
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function fullAddress(): Attribute
    {
        return Attribute::get(function (): string {
            if (blank($this->street)) {
                return 'Endereço nao informado';
            }

            return collect([
                trim((string) $this->street).($this->number ? ', '.$this->number : ''),
                $this->complement,
                $this->district,
                trim(collect([$this->city, $this->state])->filter()->implode('/')),
                $this->zipcode,
                $this->country,
            ])->filter(fn ($part): bool => filled($part))->implode(' - ');
        });
    }
}
