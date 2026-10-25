import type { SortingState } from '@tanstack/react-table';

import { isActiveFilterValue } from './filter-utils';
import type {
    DataTableFilterConfig,
    DataTableFilterOperator,
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
): DataTableFilterOperator {
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

function selectedOptionForValue(
    filter: DataTableFilterConfig | DataTableQuickFilter,
    value: DataTableFilterValue,
) {
    if (
        Array.isArray(value) ||
        isRangeValue(value) ||
        value === null ||
        value === undefined
    ) {
        return undefined;
    }

    return filter.options?.find(
        (option) => String(option.value) === String(value),
    );
}

function resolveFilterPayload(
    filter: DataTableFilterConfig | DataTableQuickFilter,
    value: DataTableFilterValue,
): {
    operator: DataTableFilterOperator;
    payloadValue: DataTableFilterValue;
} {
    const selectedOption = selectedOptionForValue(filter, value);
    const payloadValue =
        selectedOption && 'filterValue' in selectedOption
            ? selectedOption.filterValue
            : value;

    return {
        operator:
            selectedOption?.operator ??
            defaultOperatorForFilter(filter, payloadValue),
        payloadValue,
    };
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

        const { operator, payloadValue } = resolveFilterPayload(filter, value);

        if (
            operator === 'eq' &&
            !Array.isArray(payloadValue) &&
            !isRangeValue(payloadValue)
        ) {
            params[`filter[${filter.id}]`] = payloadValue;

            continue;
        }

        params[`filter[${filter.id}][operator]`] = operator;

        if (operator !== 'null' && operator !== 'not_null') {
            if (isRangeValue(payloadValue)) {
                // Flatten range objects into separate params so axios does not
                // JSON.stringify the object: PHP must receive proper array notation.
                if (
                    payloadValue.from !== null &&
                    payloadValue.from !== undefined &&
                    payloadValue.from !== ''
                ) {
                    params[`filter[${filter.id}][value][from]`] =
                        payloadValue.from;
                }

                if (
                    payloadValue.to !== null &&
                    payloadValue.to !== undefined &&
                    payloadValue.to !== ''
                ) {
                    params[`filter[${filter.id}][value][to]`] = payloadValue.to;
                }
            } else {
                params[`filter[${filter.id}][value]`] = payloadValue;
            }
        }
    }

    return params;
}

export function filtersToApiPayload(
    values: Record<string, DataTableFilterValue>,
    filters: Array<DataTableFilterConfig | DataTableQuickFilter> = [],
): Record<string, unknown> {
    const params: Record<string, unknown> = {};

    for (const filter of filters) {
        const value = values[filter.id];

        if (!isActiveFilterValue(value)) {
            continue;
        }

        const { operator, payloadValue } = resolveFilterPayload(filter, value);

        if (operator === 'null' || operator === 'not_null') {
            params[filter.id] = {
                operator,
            };

            continue;
        }

        params[filter.id] = {
            operator,
            value: payloadValue,
        };
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
