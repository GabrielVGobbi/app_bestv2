import { router } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';

import echo from '@/lib/echo';
import { notificationService } from '@/services/notification-service';
import type { Notification } from '@/types/notification';

interface NotificationBellState {
    unreadCount: number;
    recentNotifications: Notification[];
    loading: boolean;
    refresh: () => void;
    markAsRead: (uuid: string) => Promise<void>;
    markAllAsRead: () => Promise<void>;
}

const NotificationBellContext = createContext<NotificationBellState | null>(null);

const TOAST_TYPES = new Set<Notification['type']>(['warning', 'error', 'system']);

function showRealtimeToast(notification: Notification): void {
    if (!TOAST_TYPES.has(notification.type)) {
return;
}

    const method = notification.type === 'error' ? 'error' : notification.type === 'warning' ? 'warning' : 'message';

    toast[method](notification.title, {
        description: notification.body,
        ...(notification.link
            ? { action: { label: 'Ver', onClick: () => window.location.assign(notification.link!) } }
            : {}),
    });
}

function readUserIdFromDom(): number | null {
    try {
        const el = document.getElementById('app');

        if (!el?.dataset.page) {
return null;
}

        const page = JSON.parse(el.dataset.page) as { props?: { auth?: { user?: { id?: number } } } };

        return page.props?.auth?.user?.id ?? null;
    } catch {
        return null;
    }
}

export function NotificationBellProvider({ children }: { children: ReactNode }) {
    const [userId, setUserId] = useState<number | null>(readUserIdFromDom);

    const [unreadCount, setUnreadCount] = useState(0);
    const [recentNotifications, setRecentNotifications] = useState<Notification[]>([]);
    const [loading, setLoading] = useState(false);
    const fetchedRef = useRef(false);

    // Keep userId in sync with Inertia page props across navigations
    useEffect(() => {
        return router.on('navigate', (event) => {
            const props = (event as CustomEvent).detail?.page?.props as
                | { auth?: { user?: { id?: number } } }
                | undefined;
            setUserId(props?.auth?.user?.id ?? null);
        });
    }, []);

    const refresh = useCallback(async () => {
        if (!userId) {
return;
}

        setLoading(true);

        try {
            const unreadResult = await notificationService.getUnreadNotifications();

            setRecentNotifications(unreadResult.data);
            setUnreadCount(unreadResult.unread_count);
        } finally {
            setLoading(false);
        }
    }, [userId]);

    // Initial fetch — runs once when userId becomes available
    useEffect(() => {
        if (!userId || fetchedRef.current) {
return;
}

        fetchedRef.current = true;
        void refresh();
    }, [userId, refresh]);

    // Echo subscription — re-subscribes if userId changes (login/logout)
    useEffect(() => {
        if (!userId || !echo) {
return;
}

        const activeEcho = echo;
        const channel = activeEcho.private(`user.${userId}.notifications`);

        channel.listen('.notification.created', (event: { notification: Notification }) => {
            setRecentNotifications((prev) => [event.notification, ...prev].slice(0, 10));
            setUnreadCount((c) => c + 1);
            showRealtimeToast(event.notification);
        });

        return () => {
            activeEcho.leave(`user.${userId}.notifications`);
        };
    }, [userId]);

    const markAsRead = useCallback(async (uuid: string) => {
        await notificationService.markAsRead(uuid);
        setRecentNotifications((prev) => prev.filter((n) => n.uuid !== uuid));
        setUnreadCount((c) => Math.max(0, c - 1));
    }, []);

    const markAllAsRead = useCallback(async () => {
        await notificationService.markAllAsRead();
        setRecentNotifications([]);
        setUnreadCount(0);
    }, []);

    return (
        <NotificationBellContext.Provider
            value={{ unreadCount, recentNotifications, loading, refresh, markAsRead, markAllAsRead }}
        >
            {children}
        </NotificationBellContext.Provider>
    );
}

export function useNotificationBellContext(): NotificationBellState {
    const ctx = useContext(NotificationBellContext);

    if (!ctx) {
throw new Error('useNotificationBellContext must be used inside NotificationBellProvider');
}

    return ctx;
}
