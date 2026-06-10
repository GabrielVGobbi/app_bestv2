import {
    useCreateTimelineComment,
    useDeleteTimelineComment,
    useReplyTimelineComment,
    useUpdateTimelineComment,
} from '../api/mutations';
import type { ModelId } from '../api/types';

export function useCommentMutations(modelType: string, modelId: ModelId) {
    return {
        create: useCreateTimelineComment(modelType, modelId),
        reply: useReplyTimelineComment(modelType, modelId),
        update: useUpdateTimelineComment(modelType, modelId),
        remove: useDeleteTimelineComment(modelType, modelId),
    };
}
