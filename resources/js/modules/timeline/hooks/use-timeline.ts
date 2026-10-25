import { useInfiniteQuery } from '@tanstack/react-query';
import { useMemo } from 'react';

import { timelineInfiniteOptions } from '../api/queries';
import type { ModelId } from '../api/types';

export function useTimeline(
    modelType: string,
    modelId: ModelId,
    pageSize = 50,
) {
    const query = useInfiniteQuery(
        timelineInfiniteOptions(modelType, modelId, pageSize),
    );
    const items = useMemo(
        () =>
            [...(query.data?.pages ?? [])]
                .reverse()
                .flatMap((page) => page.data),
        [query.data?.pages],
    );

    return { ...query, items, subject: query.data?.pages[0]?.subject };
}
