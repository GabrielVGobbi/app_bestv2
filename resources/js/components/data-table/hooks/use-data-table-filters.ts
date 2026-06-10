import { useMemo } from 'react';

import type { DataTableFilterConfig, DataTableFilterValue, DataTableQuickFilter } from '../core/types';
import { isActiveFilterValue } from '../core/filter-utils';

export function useDataTableFilters(
    filters: Array<DataTableFilterConfig | DataTableQuickFilter>,
    values: Record<string, DataTableFilterValue>,
) {
    return useMemo(() => {
        const activeFilters = filters.filter((filter) => isActiveFilterValue(values[filter.id]));

        return {
            activeFilters,
            activeCount: activeFilters.length,
        };
    }, [filters, values]);
}
