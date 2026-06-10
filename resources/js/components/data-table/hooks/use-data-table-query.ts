import { useQuery } from '@tanstack/react-query';

import { httpGet } from '@/lib/http';
import type { Paginated } from '@/types/pagination';

import { buildDataTableQueryParams } from '../core/query-params';
import type { DataTableFilterConfig, DataTableQuickFilter, DataTableState } from '../core/types';
import { useDebouncedValue } from './use-debounced-value';

export function useDataTableQuery<TData>(args: {
    queryKey: string;
    endpoint: string;
    state: DataTableState;
    filters: Array<DataTableFilterConfig | DataTableQuickFilter>;
    enabled?: boolean;
}) {
    const debouncedSearch = useDebouncedValue(args.state.search);

    const params = buildDataTableQueryParams({
        page: args.state.page,
        perPage: args.state.perPage,
        search: debouncedSearch,
        sorting: args.state.sorting,
        filters: args.state.filters,
        filterConfig: args.filters,
    });

    const query = useQuery<Paginated<TData>, Error>({
        queryKey: [args.queryKey, 'paginated', params],
        queryFn: () => httpGet<Paginated<TData>>(args.endpoint, { params }),
        enabled: args.enabled ?? true,
        placeholderData: (previousData) => previousData,
    });

    return {
        data: query.data,
        rows: query.data?.data ?? [],
        meta: query.data?.meta,
        isLoading: query.isLoading,
        isFetching: query.isFetching,
        error: query.error,
        refresh: () => {
            void query.refetch();
        },
    };
}
