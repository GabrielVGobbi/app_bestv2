<?php

namespace App\Supports\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasUuidIdentifier
{
    protected static function bootHasUuidIdentifier(): void
    {
        static::creating(function ($model) {
            $column = $model->getUuidColumnName();

            if (empty($model->{$column})) {
                $model->{$column} = (string) Str::uuid();
            }
        });
    }

    public function getUuidColumnName(): string
    {
        return property_exists($this, 'uuidColumn')
            ? $this->uuidColumn
            : 'uuid';
    }

    public function scopeWhereUuid(Builder $query, string $value): Builder
    {
        return $query->where($this->getUuidColumnName(), $value);
    }

    public function scopeByToken(Builder $query, string|int $id): Builder
    {
        $model = $query->getModel();
        $uuidColumn = method_exists($model, 'getUuidColumnName')
            ? $model->getUuidColumnName()
            : 'uuid';

        if (is_string($id) && Str::isUuid($id)) {
            return $query->where($uuidColumn, $id);
        }

        return $query->where($model->getKeyName(), $id);
    }

    public static function findByToken(string|int $id): ?static
    {
        return static::query()->byToken($id)->first();
    }

    public static function findByTokenOrFail(string|int $id): static
    {
        return static::query()->byToken($id)->firstOrFail();
    }

    public function getRouteKeyName(): string
    {
        return $this->getUuidColumnName();
    }
}
