import { useCallback, useEffect, useRef, useState } from 'react';

import { useNotificationBellContext } from '@/contexts/notification-bell-context';
import { usePrivateState } from '@/contexts/private-state-context';
import { toApiError } from '@/lib/http';
import { notificationService } from '@/services/notification-service';
import type { PaginationMeta } from '@/types';
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
    executeAction: (
        uuid: string,
        actionType: string,
        payload?: Record<string, unknown>,
    ) => Promise<unknown>;
}

export function useNotifications(
    filters?: NotificationFilters,
    onPageChange?: (page: number) => void,
): UseNotificationsReturn {
    const { scope } = usePrivateState();
    const {
        unreadCount,
        revision,
        isCurrentRevision,
        refresh,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        executeAction,
    } = useNotificationBellContext();
    const { type, category, read, per_page, page } = filters ?? {};
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [pagination, setPagination] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);
    const mounted = useRef(false);
    useEffect(() => {
        mounted.current = true;

        return () => {
            mounted.current = false;
            abortRef.current?.abort();
        };
    }, []);

    const fetchAll = useCallback(async () => {
        if (!scope.isCurrent()) {
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;
        const isCurrent = () =>
            scope.isCurrent() &&
            mounted.current &&
            !controller.signal.aborted &&
            abortRef.current === controller &&
            isCurrentRevision(revision);
        let redirected = false;

        setLoading(true);
        setError(null);

        try {
            let query: NotificationFilters = {
                type,
                category,
                read,
                per_page,
                page,
            };

            while (isCurrent()) {
                const result = await notificationService.getNotifications(
                    query,
                    {
                        signal: controller.signal,
                    },
                );

                if (!isCurrent()) {
                    return;
                }

                const lastPage = Math.max(1, result.meta.last_page);

                if (result.meta.current_page > lastPage) {
                    if (onPageChange) {
                        redirected = true;
                        onPageChange(lastPage);

                        return;
                    }

                    query = { ...query, page: lastPage };
                    continue;
                }

                setNotifications(result.data);
                setPagination(result.meta);

                return;
            }
        } catch (err: unknown) {
            if (isCurrent() && toApiError(err).type !== 'canceled') {
                setError('Não foi possível carregar as notificações.');
            }
        } finally {
            if (isCurrent() && !redirected) {
                setLoading(false);
            }
        }
    }, [
        scope,
        type,
        category,
        read,
        per_page,
        page,
        revision,
        isCurrentRevision,
        onPageChange,
    ]);

    useEffect(() => {
        let active = true;

        void Promise.resolve().then(() => {
            if (active) {
                void fetchAll();
            }
        });

        return () => {
            active = false;
            abortRef.current?.abort();
        };
    }, [fetchAll]);

    return {
        notifications,
        pagination,
        unreadCount,
        loading,
        error,
        refresh,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        executeAction,
    };
}

/**
 * Reads notification bell state from the shared NotificationBellProvider.
 * The provider must be mounted once in the private tree: never subscribe
 * to Echo or fetch here, so multiple <NotificationBell /> components stay in sync
 * and toasts fire exactly once per event.
 */
export function useNotificationBell() {
    return useNotificationBellContext();
}
