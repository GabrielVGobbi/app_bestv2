import { useQueryClient } from '@tanstack/react-query'
import type { AxiosProgressEvent } from 'axios'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'

import {
	getErrorMessage,
	http,
	toApiError,
	type PaginatedResponse,
} from '@/lib/http'

import { uploadsEndpoints } from '../api/endpoints'
import { uploadsKeys } from '../api/keys'
import type { ModelId, Upload } from '../api/types'

export type UploadTaskStatus = 'pending' | 'uploading' | 'success' | 'error'

export interface UploadTask {
	id: string
	file: File
	status: UploadTaskStatus
	progress: number
	error?: string
}

export interface UseUploadQueueOptions {
	modelType: string
	modelId: ModelId
	tag?: string | null
	defaultTag?: string | null
	collection?: string | null
	concurrency?: number
	successTtl?: number
}

export interface UseUploadQueueReturn {
	items: UploadTask[]
	enqueue: (files: File[]) => void
	enqueueRejected: (rejections: { file: File; reason: string }[]) => void
	retry: (id: string) => void
	cancel: (id: string) => void
	clearErrors: () => void
	activeCount: number
	hasActive: boolean
	hasErrors: boolean
}

let taskSeed = 0
function nextTaskId(): string {
	taskSeed += 1
	return `uploadtask-${Date.now().toString(36)}-${taskSeed}`
}

function isLikelyFolder(file: File): boolean {
	return file.size === 0 && file.type === ''
}

export function useUploadQueue({
	modelType,
	modelId,
	tag = null,
	defaultTag = null,
	collection = null,
	concurrency = 3,
	successTtl = 700,
}: UseUploadQueueOptions): UseUploadQueueReturn {
	const queryClient = useQueryClient()
	const [tasks, setTasks] = useState<Record<string, UploadTask>>({})
	const filesRef = useRef<Map<string, File>>(new Map())
	const abortersRef = useRef<Map<string, AbortController>>(new Map())
	const runningRef = useRef<Set<string>>(new Set())

	const paramsRef = useRef({ modelType, modelId, tag, defaultTag, collection })
	paramsRef.current = { modelType, modelId, tag, defaultTag, collection }
	const successTtlRef = useRef(successTtl)
	successTtlRef.current = successTtl

	const patchTask = useCallback((id: string, patch: Partial<UploadTask>) => {
		setTasks((prev) => {
			const current = prev[id]
			if (!current) return prev
			return { ...prev, [id]: { ...current, ...patch } }
		})
	}, [])

	const removeTask = useCallback((id: string) => {
		filesRef.current.delete(id)
		abortersRef.current.delete(id)
		runningRef.current.delete(id)
		setTasks((prev) => {
			if (!(id in prev)) return prev
			const next = { ...prev }
			delete next[id]
			return next
		})
	}, [])

	const startUpload = useCallback(
		async (id: string) => {
			if (runningRef.current.has(id)) return
			const file = filesRef.current.get(id)
			if (!file) {
				runningRef.current.delete(id)
				return
			}

			runningRef.current.add(id)
			patchTask(id, { status: 'uploading', progress: 0, error: undefined })

			const params = paramsRef.current
			const form = new FormData()
			form.append('parentable_type', params.modelType)
			form.append('parentable_id', String(params.modelId))
			form.append('files[]', file)
			const uploadTag = params.tag ?? params.defaultTag
			if (uploadTag) form.append('tag', uploadTag)
			if (params.collection) form.append('collection', params.collection)

			const controller = new AbortController()
			abortersRef.current.set(id, controller)

			try {
				const res = await http.post<{
					success: boolean
					message: string
					data: Upload[]
				}>(uploadsEndpoints.store(), form, {
					headers: { 'Content-Type': 'multipart/form-data' },
					signal: controller.signal,
					onUploadProgress: (event: AxiosProgressEvent) => {
						if (!event.total) return
						const pct = Math.min(
							99,
							Math.round((event.loaded / event.total) * 100),
						)
						patchTask(id, { progress: pct })
					},
				})

				const uploaded = res.data?.data ?? []

				if (uploaded.length > 0) {
					queryClient.setQueriesData<PaginatedResponse<Upload>>(
						{ queryKey: uploadsKeys.list(params.modelType, params.modelId) },
						(old) => {
							if (!old || !Array.isArray(old.data)) return old
							const existing = new Set(old.data.map((u) => u.id))
							const fresh = uploaded.filter((u) => !existing.has(u.id))
							if (fresh.length === 0) return old
							return { ...old, data: [...fresh, ...old.data] }
						},
					)
				}

				patchTask(id, { status: 'success', progress: 100 })
				setTimeout(() => removeTask(id), successTtlRef.current)
			} catch (err) {
				const errorName = (err as { name?: string } | null)?.name
				const errorCode = (err as { code?: string } | null)?.code
				if (errorName === 'CanceledError' || errorCode === 'ERR_CANCELED') {
					removeTask(id)
					return
				}
				const apiError = toApiError(err)
				patchTask(id, {
					status: 'error',
					error: getErrorMessage(apiError),
				})
			} finally {
				runningRef.current.delete(id)
				abortersRef.current.delete(id)
			}
		},
		[patchTask, removeTask, queryClient],
	)

	useEffect(() => {
		const list = Object.values(tasks)
		const activeIds = list
			.filter((t) => t.status === 'uploading')
			.map((t) => t.id)

		for (const rid of Array.from(runningRef.current)) {
			if (!activeIds.includes(rid)) runningRef.current.delete(rid)
		}

		let slots = Math.max(0, concurrency - runningRef.current.size)
		if (slots === 0) return

		const pendings = list.filter((t) => t.status === 'pending')
		for (const task of pendings) {
			if (slots === 0) break
			if (runningRef.current.has(task.id)) continue
			slots -= 1
			void startUpload(task.id)
		}
	}, [tasks, concurrency, startUpload])

	const enqueue = useCallback((files: File[]) => {
		if (files.length === 0) return
		setTasks((prev) => {
			const next = { ...prev }
			for (const file of files) {
				const id = nextTaskId()
				if (isLikelyFolder(file)) {
					next[id] = {
						id,
						file,
						status: 'error',
						progress: 0,
						error: 'Pastas não são suportadas — envie apenas arquivos.',
					}
					continue
				}
				filesRef.current.set(id, file)
				next[id] = { id, file, status: 'pending', progress: 0 }
			}
			return next
		})
	}, [])

	const enqueueRejected = useCallback(
		(rejections: { file: File; reason: string }[]) => {
			if (rejections.length === 0) return
			setTasks((prev) => {
				const next = { ...prev }
				for (const { file, reason } of rejections) {
					const id = nextTaskId()
					next[id] = {
						id,
						file,
						status: 'error',
						progress: 0,
						error: reason,
					}
				}
				return next
			})
		},
		[],
	)

	const retry = useCallback((id: string) => {
		setTasks((prev) => {
			const current = prev[id]
			if (!current) return prev
			if (!filesRef.current.has(id)) {
				filesRef.current.set(id, current.file)
			}
			return {
				...prev,
				[id]: {
					...current,
					status: 'pending',
					progress: 0,
					error: undefined,
				},
			}
		})
	}, [])

	const cancel = useCallback(
		(id: string) => {
			abortersRef.current.get(id)?.abort()
			removeTask(id)
		},
		[removeTask],
	)

	const clearErrors = useCallback(() => {
		setTasks((prev) => {
			const next: Record<string, UploadTask> = {}
			for (const [id, task] of Object.entries(prev)) {
				if (task.status === 'error') {
					filesRef.current.delete(id)
					continue
				}
				next[id] = task
			}
			return next
		})
	}, [])

	const items = useMemo(() => {
		return Object.values(tasks).sort((a, b) => {
			const order: Record<UploadTaskStatus, number> = {
				uploading: 0,
				pending: 1,
				error: 2,
				success: 3,
			}
			return order[a.status] - order[b.status]
		})
	}, [tasks])

	const activeCount = useMemo(
		() =>
			items.filter(
				(t) => t.status === 'uploading' || t.status === 'pending',
			).length,
		[items],
	)

	return {
		items,
		enqueue,
		enqueueRejected,
		retry,
		cancel,
		clearErrors,
		activeCount,
		hasActive: activeCount > 0,
		hasErrors: items.some((t) => t.status === 'error'),
	}
}
