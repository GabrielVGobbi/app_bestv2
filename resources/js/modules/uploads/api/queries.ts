import { queryOptions } from '@tanstack/react-query';

import { httpGet } from '@/lib/http';
import type { ApiResponse, PaginatedResponse } from '@/lib/http';

import { uploadsEndpoints } from './endpoints';
import { uploadsKeys } from './keys';
import type { ModelId, TagSuggestion, Upload, UploadFilters } from './types';

export function uploadsListOptions(
    modelType: string,
    modelId: ModelId,
    filters?: UploadFilters,
) {
    return queryOptions({
        queryKey: uploadsKeys.list(modelType, modelId, filters),
        queryFn: ({ signal }) =>
            httpGet<PaginatedResponse<Upload>>(uploadsEndpoints.list(), {
                signal,
                params: {
                    parentable_type: modelType,
                    parentable_id: modelId,
                    ...filters,
                },
            }),
    });
}

export function uploadTagsOptions(modelType: string) {
    return queryOptions({
        queryKey: uploadsKeys.tags(modelType),
        queryFn: ({ signal }) =>
            httpGet<ApiResponse<TagSuggestion[]>>(uploadsEndpoints.tags(), {
                signal,
                params: { parentable_type: modelType },
            }),
    });
}
