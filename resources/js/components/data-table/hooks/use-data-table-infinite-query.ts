import { useInfiniteQuery } from '@tanstack/react-query';

import { httpGet } from '@/lib/http';
import type { Paginated } from '@/types/pagination';

import { buildDataTableQueryParams } from '../core/query-params';
import type { DataTableFilterConfig, DataTableQuickFilter, DataTableState } from '../core/types';
import { useDebouncedValue } from './use-debounced-value';

export function useDataTableInfiniteQuery<TData>(args: {
    queryKey: string;
    endpoint: string;
    state: DataTableState;
    filters: Array<DataTableFilterConfig | DataTableQuickFilter>;
    enabled?: boolean;
}) {
    const debouncedSearch = useDebouncedValue(args.state.search);

    const baseParams = buildDataTableQueryParams({
        perPage: args.state.perPage,
        search: debouncedSearch,
        sorting: args.state.sorting,
        filters: args.state.filters,
        filterConfig: args.filters,
    });

    const query = useInfiniteQuery<Paginated<TData>, Error>({
        queryKey: [args.queryKey, 'infinite', baseParams],
        initialPageParam: 1,
        enabled: args.enabled ?? true,
        queryFn: ({ pageParam }) =>
            httpGet<Paginated<TData>>(args.endpoint, {
                params: {
                    ...baseParams,
                    page: pageParam,
                },
            }),
        getNextPageParam: (lastPage) => {
            if (lastPage.meta.current_page >= lastPage.meta.last_page) {
                return undefined;
            }

            return lastPage.meta.current_page + 1;
        },
    });

    const rows = query.data?.pages.flatMap((page) => page.data) ?? [];
    const lastPage = query.data?.pages.at(-1);

    return {
        data: lastPage,
        rows,
        meta: lastPage?.meta,
        isLoading: query.isLoading,
        isFetching: query.isFetching,
        isFetchingMore: query.isFetchingNextPage,
        hasMore: query.hasNextPage,
        error: query.error,
        fetchNextPage: () => {
            void query.fetchNextPage();
        },
        refresh: () => {
            void query.refetch();
        },
    };
}
