import {
	useMutation,
	useQueryClient,
	type UseMutationResult,
} from '@tanstack/react-query'

import {
	http,
	httpDelete,
	httpPost,
	httpPut,
	toApiError,
	getErrorMessage,
	type ApiResponse,
	type PaginatedResponse,
} from '@/lib/http'
import { toast } from 'sonner'

import { uploadsEndpoints } from './endpoints'
import { uploadsKeys } from './keys'
import type { ModelId, Upload, UploadUpdateInput } from './types'

type Patcher = (list: Upload[]) => Upload[]

function setQueriesData(
	queryClient: ReturnType<typeof useQueryClient>,
	type: string,
	id: ModelId,
	patcher: Patcher,
): void {
	queryClient.setQueriesData<PaginatedResponse<Upload>>(
		{ queryKey: uploadsKeys.list(type, id) },
		(old) => {
			if (!old || !Array.isArray(old.data)) return old
			return { ...old, data: patcher(old.data) }
		},
	)
}

export interface UploadFileVariables {
	files: File[]
	name?: string
	tag?: string | null
	collection?: string | null
	isCover?: boolean
}

export interface UploadFileResponse {
	success: boolean
	message: string
	data: Upload[]
}

export function useUploadFile(
	type: string,
	id: ModelId,
): UseMutationResult<UploadFileResponse, unknown, UploadFileVariables> {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: async (vars) => {
			const form = new FormData()
			form.append('parentable_type', type)
			form.append('parentable_id', String(id))
			vars.files.forEach((file) => form.append('files[]', file))
			if (vars.name) form.append('name', vars.name)
			if (vars.tag) form.append('tag', vars.tag)
			if (vars.collection) form.append('collection', vars.collection)
			if (vars.isCover) form.append('is_cover', '1')

			const res = await http.post<UploadFileResponse>(
				uploadsEndpoints.store(),
				form,
				{ headers: { 'Content-Type': 'multipart/form-data' } },
			)
			return res.data
		},
		onSuccess: (response) => {
			setQueriesData(queryClient, type, id, (list) => {
				const existingIds = new Set(list.map((u) => u.id))
				const fresh = response.data.filter((u) => !existingIds.has(u.id))
				return [...fresh, ...list]
			})
			toast.success(response.message)
		},
		onError: (error) => {
			toast.error(getErrorMessage(toApiError(error)))
		},
	})
}

export interface UpdateUploadVariables {
	id: number
	data: UploadUpdateInput
}

interface UpdateContext {
	previous: Upload | undefined
}

export function useUpdateUpload(
	type: string,
	id: ModelId,
): UseMutationResult<ApiResponse<Upload>, unknown, UpdateUploadVariables, UpdateContext> {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (vars) =>
			httpPut<ApiResponse<Upload>>(
				uploadsEndpoints.update(vars.id),
				vars.data,
			),
		onMutate: async ({ id: uploadId, data }) => {
			await queryClient.cancelQueries({ queryKey: uploadsKeys.list(type, id) })
			let previous: Upload | undefined
			setQueriesData(queryClient, type, id, (list) =>
				list.map((item) => {
					if (item.id !== uploadId) return item
					previous = item
					return { ...item, ...data }
				}),
			)
			return { previous }
		},
		onSuccess: (response) => {
			setQueriesData(queryClient, type, id, (list) =>
				list.map((item) =>
					item.id === response.data.id ? response.data : item,
				),
			)
		},
		onError: (error, _vars, ctx) => {
			if (ctx?.previous) {
				setQueriesData(queryClient, type, id, (list) =>
					list.map((item) =>
						item.id === ctx.previous!.id ? ctx.previous! : item,
					),
				)
			}
			toast.error(getErrorMessage(toApiError(error)))
		},
	})
}

interface DeleteContext {
	previous: Upload[] | undefined
}

export function useDeleteUpload(
	type: string,
	id: ModelId,
): UseMutationResult<ApiResponse<null>, unknown, number, DeleteContext> {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (uploadId) =>
			httpDelete<ApiResponse<null>>(uploadsEndpoints.destroy(uploadId)),
		onMutate: async (uploadId) => {
			await queryClient.cancelQueries({ queryKey: uploadsKeys.list(type, id) })
			let previous: Upload[] | undefined
			setQueriesData(queryClient, type, id, (list) => {
				previous = list
				return list.filter((item) => item.id !== uploadId)
			})
			return { previous }
		},
		onSuccess: () => {
			toast.success('Arquivo excluído com sucesso.')
		},
		onError: (error, _id, ctx) => {
			if (ctx?.previous) {
				setQueriesData(queryClient, type, id, () => ctx.previous!)
			}
			toast.error(getErrorMessage(toApiError(error)))
		},
	})
}

export function useSetCoverUpload(
	type: string,
	id: ModelId,
): UseMutationResult<ApiResponse<Upload>, unknown, number> {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (uploadId) =>
			httpPost<ApiResponse<Upload>>(uploadsEndpoints.setCover(uploadId), {}),
		onSuccess: (response) => {
			setQueriesData(queryClient, type, id, (list) =>
				list.map((item) => ({
					...item,
					is_cover: item.id === response.data.id,
				})),
			)
			toast.success('Imagem de capa definida.')
		},
		onError: (error) => {
			toast.error(getErrorMessage(toApiError(error)))
		},
	})
}

export function useToggleFavoriteUpload(
	type: string,
	id: ModelId,
): UseMutationResult<ApiResponse<Upload>, unknown, number> {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (uploadId) =>
			httpPost<ApiResponse<Upload>>(
				uploadsEndpoints.toggleFavorite(uploadId),
				{},
			),
		onSuccess: (response) => {
			setQueriesData(queryClient, type, id, (list) =>
				list.map((item) =>
					item.id === response.data.id ? response.data : item,
				),
			)
		},
		onError: (error) => {
			toast.error(getErrorMessage(toApiError(error)))
		},
	})
}

interface BulkDeleteContext {
	previous: Upload[] | undefined
}

export function useBulkDeleteUploads(
	type: string,
	id: ModelId,
): UseMutationResult<ApiResponse<null>, unknown, number[], BulkDeleteContext> {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: async (ids) => {
			const res = await http.delete<ApiResponse<null>>(
				uploadsEndpoints.bulkDestroy(),
				{ data: { ids } },
			)
			return res.data
		},
		onMutate: async (ids) => {
			await queryClient.cancelQueries({ queryKey: uploadsKeys.list(type, id) })
			const idSet = new Set(ids)
			let previous: Upload[] | undefined
			setQueriesData(queryClient, type, id, (list) => {
				previous = list
				return list.filter((item) => !idSet.has(item.id))
			})
			return { previous }
		},
		onSuccess: (_res, ids) => {
			toast.success(
				ids.length === 1
					? 'Arquivo excluído.'
					: `${ids.length} arquivos excluídos.`,
			)
		},
		onError: (error, _ids, ctx) => {
			if (ctx?.previous) {
				setQueriesData(queryClient, type, id, () => ctx.previous!)
			}
			toast.error(getErrorMessage(toApiError(error)))
		},
	})
}
