import { HttpNetworkError } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
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

import TemporaryPasswordDialog from '@/components/temporary-password-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ExportTrackingProvider } from '@/contexts/export-tracking-context';
import { NotificationBellProvider } from '@/contexts/notification-bell-context';
import { bindPrivateHttpScope } from '@/lib/http';
import { ZipDownloadProvider } from '@/modules/uploads/stores/zip-download-store';
import { logout } from '@/routes';
import type { User } from '@/types';

type PrivateUser = Pick<
    User,
    'id' | 'email_verified_at' | 'must_change_password'
>;

export interface PrivateStateScope {
    userId: number | null;
    canRead: boolean;
    generation: number;
    signal: AbortSignal;
    isCurrent: () => boolean;
    invalidate: (options?: { preserveStorage?: boolean }) => void;
    storageKey: (base: string) => string | null;
    onInvalidate: (cleanup: () => void) => void;
}

function identity(user?: PrivateUser | null): string {
    return `${user?.id ?? 'guest'}:${Boolean(user?.email_verified_at)}:${Boolean(user?.must_change_password)}`;
}

export function createPrivateStateScope(
    user: PrivateUser | null | undefined,
    generation: number,
): PrivateStateScope {
    const controller = new AbortController();
    const cleanups = new Set<() => void>();
    const userId = user?.id ?? null;
    const canRead = Boolean(
        userId && user?.email_verified_at && !user?.must_change_password,
    );
    const prefix = userId === null ? null : `private-state:${userId}:`;

    return {
        userId,
        canRead,
        generation,
        signal: controller.signal,
        isCurrent: () => canRead && !controller.signal.aborted,
        onInvalidate: (cleanup) => {
            cleanups.add(cleanup);
        },
        invalidate: (options) => {
            if (!controller.signal.aborted) {
                controller.abort();
                cleanups.forEach((cleanup) => cleanup());
                cleanups.clear();
            }

            if (
                !options?.preserveStorage &&
                typeof window !== 'undefined' &&
                prefix
            ) {
                try {
                    for (
                        let index = window.localStorage.length - 1;
                        index >= 0;
                        index--
                    ) {
                        const key = window.localStorage.key(index);

                        if (key?.startsWith(prefix)) {
                            window.localStorage.removeItem(key);
                        }
                    }
                } catch {
                    /* Storage may be unavailable in privacy mode. */
                }
            }
        },
        storageKey: (base) => {
            if (typeof window !== 'undefined') {
                try {
                    window.localStorage.removeItem(base);
                } catch {
                    /* Ignore unowned legacy storage. */
                }
            }

            return canRead && !controller.signal.aborted
                ? `${prefix}${base}`
                : null;
        },
    };
}

const PrivateStateContext = createContext<{
    scope: PrivateStateScope;
    endSession: (options?: { reason?: 'expired' }) => void;
} | null>(null);

export function usePrivateState() {
    const value = useContext(PrivateStateContext);

    if (!value) {
        throw new Error('Private state requires PrivateStateLayout.');
    }

    return value;
}

function PrivateTree({
    scope,
    endSession,
    children,
}: {
    scope: PrivateStateScope;
    endSession: (options?: { reason?: 'expired' }) => void;
    children: ReactNode;
}) {
    const [client] = useState(() => {
        const queryClient = new QueryClient();
        scope.onInvalidate(() => queryClient.clear());

        return queryClient;
    });

    // Install before child effects; SSR never binds a process-global identity.
    if (typeof window !== 'undefined') {
        bindPrivateHttpScope(scope);
    }

    return (
        <PrivateStateContext.Provider value={{ scope, endSession }}>
            <QueryClientProvider client={client}>
                <ExportTrackingProvider>
                    <ZipDownloadProvider>
                        <TooltipProvider delayDuration={0}>
                            <NotificationBellProvider>
                                {children}
                                <TemporaryPasswordDialog />
                                <Toaster />
                            </NotificationBellProvider>
                        </TooltipProvider>
                    </ZipDownloadProvider>
                </ExportTrackingProvider>
                <ReactQueryDevtools initialIsOpen={false} />
            </QueryClientProvider>
        </PrivateStateContext.Provider>
    );
}

/** Persistent outer layout: history props cannot establish a new identity. */
export function PrivateStateLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const user = page.props.auth?.user;
    const [authority, setAuthority] = useState(() => ({
        identity: identity(user),
        scope: createPrivateStateScope(user, 0),
        blocked: false,
        reason: undefined as 'expired' | undefined,
    }));
    const current = useRef(authority);
    const lifecycle = useRef({ generation: 0 });
    const revalidating = useRef(false);
    const endSession = useCallback((options?: { reason?: 'expired' }) => {
        if (current.current.blocked) {
            return;
        }

        current.current.scope.invalidate();
        // The before event runs before Inertia enqueues the new logout request.
        router.cancelAll();
        router.flushAll();
        toast.dismiss();
        current.current = {
            ...current.current,
            blocked: true,
            reason: options?.reason,
        };
        setAuthority(current.current);
    }, []);

    useEffect(() => {
        const activeLifecycle = lifecycle.current;
        const lease = ++activeLifecycle.generation;
        let historyReload: ReturnType<typeof setTimeout> | null = null;
        const removeBeforeUpdate = router.on('beforeUpdate', (event) => {
            if (historyReload !== null) {
                clearTimeout(historyReload);
                historyReload = null;
            }

            const nextUser = event.detail.page.props.auth?.user;
            const nextIdentity = identity(nextUser);
            const previous = current.current;
            revalidating.current = false;

            if (
                nextIdentity !== previous.identity ||
                previous.blocked ||
                previous.scope.signal.aborted
            ) {
                previous.scope.invalidate({
                    preserveStorage:
                        nextIdentity === previous.identity && !previous.blocked,
                });
                toast.dismiss();
                const next = {
                    identity: nextIdentity,
                    scope: createPrivateStateScope(
                        nextUser,
                        previous.scope.generation + 1,
                    ),
                    blocked: false,
                    reason: undefined,
                };
                current.current = next;
                setAuthority(next);
            }
        });
        const removeBefore = router.on('before', (event) => {
            if (
                event.detail.visit.method === 'post' &&
                event.detail.visit.url.pathname ===
                    new URL(logout.url(), window.location.origin).pathname
            ) {
                endSession();
            }
        });
        const removeNavigate = router.on('navigate', (event) => {
            if (current.current.reason === 'expired') {
                return;
            }

            if (
                identity(event.detail.page.props.auth?.user) !==
                    current.current.identity ||
                current.current.scope.signal.aborted
            ) {
                revalidating.current = true;
                current.current.scope.invalidate({ preserveStorage: true });
                toast.dismiss();

                if (historyReload !== null) {
                    clearTimeout(historyReload);
                }

                // Finish the history traversal before starting HTTP revalidation.
                historyReload = setTimeout(() => {
                    historyReload = null;
                    window.location.reload();
                }, 0);
            }
        });
        const recoverLogout = () => {
            if (
                current.current.blocked &&
                current.current.reason !== 'expired' &&
                !revalidating.current
            ) {
                revalidating.current = true;
                window.location.reload();
            }
        };
        const removeFinish = router.on('finish', recoverLogout);
        const removeNetworkError = router.on('networkError', (event) => {
            const error = event.detail.error;

            if (
                current.current.blocked &&
                error instanceof HttpNetworkError &&
                error.url &&
                new URL(error.url, window.location.origin).pathname ===
                    new URL(logout.url(), window.location.origin).pathname
            ) {
                event.preventDefault();
                recoverLogout();
            }
        });
        const removeHttpException = router.on('httpException', recoverLogout);

        return () => {
            if (historyReload !== null) {
                clearTimeout(historyReload);
            }

            removeBeforeUpdate();
            removeBefore();
            removeNavigate();
            removeFinish();
            removeNetworkError();
            removeHttpException();
            // StrictMode replays setup synchronously; only a real unmount disposes.
            queueMicrotask(() => {
                if (activeLifecycle.generation === lease) {
                    current.current.scope.invalidate();
                }
            });
        };
    }, [endSession]);

    const active = authority;

    if (active.blocked && active.reason === 'expired') {
        return (
            <Alert role="alert">
                <AlertDescription>
                    Sua sessão precisa ser revalidada.
                </AlertDescription>
                <Button type="button" onClick={() => window.location.reload()}>
                    Atualizar sessão
                </Button>
            </Alert>
        );
    }

    if (
        active.blocked ||
        active.scope.signal.aborted ||
        identity(user) !== active.identity
    ) {
        return null;
    }

    return (
        <PrivateTree
            key={active.scope.generation}
            scope={active.scope}
            endSession={endSession}
        >
            {children}
        </PrivateTree>
    );
}
