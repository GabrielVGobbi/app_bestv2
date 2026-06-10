import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { getErrorMessage, httpDelete, httpPost, httpPut, toApiError } from '@/lib/http';

import { timelineEndpoints } from './endpoints';
import { timelineKeys } from './keys';
import type {
    CommentPayload,
    ModelId,
    TimelineCommentItem,
    TimelineMessageResponse,
    TimelineResponse,
} from './types';

interface CommentResourceResponse {
    data: TimelineCommentItem;
}

function normalizeCommentFromPayload(
    comment: TimelineCommentItem,
    payload: CommentPayload,
): TimelineCommentItem {
    return {
        ...comment,
        body_format: payload.body_format ?? comment.body_format ?? 'plain',
    };
}

function patchTimeline(
    old: TimelineResponse | undefined,
    patcher: (items: TimelineResponse['data']) => TimelineResponse['data'],
): TimelineResponse | undefined {
    if (!old) {
return old;
}

    return {
        ...old,
        data: patcher(old.data),
    };
}

export function useCreateTimelineComment(modelType: string, modelId: ModelId) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: CommentPayload) =>
            httpPost<CommentResourceResponse>(
                timelineEndpoints.comments(modelType, modelId),
                payload,
            ),
        onSuccess: (response, payload) => {
            const comment = normalizeCommentFromPayload(response.data, payload);

            queryClient.setQueriesData<TimelineResponse>(
                { queryKey: timelineKeys.subject(modelType, modelId) },
                (old) =>
                    patchTimeline(old, (items) => [...items, comment]),
            );
            toast.success('Comentario criado.');
        },
        onError: (error) => {
            toast.error(getErrorMessage(toApiError(error)));
        },
    });
}

export function useReplyTimelineComment(modelType: string, modelId: ModelId) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({
            commentId,
            payload,
        }: {
            commentId: number;
            payload: CommentPayload;
        }) =>
            httpPost<CommentResourceResponse>(
                timelineEndpoints.replies(commentId),
                payload,
            ),
        onSuccess: (response, variables) => {
            const comment = normalizeCommentFromPayload(
                response.data,
                variables.payload,
            );

            queryClient.setQueriesData<TimelineResponse>(
                { queryKey: timelineKeys.subject(modelType, modelId) },
                (old) =>
                    patchTimeline(old, (items) =>
                        items.map((item) => {
                            if (
                                item.type !== 'comment' ||
                                item.id !== variables.commentId
                            ) {
                                return item;
                            }

                            return {
                                ...item,
                                replies: [...(item.replies ?? []), comment],
                            };
                        }),
                    ),
            );
            toast.success('Resposta criada.');
        },
        onError: (error) => {
            toast.error(getErrorMessage(toApiError(error)));
        },
    });
}

export function useUpdateTimelineComment(modelType: string, modelId: ModelId) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({
            commentId,
            payload,
        }: {
            commentId: number;
            payload: CommentPayload;
        }) =>
            httpPut<CommentResourceResponse>(
                timelineEndpoints.comment(commentId),
                payload,
            ),
        onSuccess: (response, variables) => {
            const comment = normalizeCommentFromPayload(
                response.data,
                variables.payload,
            );

            queryClient.setQueriesData<TimelineResponse>(
                { queryKey: timelineKeys.subject(modelType, modelId) },
                (old) =>
                    patchTimeline(old, (items) =>
                        items.map((item) => {
                            if (item.type !== 'comment') {
                                return item;
                            }

                            if (item.id === comment.id) {
                                return comment;
                            }

                            return {
                                ...item,
                                replies: (item.replies ?? []).map((reply) =>
                                    reply.id === comment.id
                                        ? comment
                                        : reply,
                                ),
                            };
                        }),
                    ),
            );
            toast.success('Comentario atualizado.');
        },
        onError: (error) => {
            toast.error(getErrorMessage(toApiError(error)));
        },
    });
}

export function useDeleteTimelineComment(modelType: string, modelId: ModelId) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (commentId: number) =>
            httpDelete<TimelineMessageResponse>(
                timelineEndpoints.comment(commentId),
            ),
        onSuccess: (_response, commentId) => {
            queryClient.setQueriesData<TimelineResponse>(
                { queryKey: timelineKeys.subject(modelType, modelId) },
                (old) =>
                    patchTimeline(old, (items) =>
                        items
                            .filter(
                                (item) =>
                                    item.type !== 'comment' ||
                                    item.id !== commentId,
                            )
                            .map((item) => {
                                if (item.type !== 'comment') {
                                    return item;
                                }

                                return {
                                    ...item,
                                    replies: (item.replies ?? []).filter(
                                        (reply) => reply.id !== commentId,
                                    ),
                                };
                            }),
                    ),
            );
            toast.success('Comentario removido.');
        },
        onError: (error) => {
            toast.error(getErrorMessage(toApiError(error)));
        },
    });
}
