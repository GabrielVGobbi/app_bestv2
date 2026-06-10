export type NotificationType =
    | 'system'
    | 'user'
    | 'mention'
    | 'social'
    | 'action'
    | 'warning'
    | 'error'
    | 'success'
    | 'info';

export type NotificationCategory =
    | 'general'
    | 'export'
    | 'import'
    | 'payment'
    | 'comment'
    | 'mention'
    | 'task'
    | 'post'
    | 'follow'
    | 'like'
    | 'message'
    | 'security'
    | 'account'
    | 'system';

export type NotificationActionVariant =
    | 'primary'
    | 'secondary'
    | 'destructive'
    | 'ghost'
    | 'outline'
    | 'success'
    | 'warning';

export type NotificationActionStatus =
    | null
    | 'pending'
    | 'executed'
    | 'rejected'
    | 'expired'
    | 'cancelled';

export interface NotificationAction {
    label: string;
    type: string;
    variant: NotificationActionVariant;
    requires_confirmation?: boolean;
    confirmation_message?: string;
}

export interface NotificationSender {
    id: number;
    name: string;
    uuid: string;
}

export interface Notification {
    id: number;
    uuid: string;
    type: NotificationType;
    type_label: string;
    category: NotificationCategory;
    category_label: string;
    title: string;
    body: string;
    link: string | null;
    actions: NotificationAction[] | null;
    action_status: NotificationActionStatus;
    is_read: boolean;
    read_at: string | null;
    created_at: string;
    sender: NotificationSender | null;
    data: Record<string, unknown> | null;
}

export interface NotificationFilters {
    type?: NotificationType;
    category?: NotificationCategory;
    read?: 'true' | 'false';
    per_page?: number;
    page?: number;
}

export interface UnreadCountResponse {
    count: number;
}

export interface UnreadNotificationsResponse {
    data: Notification[];
    unread_count: number;
}

export interface ExecuteActionResponse {
    action_type: string;
    result: Record<string, unknown>;
}

export interface MarkAllReadResponse {
    marked: number;
}
