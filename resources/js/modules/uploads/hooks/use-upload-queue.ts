import { useQueryClient } from '@tanstack/react-query';
import type { AxiosProgressEvent } from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePrivateState } from '@/contexts/private-state-context';

import { getErrorMessage, http, toApiError } from '@/lib/http';

import { uploadsEndpoints } from '../api/endpoints';
import { uploadsKeys } from '../api/keys';
import type { ModelId, Upload } from '../api/types';

export type UploadTaskStatus = 'pending' | 'uploading' | 'success' | 'error';

export interface UploadTask {
    id: string;
    file: File;
    status: UploadTaskStatus;
    progress: number;
    error?: string;
}

export interface UseUploadQueueOptions {
    modelType: string;
    modelId: ModelId;
    tag?: string | null;
    defaultTag?: string | null;
    collection?: string | null;
    concurrency?: number;
    successTtl?: number;
}

export interface UseUploadQueueReturn {
    items: UploadTask[];
    enqueue: (files: File[]) => void;
    enqueueRejected: (rejections: { file: File; reason: string }[]) => void;
    retry: (id: string) => void;
    cancel: (id: string) => void;
    clearErrors: () => void;
    activeCount: number;
    hasActive: boolean;
    hasErrors: boolean;
}

let taskSeed = 0;
function nextTaskId(): string {
    taskSeed += 1;

    return `uploadtask-${Date.now().toString(36)}-${taskSeed}`;
}

function isLikelyFolder(file: File): boolean {
    return file.size === 0 && file.type === '';
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
    const { scope } = usePrivateState();
    const queryClient = useQueryClient();
    const [tasks, setTasks] = useState<Record<string, UploadTask>>({});
    const abortersRef = useRef<Map<string, AbortController>>(new Map());
    const runningRef = useRef<Set<string>>(new Set());
    const timersRef = useRef<Set<ReturnType<typeof setTimeout>>>(new Set());
    const mounted = useRef(false);
    const isActive = useCallback(
        () => mounted.current && scope.isCurrent(),
        [scope],
    );
    useEffect(() => {
        mounted.current = true;
        const aborters = abortersRef.current;
        const running = runningRef.current;
        const timers = timersRef.current;

        return () => {
            mounted.current = false;
            aborters.forEach((controller) => controller.abort());
            aborters.clear();
            running.clear();
            timers.forEach(clearTimeout);
            timers.clear();
        };
    }, []);

    const paramsRef = useRef({
        modelType,
        modelId,
        tag,
        defaultTag,
        collection,
    });
    const successTtlRef = useRef(successTtl);

    useEffect(() => {
        paramsRef.current = { modelType, modelId, tag, defaultTag, collection };
        successTtlRef.current = successTtl;
    }, [modelType, modelId, tag, defaultTag, collection, successTtl]);

    const patchTask = useCallback(
        (id: string, patch: Partial<UploadTask>) => {
            if (!isActive()) {
                return;
            }

            setTasks((prev) => {
                if (!isActive()) {
                    return prev;
                }

                const current = prev[id];

                if (!current) {
                    return prev;
                }

                return { ...prev, [id]: { ...current, ...patch } };
            });
        },
        [isActive],
    );

    const removeTask = useCallback(
        (id: string) => {
            abortersRef.current.delete(id);
            runningRef.current.delete(id);

            if (!isActive()) {
                return;
            }

            setTasks((prev) => {
                if (!(id in prev)) {
                    return prev;
                }

                const next = { ...prev };
                delete next[id];

                return next;
            });
        },
        [isActive],
    );

    const startUpload = useCallback(
        async (task: UploadTask) => {
            const { id, file } = task;

            if (!isActive()) {
                return;
            }

            if (runningRef.current.has(id)) {
                return;
            }

            runningRef.current.add(id);
            patchTask(id, {
                status: 'uploading',
                progress: 0,
                error: undefined,
            });

            const params = paramsRef.current;
            const form = new FormData();
            form.append('parentable_type', params.modelType);
            form.append('parentable_id', String(params.modelId));
            form.append('files[]', file);
            const uploadTag = params.tag ?? params.defaultTag;

            if (uploadTag) {
                form.append('tag', uploadTag);
            }

            if (params.collection) {
                form.append('collection', params.collection);
            }

            const controller = new AbortController();
            abortersRef.current.set(id, controller);

            try {
                const res = await http.post<{
                    success: boolean;
                    message: string;
                    data: Upload[];
                }>(uploadsEndpoints.store(), form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    signal: controller.signal,
                    onUploadProgress: (event: AxiosProgressEvent) => {
                        if (!event.total) {
                            return;
                        }

                        const pct = Math.min(
                            99,
                            Math.round((event.loaded / event.total) * 100),
                        );
                        patchTask(id, { progress: pct });
                    },
                });

                if (!isActive()) {
                    return;
                }

                const uploaded = res.data?.data ?? [];

                if (uploaded.length > 0) {
                    await queryClient.invalidateQueries({
                        queryKey: uploadsKeys.lists(
                            params.modelType,
                            params.modelId,
                        ),
                    });

                    if (!isActive()) {
                        return;
                    }
                }

                patchTask(id, { status: 'success', progress: 100 });
                const timer = setTimeout(() => {
                    timersRef.current.delete(timer);

                    if (isActive()) {
                        removeTask(id);
                    }
                }, successTtlRef.current);
                timersRef.current.add(timer);
            } catch (err) {
                if (!isActive()) {
                    return;
                }

                const errorName = (err as { name?: string } | null)?.name;
                const errorCode = (err as { code?: string } | null)?.code;

                if (
                    errorName === 'CanceledError' ||
                    errorCode === 'ERR_CANCELED' ||
                    toApiError(err).type === 'canceled'
                ) {
                    removeTask(id);

                    return;
                }

                const apiError = toApiError(err);
                patchTask(id, {
                    status: 'error',
                    error: getErrorMessage(apiError),
                });
            } finally {
                runningRef.current.delete(id);
                abortersRef.current.delete(id);
            }
        },
        [patchTask, removeTask, queryClient, isActive],
    );

    useEffect(() => {
        if (!isActive()) {
            return;
        }

        const list = Object.values(tasks);
        const activeIds = list
            .filter((t) => t.status === 'uploading')
            .map((t) => t.id);

        for (const rid of Array.from(runningRef.current)) {
            if (!activeIds.includes(rid)) {
                runningRef.current.delete(rid);
            }
        }

        let slots = Math.max(0, concurrency - runningRef.current.size);

        if (slots === 0) {
            return;
        }

        const pendings = list.filter((t) => t.status === 'pending');

        for (const task of pendings) {
            if (slots === 0) {
                break;
            }

            if (runningRef.current.has(task.id)) {
                continue;
            }

            slots -= 1;
            void startUpload(task);
        }
    }, [tasks, concurrency, startUpload, isActive]);

    const enqueue = useCallback(
        (files: File[]) => {
            if (!isActive()) {
                return;
            }

            if (files.length === 0) {
                return;
            }

            const additions: Record<string, UploadTask> = {};

            for (const file of files) {
                const id = nextTaskId();
                const folder = isLikelyFolder(file);
                additions[id] = {
                    id,
                    file,
                    status: folder ? 'error' : 'pending',
                    progress: 0,
                    ...(folder
                        ? {
                              error: 'Pastas não são suportadas: envie apenas arquivos.',
                          }
                        : {}),
                };
            }

            setTasks((prev) => ({ ...prev, ...additions }));
        },
        [isActive],
    );

    const enqueueRejected = useCallback(
        (rejections: { file: File; reason: string }[]) => {
            if (!isActive()) {
                return;
            }

            if (rejections.length === 0) {
                return;
            }

            const additions: Record<string, UploadTask> = {};

            for (const { file, reason } of rejections) {
                const id = nextTaskId();
                additions[id] = {
                    id,
                    file,
                    status: 'error',
                    progress: 0,
                    error: reason,
                };
            }

            setTasks((prev) => ({ ...prev, ...additions }));
        },
        [isActive],
    );

    const retry = useCallback(
        (id: string) => {
            if (!isActive()) {
                return;
            }

            setTasks((prev) => {
                const current = prev[id];

                if (!current) {
                    return prev;
                }

                return {
                    ...prev,
                    [id]: {
                        ...current,
                        status: 'pending',
                        progress: 0,
                        error: undefined,
                    },
                };
            });
        },
        [isActive],
    );

    const cancel = useCallback(
        (id: string) => {
            abortersRef.current.get(id)?.abort();
            removeTask(id);
        },
        [removeTask],
    );

    const clearErrors = useCallback(() => {
        if (!isActive()) {
            return;
        }

        setTasks((prev) => {
            const next: Record<string, UploadTask> = {};

            for (const [id, task] of Object.entries(prev)) {
                if (task.status === 'error') {
                    continue;
                }

                next[id] = task;
            }

            return next;
        });
    }, [isActive]);

    const items = useMemo(() => {
        return Object.values(tasks).sort((a, b) => {
            const order: Record<UploadTaskStatus, number> = {
                uploading: 0,
                pending: 1,
                error: 2,
                success: 3,
            };

            return order[a.status] - order[b.status];
        });
    }, [tasks]);

    const activeCount = useMemo(
        () =>
            items.filter(
                (t) => t.status === 'uploading' || t.status === 'pending',
            ).length,
        [items],
    );

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
    };
}
