import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';

import { usePrivateState } from '@/contexts/private-state-context';
import echo from '@/lib/echo';
import {
    canceledPrivateRequest,
    getErrorMessage,
    toApiError,
} from '@/lib/http';
import { notificationService } from '@/services/notification-service';
import type { Notification } from '@/types/notification';

interface NotificationBellState {
    unreadCount: number;
    recentNotifications: Notification[];
    loading: boolean;
    revision: number;
    isCurrentRevision: (revision: number) => boolean;
    refresh: () => Promise<void>;
    markAsRead: (uuid: string) => Promise<void>;
    markAllAsRead: () => Promise<void>;
    deleteNotification: (uuid: string) => Promise<void>;
    executeAction: (
        uuid: string,
        actionType: string,
        payload?: Record<string, unknown>,
    ) => Promise<unknown>;
}

interface NotificationAccess {
    userId: number | null;
    canRead: boolean;
}

interface NotificationScope extends NotificationAccess {
    generation: number;
}

interface NotificationBellData {
    generation: number;
    unreadCount: number;
    recentNotifications: Notification[];
    loading: boolean;
}

interface PendingMutation {
    signature: string;
    promise: Promise<unknown>;
}

const NotificationBellContext = createContext<NotificationBellState | null>(
    null,
);

const TOAST_TYPES = new Set<Notification['type']>([
    'warning',
    'error',
    'system',
]);

function showRealtimeToast(
    notification: Notification,
    isCurrent: () => boolean,
): void {
    if (!TOAST_TYPES.has(notification.type)) {
        return;
    }

    const method =
        notification.type === 'error'
            ? 'error'
            : notification.type === 'warning'
              ? 'warning'
              : 'message';

    toast[method](notification.title, {
        description: notification.body,
        ...(notification.link
            ? {
                  action: {
                      label: 'Ver',
                      onClick: () => {
                          if (isCurrent() && notification.link) {
                              window.location.assign(notification.link);
                          }
                      },
                  },
              }
            : {}),
    });
}

function emptyBellData(generation: number): NotificationBellData {
    return {
        generation,
        unreadCount: 0,
        recentNotifications: [],
        loading: false,
    };
}

export function NotificationBellProvider({
    children,
}: {
    children: ReactNode;
}) {
    const { scope: privateScope } = usePrivateState();
    const [access] = useState<NotificationAccess>(() => ({
        userId: privateScope.userId,
        canRead: privateScope.canRead,
    }));
    const [bellData, setBellData] = useState(() => emptyBellData(0));
    const scopeRef = useRef<NotificationScope>({ ...access, generation: 0 });
    const fetchedRef = useRef<number | null>(null);
    const mountedRef = useRef(false);
    const leaveChannelRef = useRef<(() => void) | null>(null);
    const [revision, setRevision] = useState(0);
    const revisionRef = useRef(0);
    const requestRef = useRef<AbortController | null>(null);
    const mutationsRef = useRef(new Map<string, PendingMutation>());
    const eventsRef = useRef(new Set<string>());

    const isCurrentScope = useCallback(
        (scope: NotificationScope): boolean => {
            return (
                mountedRef.current &&
                privateScope.isCurrent() &&
                scope.canRead &&
                scopeRef.current === scope
            );
        },
        [privateScope],
    );

    const updateBell = useCallback(
        (
            scope: NotificationScope,
            update: (current: NotificationBellData) => NotificationBellData,
        ): void => {
            if (!isCurrentScope(scope)) {
                return;
            }

            setBellData((current) =>
                current.generation === scope.generation
                    ? update(current)
                    : current,
            );
        },
        [isCurrentScope],
    );

    const showError = useCallback(
        (scope: NotificationScope, error: unknown): void => {
            if (isCurrentScope(scope)) {
                const apiError = toApiError(error);

                if (apiError.type !== 'canceled') {
                    toast.error(getErrorMessage(apiError));
                }
            }
        },
        [isCurrentScope],
    );

    const isCurrentRevision = useCallback(
        (candidate: number): boolean =>
            privateScope.isCurrent() && revisionRef.current === candidate,
        [privateScope],
    );

    const invalidate = useCallback(
        (scope: NotificationScope): void => {
            if (!isCurrentScope(scope)) {
                return;
            }

            requestRef.current?.abort();
            revisionRef.current += 1;
            setRevision(revisionRef.current);
        },
        [isCurrentScope],
    );

    useEffect(() => {
        mountedRef.current = true;
        const mutations = mutationsRef.current;
        const events = eventsRef.current;

        return () => {
            mountedRef.current = false;
            scopeRef.current = {
                ...scopeRef.current,
                generation: scopeRef.current.generation + 1,
            };
            fetchedRef.current = null;
            requestRef.current?.abort();
            mutations.clear();
            events.clear();
            leaveChannelRef.current?.();
            leaveChannelRef.current = null;
        };
    }, []);

    const refreshBell = useCallback(async (): Promise<void> => {
        const scope = scopeRef.current;

        if (!isCurrentScope(scope)) {
            return;
        }

        requestRef.current?.abort();
        const controller = new AbortController();
        requestRef.current = controller;
        const requestRevision = revisionRef.current;
        const isCurrent = (): boolean =>
            isCurrentScope(scope) &&
            !controller.signal.aborted &&
            requestRef.current === controller &&
            isCurrentRevision(requestRevision);

        updateBell(scope, (current) => ({ ...current, loading: true }));

        try {
            const result = await notificationService.getUnreadNotifications({
                signal: controller.signal,
            });

            if (!isCurrent()) {
                return;
            }

            updateBell(scope, (current) => ({
                ...current,
                recentNotifications: result.data,
                unreadCount: result.unread_count,
            }));
        } catch (error: unknown) {
            if (isCurrent()) {
                showError(scope, error);
            }
        } finally {
            if (isCurrent()) {
                updateBell(scope, (current) => ({
                    ...current,
                    loading: false,
                }));
            }
        }
    }, [isCurrentRevision, isCurrentScope, showError, updateBell]);

    const refresh = useCallback(async (): Promise<void> => {
        invalidate(scopeRef.current);
        await refreshBell();
    }, [invalidate, refreshBell]);

    const mutate = useCallback(
        <T,>(
            key: string,
            signature: string,
            operation: () => Promise<T>,
        ): Promise<T> => {
            const scope = scopeRef.current;

            if (!isCurrentScope(scope)) {
                return Promise.reject(canceledPrivateRequest());
            }

            const pending = mutationsRef.current.get(key);

            if (pending) {
                if (pending.signature === signature) {
                    return pending.promise as Promise<T>;
                }

                const error = new Error(
                    'Uma ação desta notificação já está em andamento.',
                );
                showError(scope, error);

                return Promise.reject(error);
            }

            invalidate(scope);
            const entry: PendingMutation = {
                signature,
                promise: Promise.resolve(),
            };
            const promise = Promise.resolve()
                .then(async () => {
                    try {
                        if (!isCurrentScope(scope)) {
                            throw canceledPrivateRequest();
                        }

                        const result = await operation();

                        if (!isCurrentScope(scope)) {
                            throw canceledPrivateRequest();
                        }

                        return result;
                    } catch (error: unknown) {
                        showError(scope, error);

                        throw error;
                    } finally {
                        if (isCurrentScope(scope)) {
                            invalidate(scope);
                            await refreshBell();
                        }
                    }
                })
                .finally(() => {
                    if (mutationsRef.current.get(key) === entry) {
                        mutationsRef.current.delete(key);
                    }
                });
            entry.promise = promise;
            mutationsRef.current.set(key, entry);

            return promise;
        },
        [invalidate, isCurrentScope, refreshBell, showError],
    );

    useEffect(() => {
        const scope = scopeRef.current;
        let active = true;

        void Promise.resolve().then(() => {
            if (
                !active ||
                !isCurrentScope(scope) ||
                fetchedRef.current === scope.generation
            ) {
                return;
            }

            fetchedRef.current = scope.generation;
            setBellData(emptyBellData(scope.generation));
            void refreshBell();
        });

        return () => {
            active = false;
        };
    }, [access, isCurrentScope, refreshBell]);

    useEffect(() => {
        const scope = scopeRef.current;

        if (!isCurrentScope(scope) || !echo) {
            return;
        }

        const activeEcho = echo;
        const channelName = `user.${scope.userId}.notifications`;
        const channel = activeEcho.private(channelName);
        let active = true;
        const leave = (): void => {
            if (active) {
                active = false;
                activeEcho.leave(channelName);
            }
        };
        leaveChannelRef.current = leave;

        channel.listen(
            '.notification.created',
            (event: { notification: Notification }) => {
                if (!active || !isCurrentScope(scope)) {
                    return;
                }

                if (eventsRef.current.has(event.notification.uuid)) {
                    return;
                }

                eventsRef.current.add(event.notification.uuid);
                invalidate(scope);
                void refreshBell();
                showRealtimeToast(event.notification, () =>
                    isCurrentScope(scope),
                );
            },
        );

        return () => {
            leave();

            if (leaveChannelRef.current === leave) {
                leaveChannelRef.current = null;
            }
        };
    }, [access, invalidate, isCurrentScope, refreshBell]);

    const markAsRead = useCallback(
        async (uuid: string): Promise<void> => {
            await mutate(`read:${uuid}`, uuid, () =>
                notificationService.markAsRead(uuid),
            ).catch(() => undefined);
        },
        [mutate],
    );

    const markAllAsRead = useCallback(async (): Promise<void> => {
        await mutate('read:all', 'all', () =>
            notificationService.markAllAsRead(),
        ).catch(() => undefined);
    }, [mutate]);

    const deleteNotification = useCallback(
        async (uuid: string): Promise<void> => {
            await mutate(`delete:${uuid}`, uuid, () =>
                notificationService.deleteNotification(uuid),
            ).catch(() => undefined);
        },
        [mutate],
    );

    const executeAction = useCallback(
        (
            uuid: string,
            actionType: string,
            payload?: Record<string, unknown>,
        ): Promise<unknown> =>
            mutate(
                `action:${uuid}`,
                JSON.stringify([actionType, payload ?? {}]),
                () =>
                    notificationService.executeAction(
                        uuid,
                        actionType,
                        payload,
                    ),
            ),
        [mutate],
    );

    return (
        <NotificationBellContext.Provider
            value={{
                unreadCount: bellData.unreadCount,
                recentNotifications: bellData.recentNotifications,
                loading: bellData.loading,
                revision,
                isCurrentRevision,
                refresh,
                markAsRead,
                markAllAsRead,
                deleteNotification,
                executeAction,
            }}
        >
            {children}
        </NotificationBellContext.Provider>
    );
}

export function useNotificationBellContext(): NotificationBellState {
    const ctx = useContext(NotificationBellContext);

    if (!ctx) {
        throw new Error(
            'useNotificationBellContext must be used inside NotificationBellProvider',
        );
    }

    return ctx;
}
