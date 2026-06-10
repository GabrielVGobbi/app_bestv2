<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait Searchable
{
    /**
     * @return array<int, string>
     */
    public function getSearchableColumns(): array
    {
        return $this->searchable ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function getFilterableColumns(): array
    {
        return $this->filterable ?? $this->getSearchableColumns();
    }

    /**
     * @return array<int, string>
     */
    public function getSortableColumns(): array
    {
        return $this->sortable ?? array_merge($this->getFilterableColumns(), [$this->getKeyName()]);
    }

    /**
     * @return array<int, string>
     */
    public function getApiIncludes(): array
    {
        return $this->apiIncludes ?? [];
    }

    public function getDefaultSortColumn(): string
    {
        return $this->defaultSortColumn ?? $this->getKeyName();
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection ?? 'desc';
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term || $this->getSearchableColumns() === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            foreach ($this->getSearchableColumns() as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeApiFilters(Builder $query, array $filters): Builder
    {
        foreach (Arr::only($filters, $this->getFilterableColumns()) as $column => $value) {
            $this->applyApiFilter($query, $column, $value);
        }

        return $query;
    }

    protected function applyApiFilter(Builder $query, string $column, mixed $filter): void
    {
        if ($filter === null || $filter === '') {
            return;
        }

        if (! is_array($filter) || ! array_key_exists('operator', $filter)) {
            $this->applyApiFilterOperator($query, $column, is_array($filter) ? 'in' : 'eq', $filter);

            return;
        }

        $operator = Str::lower((string) ($filter['operator'] ?? 'eq'));
        $value = $filter['value'] ?? null;

        $this->applyApiFilterOperator($query, $column, $operator, $value);
    }

    protected function applyApiFilterOperator(Builder $query, string $column, string $operator, mixed $value): void
    {
        match ($operator) {
            'contains' => $this->applyLikeFilter($query, $column, $value, 'both'),
            'starts_with' => $this->applyLikeFilter($query, $column, $value, 'right'),
            'in' => $this->applyInFilter($query, $column, $value),
            'between' => $this->applyBetweenFilter($query, $column, $value),
            'gte' => $this->applyComparisonFilter($query, $column, '>=', $value),
            'lte' => $this->applyComparisonFilter($query, $column, '<=', $value),
            'null' => $query->whereNull($column),
            'not_null' => $query->whereNotNull($column),
            default => $this->applyComparisonFilter($query, $column, '=', $value),
        };
    }

    protected function applyLikeFilter(Builder $query, string $column, mixed $value, string $mode): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $value = match ($mode) {
            'right' => "{$value}%",
            default => "%{$value}%",
        };

        $query->where($column, 'like', $value);
    }

    protected function applyInFilter(Builder $query, string $column, mixed $value): void
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);
        $values = array_values(array_filter($values, fn (mixed $item): bool => $item !== null && $item !== ''));

        if ($values === []) {
            return;
        }

        $query->whereIn($column, $values);
    }

    protected function applyBetweenFilter(Builder $query, string $column, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if ($from !== null && $from !== '') {
            $query->where($column, '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->where($column, '<=', $to);
        }
    }

    protected function applyComparisonFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query->where($column, $operator, $value);
    }

    public function scopeApiSort(Builder $query, ?string $column, ?string $direction = null): Builder
    {
        $column = $column ?: $this->getDefaultSortColumn();

        if (! in_array($column, $this->getSortableColumns(), true)) {
            $column = $this->getDefaultSortColumn();
        }

        $direction = Str::lower($direction ?: $this->getDefaultSortDirection());
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : $this->getDefaultSortDirection();

        return $query->orderBy($column, $direction);
    }
}
