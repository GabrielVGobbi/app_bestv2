import { useQuery } from '@tanstack/react-query'

import {
	useDeleteUpload,
	useSetCoverUpload,
	useToggleFavoriteUpload,
	useUpdateUpload,
	useUploadFile,
} from '../api/mutations'
import { uploadsListOptions, uploadTagsOptions } from '../api/queries'
import type { ModelId, UploadFilters } from '../api/types'

export function useUploads(
	modelType: string,
	modelId: ModelId,
	filters?: UploadFilters,
) {
	const list = useQuery(uploadsListOptions(modelType, modelId, filters))
	const tags = useQuery(uploadTagsOptions(modelType))

	const upload = useUploadFile(modelType, modelId)
	const update = useUpdateUpload(modelType, modelId)
	const remove = useDeleteUpload(modelType, modelId)
	const setCover = useSetCoverUpload(modelType, modelId)
	const toggleFavorite = useToggleFavoriteUpload(modelType, modelId)

	return { list, tags, upload, update, remove, setCover, toggleFavorite }
}

export type UseUploadsReturn = ReturnType<typeof useUploads>
