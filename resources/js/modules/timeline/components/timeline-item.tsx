import type { CommentPayload, TimelineItem as TimelineItemType } from '../api/types';
import { TimelineActivityItem } from './timeline-activity-item';
import { TimelineCommentItem } from './timeline-comment-item';

interface TimelineItemProps {
    item: TimelineItemType;
    readOnly?: boolean;
    allowAttachments?: boolean;
    onReply: (commentId: number, payload: CommentPayload) => Promise<void> | void;
    onUpdate: (commentId: number, payload: CommentPayload) => Promise<void> | void;
    onDelete: (commentId: number) => Promise<void> | void;
}

export function TimelineItem({
    item,
    readOnly = false,
    allowAttachments = true,
    onReply,
    onUpdate,
    onDelete,
}: TimelineItemProps) {
    if (item.type === 'activity') {
        return <TimelineActivityItem item={item} />;
    }

    return (
        <TimelineCommentItem
            item={item}
            readOnly={readOnly}
            allowAttachments={allowAttachments}
            onReply={onReply}
            onUpdate={onUpdate}
            onDelete={onDelete}
        />
    );
}
