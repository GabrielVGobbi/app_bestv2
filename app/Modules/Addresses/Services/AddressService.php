<?php

declare(strict_types=1);

namespace App\Modules\Addresses\Services;

use App\Modules\Addresses\Models\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddressService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Model $addressable, array $attributes): Address
    {
        return DB::transaction(function () use ($addressable, $attributes): Address {
            $attributes = $this->normalize($attributes);

            if (! $addressable->addresses()->exists()) {
                $attributes['is_default'] = true;
            }

            if (($attributes['is_default'] ?? false) === true) {
                $this->resetDefaultAddresses($addressable);
            }

            return $addressable->addresses()->create($attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreate(Model $addressable, array $attributes): Address
    {
        $attributes = $this->normalize($attributes);

        $existing = $addressable->addresses()
            ->where('zipcode', $attributes['zipcode'] ?? null)
            ->where('street', $attributes['street'] ?? null)
            ->where('number', $attributes['number'] ?? null)
            ->where('city', $attributes['city'] ?? null)
            ->where('state', $attributes['state'] ?? null)
            ->first();

        if ($existing instanceof Address) {
            if (($attributes['is_default'] ?? false) === true) {
                return $this->setDefault($existing);
            }

            return $existing;
        }

        return $this->create($addressable, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Address $address, array $attributes): Address
    {
        return DB::transaction(function () use ($address, $attributes): Address {
            $attributes = $this->normalize($attributes);

            if (($attributes['is_default'] ?? false) === true) {
                $this->resetDefaultAddressesForAddress($address);
            }

            $address->update($attributes);

            return $address->fresh();
        });
    }

    public function setDefault(Address $address): Address
    {
        return DB::transaction(function () use ($address): Address {
            $this->resetDefaultAddressesForAddress($address);

            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }

    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address): void {
            $wasDefault = $address->is_default;
            $addressable = $address->addressable;

            $address->delete();

            if ($wasDefault && $addressable !== null) {
                $addressable->addresses()
                    ->latest()
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }

    public function resolveAddressable(?string $alias, string|int|null $key): ?Model
    {
        if (blank($alias) || blank($key)) {
            return null;
        }

        $class = config("addresses.addressables.$alias");

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model $model */
        $model = new $class;
        $query = $model->newQuery();
        $columns = $this->lookupColumns($model);

        foreach ($columns as $index => $column) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $query->{$method}($column, $key);
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        if (array_key_exists('zipcode', $attributes)) {
            $attributes['zipcode'] = preg_replace('/\D/', '', (string) $attributes['zipcode']);
        }

        if (blank($attributes['neighborhood'] ?? null) && filled($attributes['district'] ?? null)) {
            $attributes['neighborhood'] = $attributes['district'];
        }

        if (blank($attributes['country'] ?? null)) {
            $attributes['country'] = (string) config('addresses.default_country', 'Brasil');
        }

        if (array_key_exists('is_default', $attributes)) {
            $attributes['is_default'] = filter_var($attributes['is_default'], FILTER_VALIDATE_BOOL);
        }

        return $attributes;
    }

    private function resetDefaultAddresses(Model $addressable): void
    {
        $addressable->addresses()->update(['is_default' => false]);
    }

    private function resetDefaultAddressesForAddress(Address $address): void
    {
        Address::query()
            ->where('addressable_type', $address->addressable_type)
            ->where('addressable_id', $address->addressable_id)
            ->update(['is_default' => false]);
    }

    /**
     * @return array<int, string>
     */
    private function lookupColumns(Model $model): array
    {
        $columns = collect((array) config('addresses.lookup_columns', ['uuid', 'id']))
            ->filter(fn ($column): bool => is_string($column) && Schema::hasColumn($model->getTable(), $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [$model->getKeyName()];
        }

        return $columns;
    }
}
