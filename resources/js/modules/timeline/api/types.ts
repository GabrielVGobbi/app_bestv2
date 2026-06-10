import type { Upload } from '@/modules/uploads/api/types';

export type ModelId = string | number;

export type TimelineSeverity = 'muted' | 'info' | 'success' | 'warning' | 'danger';

export interface TimelineActor {
    id: number | null;
    name: string | null;
    avatar_url?: string | null;
}

export interface TimelineChange {
    field: string;
    label: string;
    old: string | null;
    new: string | null;
}

export interface TimelineActivityItem {
    type: 'activity';
    id: string;
    event: string;
    label: string | null;
    description: string;
    icon: string | null;
    severity: TimelineSeverity;
    causer: TimelineActor | null;
    changes: TimelineChange[];
    created_at: string;
}

export interface TimelineCommentPermissions {
    can_update: boolean;
    can_delete: boolean;
    can_reply: boolean;
}

export interface TimelineCommentItem {
    type: 'comment';
    id: number;
    uuid: string;
    body: string;
    body_format: 'plain' | 'html';
    author: TimelineActor;
    attachments?: Upload[];
    replies?: TimelineCommentItem[];
    permissions: TimelineCommentPermissions;
    edited_at: string | null;
    created_at: string;
}

export type TimelineItem = TimelineActivityItem | TimelineCommentItem;

export interface TimelineSubject {
    type: string;
    id: number | string;
    uuid: string | null;
    label: string;
}

export interface TimelineResponse {
    data: TimelineItem[];
    subject: TimelineSubject;
    meta: {
        next_cursor: string | null;
    };
}

export interface CommentPayload {
    body: string;
    body_format?: 'plain' | 'html';
}

export interface TimelineMessageResponse {
    message: string;
}
