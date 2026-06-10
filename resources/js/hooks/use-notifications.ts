import { useCallback, useEffect, useRef, useState } from 'react';

import { useNotificationBellContext } from '@/contexts/notification-bell-context';
import { notificationService } from '@/services/notification-service';
import type { Paginated, PaginationMeta } from '@/types';
import type { Notification, NotificationFilters } from '@/types/notification';

interface UseNotificationsReturn {
    notifications: Notification[];
    pagination: PaginationMeta | null;
    unreadCount: number;
    loading: boolean;
    error: string | null;
    refresh: () => void;
    markAsRead: (uuid: string) => Promise<void>;
    markAllAsRead: () => Promise<void>;
    deleteNotification: (uuid: string) => Promise<void>;
    executeAction: (uuid: string, actionType: string, payload?: Record<string, unknown>) => Promise<unknown>;
}

export function useNotifications(filters?: NotificationFilters): UseNotificationsReturn {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [pagination, setPagination] = useState<PaginationMeta | null>(null);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    const fetchAll = useCallback(async () => {
        abortRef.current?.abort();
        abortRef.current = new AbortController();

        setLoading(true);
        setError(null);

        try {
            const listResult = await notificationService.getNotifications(filters);
            const notificationList = listResult as Paginated<Notification> & { unread_count?: number };

            setNotifications(notificationList.data);
            setPagination(notificationList.meta);
            setUnreadCount(notificationList.unread_count ?? 0);
        } catch (err: unknown) {
            if ((err as { name?: string })?.name !== 'CanceledError') {
                setError('Não foi possível carregar as notificações.');
            }
        } finally {
            setLoading(false);
        }
    }, [JSON.stringify(filters)]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        void fetchAll();
        return () => abortRef.current?.abort();
    }, [fetchAll]);

    const markAsRead = useCallback(
        async (uuid: string) => {
            const target = notifications.find((n) => n.uuid === uuid);
            if (!target || target.is_read) return;
            await notificationService.markAsRead(uuid);
            setNotifications((prev) =>
                prev.map((n) => (n.uuid === uuid ? { ...n, is_read: true, read_at: new Date().toISOString() } : n)),
            );
            setUnreadCount((c) => Math.max(0, c - 1));
        },
        [notifications],
    );

    const markAllAsRead = useCallback(async () => {
        await notificationService.markAllAsRead();
        setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true, read_at: new Date().toISOString() })));
        setUnreadCount(0);
    }, []);

    const deleteNotification = useCallback(
        async (uuid: string) => {
            const target = notifications.find((n) => n.uuid === uuid);
            await notificationService.deleteNotification(uuid);
            setNotifications((prev) => prev.filter((n) => n.uuid !== uuid));
            if (target && !target.is_read) {
                setUnreadCount((c) => Math.max(0, c - 1));
            }
        },
        [notifications],
    );

    const executeAction = useCallback(async (uuid: string, actionType: string, payload?: Record<string, unknown>) => {
        const result = await notificationService.executeAction(uuid, actionType, payload);
        setNotifications((prev) => prev.map((n) => (n.uuid === uuid ? { ...n, action_status: 'executed' } : n)));
        return result;
    }, []);

    return {
        notifications,
        pagination,
        unreadCount,
        loading,
        error,
        refresh: fetchAll,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        executeAction,
    };
}

/**
 * Reads notification bell state from the shared NotificationBellProvider.
 * The provider must be mounted once in the app root (app.tsx) — never subscribe
 * to Echo or fetch here, so multiple <NotificationBell /> components stay in sync
 * and toasts fire exactly once per event.
 */
export function useNotificationBell() {
    return useNotificationBellContext();
}
