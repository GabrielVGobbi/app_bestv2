import type { SortingState } from '@tanstack/react-table';

import { isActiveFilterValue } from './filter-utils';
import type {
    DataTableFilterConfig,
    DataTableFilterValue,
    DataTableQuickFilter,
} from './types';

function isRangeValue(
    value: DataTableFilterValue,
): value is { from?: string | number; to?: string | number } {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function defaultOperatorForFilter(
    filter: DataTableFilterConfig | DataTableQuickFilter,
    value: DataTableFilterValue,
) {
    if (
        'type' in filter &&
        filter.type === 'boolean' &&
        typeof value === 'boolean'
    ) {
        return value ? 'not_null' : 'null';
    }

    if (filter.operator) {
        return filter.operator;
    }

    if (Array.isArray(value)) {
        return 'in';
    }

    if ('type' in filter && filter.type === 'text') {
        return 'contains';
    }

    if (
        'type' in filter &&
        (filter.type === 'date-range' || filter.type === 'number-range')
    ) {
        return 'between';
    }

    return 'eq';
}

export function sortingToApiParams(
    sorting: SortingState,
): Partial<{ sort: string; direction: 'asc' | 'desc' }> {
    const firstSort = sorting[0];

    if (!firstSort) {
        return {};
    }

    return {
        sort: firstSort.id,
        direction: firstSort.desc ? 'desc' : 'asc',
    };
}

export function filtersToApiParams(
    values: Record<string, DataTableFilterValue>,
    filters: Array<DataTableFilterConfig | DataTableQuickFilter> = [],
): Record<string, unknown> {
    const params: Record<string, unknown> = {};

    for (const filter of filters) {
        const value = values[filter.id];

        if (!isActiveFilterValue(value)) {
            continue;
        }

        const operator = defaultOperatorForFilter(filter, value);

        if (
            operator === 'eq' &&
            !Array.isArray(value) &&
            !isRangeValue(value)
        ) {
            params[`filter[${filter.id}]`] = value;

            continue;
        }

        params[`filter[${filter.id}][operator]`] = operator;

        if (operator !== 'null' && operator !== 'not_null') {
            if (isRangeValue(value)) {
                // Flatten range objects into separate params so axios does not
                // JSON.stringify the object — PHP must receive proper array notation.
                if (value.from !== null && value.from !== undefined && value.from !== '') {
                    params[`filter[${filter.id}][value][from]`] = value.from;
                }
                if (value.to !== null && value.to !== undefined && value.to !== '') {
                    params[`filter[${filter.id}][value][to]`] = value.to;
                }
            } else {
                params[`filter[${filter.id}][value]`] = value;
            }
        }
    }

    return params;
}

export function buildDataTableQueryParams(args: {
    page?: number;
    perPage?: number;
    search?: string;
    sorting: SortingState;
    filters: Record<string, DataTableFilterValue>;
    filterConfig: Array<DataTableFilterConfig | DataTableQuickFilter>;
}): Record<string, unknown> {
    const sortParams = sortingToApiParams(args.sorting);

    return {
        page: args.page,
        per_page: args.perPage,
        search: args.search || undefined,
        ...sortParams,
        ...filtersToApiParams(args.filters, args.filterConfig),
    };
}
