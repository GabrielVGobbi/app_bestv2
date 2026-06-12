import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';

import { apiRoutes } from '@/lib/api/api-routes';
import { httpGet } from '@/lib/http';
import type { ApiError } from '@/lib/http';
import type { Client, ClientResource } from '@/types/app/client';
import type { Paginated } from '@/types/pagination';

const CLIENT_CARD_PAGE_SIZE = 20;
const DEBOUNCE_DELAY = 250;

export function clientDetailQueryKey(uuid: string) {
    return ['clients', 'detail', uuid] as const;
}

function useDebouncedValue<TValue>(
    value: TValue,
    delay = DEBOUNCE_DELAY,
): TValue {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const timeout = window.setTimeout(
            () => setDebouncedValue(value),
            delay,
        );

        return () => window.clearTimeout(timeout);
    }, [delay, value]);

    return debouncedValue;
}

export function useClientCardList(search: string) {
    const debouncedSearch = useDebouncedValue(search);

    const query = useInfiniteQuery<Paginated<Client>, ApiError | Error>({
        queryKey: ['clients', 'cards', { search: debouncedSearch }],
        initialPageParam: 1,
        queryFn: ({ pageParam, signal }) =>
            httpGet<Paginated<Client>>(apiRoutes.clients.index, {
                params: {
                    page: pageParam,
                    per_page: CLIENT_CARD_PAGE_SIZE,
                    search: debouncedSearch || undefined,
                },
                signal,
            }),
        getNextPageParam: (lastPage) => {
            if (lastPage.meta.current_page >= lastPage.meta.last_page) {
                return undefined;
            }

            return lastPage.meta.current_page + 1;
        },
    });

    const clients = useMemo(
        () => query.data?.pages.flatMap((page) => page.data) ?? [],
        [query.data],
    );
    const lastPage = query.data?.pages.at(-1);

    return {
        clients,
        meta: lastPage?.meta,
        isLoading: query.isLoading,
        isFetching: query.isFetching,
        isFetchingNextPage: query.isFetchingNextPage,
        hasNextPage: query.hasNextPage,
        error: query.error,
        fetchNextPage: query.fetchNextPage,
        refetch: query.refetch,
    };
}

export function useClientDetails(uuid: string | null) {
    return useQuery<ClientResource, ApiError | Error>({
        queryKey: uuid
            ? clientDetailQueryKey(uuid)
            : ['clients', 'detail', 'empty'],
        enabled: uuid !== null,
        queryFn: ({ signal }) => {
            if (uuid === null) {
                throw new Error('Nenhum cliente selecionado.');
            }

            return httpGet<ClientResource>(apiRoutes.clients.show(uuid), {
                signal,
            });
        },
        staleTime: 30_000,
    });
}
