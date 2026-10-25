import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { UseMutationResult } from '@tanstack/react-query';

import { toast } from 'sonner';
import { usePrivateState } from '@/contexts/private-state-context';
import type { ApiResponse } from '@/lib/http';
import {
    http,
    httpDelete,
    httpPost,
    httpPut,
    toApiError,
    getErrorMessage,
} from '@/lib/http';
import { canceledPrivateRequest } from '@/lib/http';

import { uploadsEndpoints } from './endpoints';
import { uploadsKeys } from './keys';
import type { ModelId, Upload, UploadUpdateInput } from './types';

export interface UploadFileVariables {
    files: File[];
    name?: string;
    tag?: string | null;
    collection?: string | null;
    isCover?: boolean;
}

export interface UploadFileResponse {
    success: boolean;
    message: string;
    data: Upload[];
}

export function useUploadFile(
    type: string,
    id: ModelId,
): UseMutationResult<UploadFileResponse, unknown, UploadFileVariables> {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        mutationFn: async (vars) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            const form = new FormData();
            form.append('parentable_type', type);
            form.append('parentable_id', String(id));
            vars.files.forEach((file) => form.append('files[]', file));

            if (vars.name) {
                form.append('name', vars.name);
            }

            if (vars.tag) {
                form.append('tag', vars.tag);
            }

            if (vars.collection) {
                form.append('collection', vars.collection);
            }

            if (vars.isCover) {
                form.append('is_cover', '1');
            }

            const res = await http.post<UploadFileResponse>(
                uploadsEndpoints.store(),
                form,
                {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    signal: scope.signal,
                },
            );

            return res.data;
        },
        onSuccess: async (response) => {
            if (!scope.isCurrent()) {
                return;
            }

            await queryClient.invalidateQueries({
                queryKey: uploadsKeys.lists(type, id),
            });

            if (scope.isCurrent()) {
                toast.success(response.message);
            }
        },
        onError: (error) => {
            if (!scope.isCurrent()) {
                return;
            }

            if (toApiError(error).type !== 'canceled') {
                toast.error(getErrorMessage(toApiError(error)));
            }
        },
    });
}

export interface UpdateUploadVariables {
    id: number;
    data: UploadUpdateInput;
}

export function useUpdateUpload(
    type: string,
    id: ModelId,
): UseMutationResult<ApiResponse<Upload>, unknown, UpdateUploadVariables> {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        mutationFn: (vars) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return httpPut<ApiResponse<Upload>>(
                uploadsEndpoints.update(vars.id),
                vars.data,
                { signal: scope.signal },
            );
        },
        onSuccess: async () => {
            if (!scope.isCurrent()) {
                return;
            }

            await queryClient.invalidateQueries({
                queryKey: uploadsKeys.lists(type, id),
            });
        },
        onError: (error) => {
            if (!scope.isCurrent()) {
                return;
            }

            if (toApiError(error).type !== 'canceled') {
                toast.error(getErrorMessage(toApiError(error)));
            }
        },
    });
}

export function useDeleteUpload(
    type: string,
    id: ModelId,
): UseMutationResult<ApiResponse<null>, unknown, number> {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        mutationFn: (uploadId) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return httpDelete<ApiResponse<null>>(
                uploadsEndpoints.destroy(uploadId),
                { signal: scope.signal },
            );
        },
        onSuccess: async () => {
            if (!scope.isCurrent()) {
                return;
            }

            await queryClient.invalidateQueries({
                queryKey: uploadsKeys.lists(type, id),
            });

            if (scope.isCurrent()) {
                toast.success('Arquivo excluído com sucesso.');
            }
        },
        onError: (error) => {
            if (!scope.isCurrent()) {
                return;
            }

            if (toApiError(error).type !== 'canceled') {
                toast.error(getErrorMessage(toApiError(error)));
            }
        },
    });
}

export function useSetCoverUpload(
    type: string,
    id: ModelId,
): UseMutationResult<ApiResponse<Upload>, unknown, number> {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        mutationFn: (uploadId) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return httpPost<ApiResponse<Upload>>(
                uploadsEndpoints.setCover(uploadId),
                {},
                { signal: scope.signal },
            );
        },
        onSuccess: async () => {
            if (!scope.isCurrent()) {
                return;
            }

            await queryClient.invalidateQueries({
                queryKey: uploadsKeys.lists(type, id),
            });

            if (scope.isCurrent()) {
                toast.success('Imagem de capa definida.');
            }
        },
        onError: (error) => {
            if (!scope.isCurrent()) {
                return;
            }

            if (toApiError(error).type !== 'canceled') {
                toast.error(getErrorMessage(toApiError(error)));
            }
        },
    });
}

export function useToggleFavoriteUpload(
    type: string,
    id: ModelId,
): UseMutationResult<ApiResponse<Upload>, unknown, number> {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        mutationFn: (uploadId) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return httpPost<ApiResponse<Upload>>(
                uploadsEndpoints.toggleFavorite(uploadId),
                {},
                { signal: scope.signal },
            );
        },
        onSuccess: async () => {
            if (!scope.isCurrent()) {
                return;
            }

            await queryClient.invalidateQueries({
                queryKey: uploadsKeys.lists(type, id),
            });
        },
        onError: (error) => {
            if (!scope.isCurrent()) {
                return;
            }

            if (toApiError(error).type !== 'canceled') {
                toast.error(getErrorMessage(toApiError(error)));
            }
        },
    });
}

export function useBulkDeleteUploads(
    type: string,
    id: ModelId,
): UseMutationResult<ApiResponse<null>, unknown, number[]> {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        mutationFn: async (ids) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            const res = await http.delete<ApiResponse<null>>(
                uploadsEndpoints.bulkDestroy(),
                { data: { ids }, signal: scope.signal },
            );

            return res.data;
        },
        onSuccess: async (_res, ids) => {
            if (!scope.isCurrent()) {
                return;
            }

            await queryClient.invalidateQueries({
                queryKey: uploadsKeys.lists(type, id),
            });

            if (!scope.isCurrent()) {
                return;
            }

            toast.success(
                ids.length === 1
                    ? 'Arquivo excluído.'
                    : `${ids.length} arquivos excluídos.`,
            );
        },
        onError: (error) => {
            if (!scope.isCurrent()) {
                return;
            }

            if (toApiError(error).type !== 'canceled') {
                toast.error(getErrorMessage(toApiError(error)));
            }
        },
    });
}
