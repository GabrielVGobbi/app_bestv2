import type {
    CommentPayload,
    TimelineItem as TimelineItemType,
} from '../api/types';
import type { TimelineDayGroup as TimelineDayGroupType } from '../lib/group-timeline-items';
import { TimelineItem } from './timeline-item';

interface TimelineDayGroupProps {
    group: TimelineDayGroupType;
    readOnly?: boolean;
    allowAttachments?: boolean;
    onReply: (
        commentId: number,
        payload: CommentPayload,
    ) => Promise<void> | void;
    onUpdate: (
        commentId: number,
        payload: CommentPayload,
    ) => Promise<void> | void;
    onDelete: (commentId: number) => Promise<void> | void;
}

export function TimelineDayGroup({
    group,
    readOnly = false,
    allowAttachments = true,
    onReply,
    onUpdate,
    onDelete,
}: TimelineDayGroupProps) {
    return (
        <div className="space-y-3">
            <div className="flex items-center gap-3">
                <div className="h-px flex-1 bg-border/70" />
                <time className="shrink-0 rounded-full border border-border/70 bg-background px-2.5 py-0.5 text-[0.7rem] font-medium text-muted-foreground">
                    {group.label}
                </time>
                <div className="h-px flex-1 bg-border/70" />
            </div>
            <ul className="space-y-1.5" role="list">
                {group.items.map((item: TimelineItemType) => (
                    <li key={`${item.type}-${item.id}`}>
                        <TimelineItem
                            item={item}
                            readOnly={readOnly}
                            allowAttachments={allowAttachments}
                            onReply={onReply}
                            onUpdate={onUpdate}
                            onDelete={onDelete}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}
