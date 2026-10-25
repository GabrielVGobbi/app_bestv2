import { useMemo } from 'react';

import { isActiveFilterValue } from '../core/filter-utils';
import type {
    DataTableFilterConfig,
    DataTableFilterValue,
    DataTableQuickFilter,
} from '../core/types';

export function useDataTableFilters(
    filters: Array<DataTableFilterConfig | DataTableQuickFilter>,
    values: Record<string, DataTableFilterValue>,
) {
    return useMemo(() => {
        const activeFilters = filters.filter((filter) =>
            isActiveFilterValue(values[filter.id]),
        );

        return {
            activeFilters,
            activeCount: activeFilters.length,
        };
    }, [filters, values]);
}
