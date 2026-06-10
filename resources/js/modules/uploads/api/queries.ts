import { queryOptions } from '@tanstack/react-query'

import { httpGet, type ApiResponse, type PaginatedResponse } from '@/lib/http'

import { uploadsEndpoints } from './endpoints'
import { uploadsKeys } from './keys'
import type { ModelId, TagSuggestion, Upload, UploadFilters } from './types'

export function uploadsListOptions(
	modelType: string,
	modelId: ModelId,
	filters?: UploadFilters,
) {
	return queryOptions({
		queryKey: uploadsKeys.list(modelType, modelId, filters),
		queryFn: () =>
			httpGet<PaginatedResponse<Upload>>(uploadsEndpoints.list(), {
				params: {
					parentable_type: modelType,
					parentable_id: modelId,
					...filters,
				},
			}),
	})
}

export function uploadTagsOptions(modelType: string) {
	return queryOptions({
		queryKey: uploadsKeys.tags(modelType),
		queryFn: () =>
			httpGet<ApiResponse<TagSuggestion[]>>(uploadsEndpoints.tags(), {
				params: { parentable_type: modelType },
			}),
	})
}
