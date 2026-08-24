<?php

declare(strict_types=1);

namespace App\Modules\Finance\Concerns;

use App\Modules\Clients\Models\Client;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Traduz o filtro de contraparte no formato `FQCN|id` usado pelo DataTable.
 *
 * O model que usa esta trait precisa expor `applyBaseApiFilterOperator` como
 * alias do metodo original de `Searchable`:
 *
 * use Searchable, FiltersCounterparty {
 *     Searchable::applyApiFilterOperator as protected applyBaseApiFilterOperator;
 *     FiltersCounterparty::applyApiFilterOperator insteadof Searchable;
 * }
 */
trait FiltersCounterparty
{
    protected function applyApiFilterOperator(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($column === 'counterparty') {
            $this->applyCounterpartyFilter($query, $value);

            return;
        }

        $this->applyBaseApiFilterOperator($query, $column, $operator, $value);
    }

    /**
     * @return array<int, class-string>
     */
    protected function allowedCounterpartyTypes(): array
    {
        return [Client::class, Supplier::class];
    }

    private function applyCounterpartyFilter(Builder $query, mixed $value): void
    {
        if (! is_string($value) || ! str_contains($value, '|')) {
            return;
        }

        [$type, $id] = explode('|', $value, 2);

        if (! in_array($type, $this->allowedCounterpartyTypes(), true) || ! ctype_digit($id)) {
            return;
        }

        $query
            ->where('counterparty_type', $type)
            ->where('counterparty_id', (int) $id);
    }
}
