import { infiniteQueryOptions, queryOptions } from '@tanstack/react-query';

import { httpGet } from '@/lib/http';

import { timelineEndpoints } from './endpoints';
import { timelineKeys } from './keys';
import type { ModelId, TimelineResponse } from './types';

export function timelineOptions(
    modelType: string,
    modelId: ModelId,
    pageSize = 50,
) {
    return queryOptions({
        queryKey: timelineKeys.show(modelType, modelId, pageSize),
        queryFn: ({ signal }) =>
            httpGet<TimelineResponse>(
                timelineEndpoints.show(modelType, modelId),
                {
                    signal,
                    params: { limit: pageSize },
                },
            ),
    });
}

export function timelineInfiniteOptions(
    modelType: string,
    modelId: ModelId,
    pageSize = 50,
) {
    return infiniteQueryOptions({
        queryKey: timelineKeys.infinite(modelType, modelId, pageSize),
        initialPageParam: null as string | null,
        queryFn: ({ signal, pageParam }) =>
            httpGet<TimelineResponse>(
                timelineEndpoints.show(modelType, modelId),
                {
                    signal,
                    params: {
                        limit: pageSize,
                        ...(pageParam === null ? {} : { cursor: pageParam }),
                    },
                },
            ),
        getNextPageParam: (page) => page.meta.next_cursor ?? undefined,
    });
}
