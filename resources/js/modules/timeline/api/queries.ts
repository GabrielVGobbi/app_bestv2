import { queryOptions } from '@tanstack/react-query';

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
        queryFn: () =>
            httpGet<TimelineResponse>(timelineEndpoints.show(modelType, modelId), {
                params: { limit: pageSize },
            }),
    });
}
