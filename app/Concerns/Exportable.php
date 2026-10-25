<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

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

    /** @return Builder<static> */
    public static function authorizedExportQuery(User $actor): Builder
    {
        $allowedModels = config('exports.allowed_models', []);
        if (! is_array($allowedModels) || ! in_array(static::class, $allowedModels, true)) {
            throw new AuthorizationException;
        }

        Gate::forUser($actor)->authorize('viewAny', static::class);

        return static::applyExportScope($actor, static::query());
    }

    /**
     * Models narrow the export to what the actor may see; the default exports every row
     * the policy's viewAny already allowed.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected static function applyExportScope(User $actor, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a query for exporting, leveraging the existing Searchable scopes.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<static>
     */
    public static function buildExportQuery(
        User $actor,
        array $filters,
        string $search,
        string $sort,
        string $direction,
    ): Builder {
        $query = static::authorizedExportQuery($actor)
            ->search($search ?: null)
            ->apiFilters($filters)
            ->apiSort($sort ?: null, $direction ?: null);

        return $query->orderBy($query->getModel()->getQualifiedKeyName());
    }
}
