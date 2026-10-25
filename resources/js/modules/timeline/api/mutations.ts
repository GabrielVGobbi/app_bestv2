import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { usePrivateState } from '@/contexts/private-state-context';

import {
    canceledPrivateRequest,
    getErrorMessage,
    httpDelete,
    httpPost,
    httpPut,
    toApiError,
} from '@/lib/http';

import { timelineEndpoints } from './endpoints';
import { timelineKeys } from './keys';
import type {
    CommentPayload,
    ModelId,
    TimelineCommentItem,
    TimelineMessageResponse,
} from './types';

interface CommentResourceResponse {
    data: TimelineCommentItem;
}

export function useCreateTimelineComment(modelType: string, modelId: ModelId) {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        onMutate: () => ({
            subjectKey: timelineKeys.subject(modelType, modelId),
            scope,
        }),
        mutationFn: (payload: CommentPayload) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return httpPost<CommentResourceResponse>(
                timelineEndpoints.comments(modelType, modelId),
                payload,
                { signal: scope.signal },
            );
        },
        onSuccess: (_response, _payload, origin) => {
            if (!origin?.scope.isCurrent()) {
                return;
            }

            void queryClient.invalidateQueries({ queryKey: origin.subjectKey });
            toast.success('Comentario criado.');
        },
        onError: (error, _payload, origin) => {
            if (
                !(origin?.scope ?? scope).isCurrent() ||
                toApiError(error).type === 'canceled'
            ) {
                return;
            }

            toast.error(getErrorMessage(toApiError(error)));
        },
    });
}

export function useReplyTimelineComment(modelType: string, modelId: ModelId) {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        onMutate: () => ({
            subjectKey: timelineKeys.subject(modelType, modelId),
            scope,
        }),
        mutationFn: ({
            commentId,
            payload,
        }: {
            commentId: number;
            payload: CommentPayload;
        }) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return httpPost<CommentResourceResponse>(
                timelineEndpoints.replies(commentId),
                payload,
                { signal: scope.signal },
            );
        },
        onSuccess: (_response, _variables, origin) => {
            if (!origin?.scope.isCurrent()) {
                return;
            }

            void queryClient.invalidateQueries({ queryKey: origin.subjectKey });
            toast.success('Resposta criada.');
        },
        onError: (error, _variables, origin) => {
            if (
                !(origin?.scope ?? scope).isCurrent() ||
                toApiError(error).type === 'canceled'
            ) {
                return;
            }

            toast.error(getErrorMessage(toApiError(error)));
        },
    });
}

export function useUpdateTimelineComment(modelType: string, modelId: ModelId) {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        onMutate: () => ({
            subjectKey: timelineKeys.subject(modelType, modelId),
            scope,
        }),
        mutationFn: ({
            commentId,
            payload,
        }: {
            commentId: number;
            payload: CommentPayload;
        }) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return httpPut<CommentResourceResponse>(
                timelineEndpoints.comment(commentId),
                payload,
                { signal: scope.signal },
            );
        },
        onSuccess: (_response, _variables, origin) => {
            if (!origin?.scope.isCurrent()) {
                return;
            }

            void queryClient.invalidateQueries({ queryKey: origin.subjectKey });
            toast.success('Comentario atualizado.');
        },
        onError: (error, _variables, origin) => {
            if (
                !(origin?.scope ?? scope).isCurrent() ||
                toApiError(error).type === 'canceled'
            ) {
                return;
            }

            toast.error(getErrorMessage(toApiError(error)));
        },
    });
}

export function useDeleteTimelineComment(modelType: string, modelId: ModelId) {
    const queryClient = useQueryClient();
    const { scope } = usePrivateState();

    return useMutation({
        onMutate: () => ({
            subjectKey: timelineKeys.subject(modelType, modelId),
            scope,
        }),
        mutationFn: (commentId: number) => {
            if (!scope.isCurrent()) {
                throw canceledPrivateRequest();
            }

            return httpDelete<TimelineMessageResponse>(
                timelineEndpoints.comment(commentId),
                { signal: scope.signal },
            );
        },
        onSuccess: (_response, _commentId, origin) => {
            if (!origin?.scope.isCurrent()) {
                return;
            }

            void queryClient.invalidateQueries({ queryKey: origin.subjectKey });
            toast.success('Comentario removido.');
        },
        onError: (error, _commentId, origin) => {
            if (
                !(origin?.scope ?? scope).isCurrent() ||
                toApiError(error).type === 'canceled'
            ) {
                return;
            }

            toast.error(getErrorMessage(toApiError(error)));
        },
    });
}
