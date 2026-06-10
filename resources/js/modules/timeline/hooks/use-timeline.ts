import { useQuery } from '@tanstack/react-query';

import { timelineOptions } from '../api/queries';
import type { ModelId } from '../api/types';

export function useTimeline(modelType: string, modelId: ModelId, pageSize = 50) {
    return useQuery(timelineOptions(modelType, modelId, pageSize));
}
