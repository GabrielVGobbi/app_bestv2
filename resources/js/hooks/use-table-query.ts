import { useQuery } from '@tanstack/react-query';
import { httpGet } from '@/lib/http';
import type { Paginated } from '@/types/pagination';

export interface TableQueryParams {
    page?: number;
    perPage?: number;
    search?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
}

export function useTableQuery<T>(
    queryKey: string,
    endpoint: string,
    params: TableQueryParams = {},
) {
    const { page = 1, perPage = 30, search, sort, direction } = params;

    return useQuery<Paginated<T>>({
        queryKey: [queryKey, { page, perPage, search, sort, direction }],
        queryFn: async () => {
            return httpGet<Paginated<T>>(endpoint, {
                params: {
                    page,
                    per_page: perPage,
                    search,
                    sort,
                    direction,
                },
            });
        },
        placeholderData: (prev) => prev,
    });
}
