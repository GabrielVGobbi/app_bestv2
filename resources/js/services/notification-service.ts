import * as NotificationApi from '@/actions/App/Http/Controllers/Api/NotificationApiController';
import { httpDelete, httpGet, httpPatch, httpPost } from '@/lib/http';
import type { HttpConfig } from '@/lib/http';
import type { Paginated } from '@/types';
import type {
    ExecuteActionResponse,
    MarkAllReadResponse,
    Notification,
    NotificationFilters,
    UnreadCountResponse,
    UnreadNotificationsResponse,
} from '@/types/notification';
import type { QueryParams } from '@/wayfinder';

export const notificationService = {
    getNotifications(
        filters?: NotificationFilters,
        config?: HttpConfig,
    ): Promise<Paginated<Notification>> {
        return httpGet<Paginated<Notification>>(
            NotificationApi.index.url({ query: filters as QueryParams }),
            config,
        );
    },

    getUnreadNotifications(
        config?: HttpConfig,
    ): Promise<UnreadNotificationsResponse> {
        return httpGet<UnreadNotificationsResponse>(
            NotificationApi.unread.url(),
            config,
        );
    },

    getUnreadCount(): Promise<UnreadCountResponse> {
        return httpGet<UnreadCountResponse>(NotificationApi.unreadCount.url());
    },

    markAsRead(notificationUuid: string): Promise<Notification> {
        return httpPatch<Notification, void>(
            NotificationApi.markAsRead.url({ notification: notificationUuid }),
        );
    },

    markAllAsRead(): Promise<MarkAllReadResponse> {
        return httpPost<MarkAllReadResponse, void>(
            NotificationApi.markAllAsRead.url(),
        );
    },

    deleteNotification(notificationUuid: string): Promise<void> {
        return httpDelete<void>(
            NotificationApi.destroy.url({ notification: notificationUuid }),
        );
    },

    executeAction(
        notificationUuid: string,
        actionType: string,
        payload?: Record<string, unknown>,
    ): Promise<ExecuteActionResponse> {
        return httpPost<
            ExecuteActionResponse,
            { action_type: string; payload?: Record<string, unknown> }
        >(
            NotificationApi.executeAction.url({
                notification: notificationUuid,
            }),
            { action_type: actionType, payload },
        );
    },
};
