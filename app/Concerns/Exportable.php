<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Exportable
{
    /**
     * Get all exportable columns defined on the model.
     *
     * @return array<string, string>
     */
    public static function getExportableColumns(): array
    {
        return (new static)->exportable ?? [];
    }

    /**
     * Validate and resolve the requested columns against the exportable map.
     * Returns all columns if $requested is empty.
     *
     * @param  array<int, string>  $requested
     * @return array<string, string>
     */
    public static function resolveExportColumns(array $requested): array
    {
        $exportable = static::getExportableColumns();

        if (empty($requested)) {
            return $exportable;
        }

        return array_intersect_key($exportable, array_flip($requested));
    }

    /**
     * Build a query for exporting, leveraging the existing Searchable scopes.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function buildExportQuery(
        array $filters,
        string $search,
        string $sort,
        string $direction,
    ): Builder {
        return static::query()
            ->search($search ?: null)
            ->apiFilters($filters)
            ->apiSort($sort ?: null, $direction ?: null);
    }
}
