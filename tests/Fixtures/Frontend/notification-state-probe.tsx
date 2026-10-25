import { createInertiaApp, router, usePage } from '@inertiajs/react';
import React, { useCallback, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';
import type {
    Notification,
    NotificationFilters,
} from '../../../resources/js/types/notification';

type Kind = 'list' | 'unread' | 'read' | 'all' | 'delete' | 'action';
type Call = {
    number: number;
    kind: Kind;
    owner: number;
    filters?: NotificationFilters;
    uuid?: string;
    signal?: AbortSignal;
};
type Gate = {
    kind: Kind;
    predicate?: (call: Call) => boolean;
    call?: Call;
    release?: () => void;
    reject?: () => void;
};
const result = document.getElementById('fixture-result')!;
const observations = document.getElementById('fixture-observations')!;
const calls: Call[] = [];
const gates: Gate[] = [];
const failures = new Set<Kind>();
const records = new Map<number, Notification[]>();
const listeners: Array<{
    owner: number;
    callback: (event: { notification: Notification }) => void;
    active: boolean;
}> = [];
const checks: Record<string, { status: 'passed' | 'failed'; reason?: string }> =
    {};
let owner = 101;
let identity = 101;
let running = false;
const clone = <T,>(value: T): T => structuredClone(value);
function assert(condition: unknown, message: string): asserts condition {
    if (!condition) {
        throw new Error(message);
    }
}
const user = (id: number) => ({
    id,
    uuid: `ui001-user-${id}`,
    name: `UI001 account ${id}`,
    email: `ui001-${id}@example.test`,
    email_verified_at: '2026-10-18T00:00:00Z',
    must_change_password: false,
});
function row(id: number, overrides: Partial<Notification> = {}): Notification {
    return {
        id,
        uuid: `ui001-${id}`,
        type: 'system',
        type_label: 'Sistema',
        category: 'general',
        category_label: 'Geral',
        title: `UI001 notice ${id}`,
        body: `Synthetic notice ${id}`,
        link: null,
        actions: null,
        action_status: null,
        is_read: false,
        read_at: null,
        created_at: new Date(2030, 0, 1, 0, 0, id).toISOString(),
        sender: null,
        data: null,
        ...overrides,
    };
}
const rows = (count: number, base = 1) =>
    Array.from({ length: count }, (_, i) => row(base + i));
records.set(owner, rows(4));
const owned = (id = owner) => records.get(id) ?? [];
function recordCall(call: Call): void {
    calls.push(call);
    observations.textContent = JSON.stringify(
        calls.map(({ signal, ...item }) => ({
            ...item,
            aborted: signal?.aborted ?? false,
        })),
        null,
        2,
    );
}
function request<T>(
    kind: Kind,
    compute: (id: number) => T,
    details: Partial<Call> = {},
): Promise<T> {
    const call: Call = { number: calls.length + 1, kind, owner, ...details };
    recordCall(call);
    const failure = failures.delete(kind);
    const snapshot =
        kind === 'list' || kind === 'unread'
            ? clone(compute(call.owner))
            : undefined;
    const run = () => {
        if (failure)
            throw {
                type: 'http',
                message: 'UI001 controlled mutation refusal',
                status: 422,
            };
        return snapshot === undefined ? compute(call.owner) : snapshot;
    };
    const gate = gates.find(
        (item) =>
            !item.call &&
            item.kind === kind &&
            (!item.predicate || item.predicate(call)),
    );
    if (!gate) return Promise.resolve().then(run);
    gate.call = call;
    // Intentionally ignore cancellation here: production hooks must discard late settlements.
    return new Promise<T>((resolve, reject) => {
        gate.release = () => {
            try {
                resolve(run());
            } catch (error) {
                reject(error);
            }
        };
        gate.reject = () =>
            reject({
                type: 'http',
                status: 500,
                message: 'UI001 delayed account A failure',
            });
    });
}
const service = {
    getNotifications(
        filters: NotificationFilters = {},
        config?: { signal?: AbortSignal },
    ) {
        return request(
            'list',
            (id) => {
                const all = owned(id);
                const selected = all.filter(
                    (item) =>
                        (!filters.type || item.type === filters.type) &&
                        (!filters.category ||
                            item.category === filters.category) &&
                        (!filters.read ||
                            item.is_read === (filters.read === 'true')),
                );
                const page = Math.max(1, Number(filters.page ?? 1));
                const perPage = Math.max(1, Number(filters.per_page ?? 15));
                const data = selected.slice(
                    (page - 1) * perPage,
                    page * perPage,
                );
                return {
                    data,
                    unread_count: all.filter((item) => !item.is_read).length,
                    meta: {
                        current_page: page,
                        last_page: Math.max(
                            1,
                            Math.ceil(selected.length / perPage),
                        ),
                        per_page: perPage,
                        total: selected.length,
                        from: data.length ? (page - 1) * perPage + 1 : null,
                        to: data.length
                            ? (page - 1) * perPage + data.length
                            : null,
                        path: '/api/v1/notifications',
                    },
                    links: { first: null, last: null, prev: null, next: null },
                };
            },
            { filters: clone(filters), signal: config?.signal },
        );
    },
    getUnreadNotifications(config?: { signal?: AbortSignal }) {
        return request(
            'unread',
            (id) => ({
                data: owned(id)
                    .filter((item) => !item.is_read)
                    .slice(0, 10),
                unread_count: owned(id).filter((item) => !item.is_read).length,
            }),
            { signal: config?.signal },
        );
    },
    getUnreadCount() {
        return Promise.resolve({
            count: owned().filter((item) => !item.is_read).length,
        });
    },
    markAsRead(uuid: string) {
        return request(
            'read',
            (id) => {
                const item = owned(id).find((entry) => entry.uuid === uuid);
                if (item) {
                    item.is_read = true;
                    item.read_at = '2030-02-01T00:00:00Z';
                }
                return clone(item);
            },
            { uuid },
        );
    },
    markAllAsRead() {
        return request('all', (id) => {
            let marked = 0;
            owned(id).forEach((item) => {
                if (!item.is_read) marked++;
                item.is_read = true;
                item.read_at = '2030-02-01T00:00:00Z';
            });
            return { marked };
        });
    },
    deleteNotification(uuid: string) {
        return request(
            'delete',
            (id) => {
                records.set(
                    id,
                    owned(id).filter((item) => item.uuid !== uuid),
                );
            },
            { uuid },
        );
    },
    executeAction(
        uuid: string,
        actionType: string,
        payload?: Record<string, unknown>,
    ) {
        return request(
            'action',
            (id) => {
                const item = owned(id).find((entry) => entry.uuid === uuid);
                if (item)
                    item.action_status =
                        actionType === 'reject_request'
                            ? 'rejected'
                            : 'executed';
                return { action_type: actionType, result: { payload } };
            },
            { uuid },
        );
    },
};
const echo = {
    private(channel: string) {
        const id = Number(/^user\.(\d+)\.notifications$/.exec(channel)?.[1]);
        return {
            listen(
                _event: string,
                callback: (event: { notification: Notification }) => void,
            ) {
                listeners.push({ owner: id, callback, active: true });
                return this;
            },
        };
    },
    leave(channel: string) {
        const id = Number(/^user\.(\d+)\.notifications$/.exec(channel)?.[1]);
        listeners
            .filter((item) => item.owner === id)
            .forEach((item) => {
                item.active = false;
            });
    },
};
Object.assign(globalThis, { __ui001NotificationFixture: { service, echo } });
window.fetch = () =>
    Promise.reject(new Error('UI001 fixture forbids HTTP transport.'));
XMLHttpRequest.prototype.open = function () {
    throw new Error('UI001 fixture forbids XHR transport.');
};

// Only API/Echo are substituted; these imports mount the production React tree.
const { PrivateStateLayout, usePrivateState } =
    await import('../../../resources/js/contexts/private-state-context');
const { useNotifications, useNotificationBell } =
    await import('../../../resources/js/hooks/use-notifications');
const { NotificationBell } =
    await import('../../../resources/js/components/notification-bell');
const { default: NotificationsPage } =
    await import('../../../resources/js/pages/notifications/index');
type Hook = (
    filters?: NotificationFilters,
    onPageChange?: (page: number) => void,
) => ReturnType<typeof useNotifications>;
const useProbeNotifications: Hook = useNotifications;
let live: {
    identity: number;
    scope: ReturnType<typeof usePrivateState>['scope'];
    page: ReturnType<typeof useNotifications>;
    bell: ReturnType<typeof useNotificationBell>;
    filters: NotificationFilters;
    setFilters: React.Dispatch<React.SetStateAction<NotificationFilters>>;
} | null = null;
function ProbePage() {
    const account = usePage().props.auth.user;
    const state = usePrivateState();
    const [filters, setFilters] = useState<NotificationFilters>({
        page: 1,
        per_page: 2,
    });
    const onPageChange = useCallback(
        (page: number) => setFilters((current) => ({ ...current, page })),
        [],
    );
    const page = useProbeNotifications(filters, onPageChange);
    const bell = useNotificationBell();
    live = {
        identity: account.id,
        scope: state.scope,
        page,
        bell,
        filters,
        setFilters,
    };
    return (
        <>
            <section aria-label="Real notification bell">
                <NotificationBell />
            </section>
            <pre id="fixture-state">
                {JSON.stringify(
                    {
                        identity: account.id,
                        scope: state.scope.userId,
                        filters,
                        page: {
                            ids: page.notifications.map((item) => item.uuid),
                            unread: page.unreadCount,
                            meta: page.pagination,
                            loading: page.loading,
                        },
                        bell: {
                            ids: bell.recentNotifications.map(
                                (item) => item.uuid,
                            ),
                            unread: bell.unreadCount,
                            loading: bell.loading,
                        },
                    },
                    null,
                    2,
                )}
            </pre>
            <section id="real-notification-page">
                <NotificationsPage />
            </section>
        </>
    );
}
const frame = () => new Promise<void>((resolve) => setTimeout(resolve, 20));
async function until(condition: () => unknown, message: string): Promise<void> {
    const deadline = performance.now() + 5000;
    while (!condition()) {
        if (performance.now() > deadline) throw new Error(message);
        await frame();
    }
}
async function settled(): Promise<void> {
    await Promise.resolve();
    await frame();
    await frame();
}
function hold(kind: Kind, predicate?: (call: Call) => boolean): Gate {
    const gate = { kind, predicate };
    gates.push(gate);
    return gate;
}
async function reset(data = rows(4)): Promise<void> {
    gates.forEach((gate) => gate.release?.());
    gates.length = 0;
    failures.clear();
    identity += 10;
    owner = identity;
    records.set(owner, clone(data));
    // Client-side router.replace does not emit the HTTP beforeUpdate event.
    // Simulate only that trusted-response boundary; usePage, remount and cleanup remain real.
    document.dispatchEvent(
        new CustomEvent('inertia:beforeUpdate', {
            detail: {
                page: { props: { auth: { user: user(owner) }, errors: {} } },
            },
        }),
    );
    await new Promise<void>((resolve) =>
        router.replace({
            props: { auth: { user: user(owner) }, errors: {} },
            preserveState: false,
            preserveScroll: true,
            onFinish: () => resolve(),
        }),
    );
    await until(
        () =>
            live?.identity === owner &&
            !live.page.loading &&
            !live.bell.loading &&
            live.page.pagination?.total === data.length &&
            live.bell.unreadCount ===
                data.filter((item) => !item.is_read).length,
        'The real identity boundary did not initialize the fixture account.',
    );
}
function emit(item: Notification, id = owner): void {
    if (!owned(id).some((entry) => entry.uuid === item.uuid))
        records.set(id, [clone(item), ...owned(id)]);
    listeners
        .filter((listener) => listener.owner === id && listener.active)
        .forEach((listener) =>
            listener.callback({ notification: clone(item) }),
        );
}
function receipt(): void {
    result.textContent = JSON.stringify(
        {
            status: Object.values(checks).some(
                (item) => item.status === 'failed',
            )
                ? 'failed'
                : running
                  ? 'running'
                  : 'passed',
            contracts: checks,
            calls: calls.length,
            hasLegacyDataPage: Boolean(document.querySelector('[data-page]')),
            identity: live?.identity,
            state: document.getElementById('fixture-state')?.textContent,
        },
        null,
        2,
    );
}
async function check(name: string, run: () => Promise<void>): Promise<void> {
    try {
        await run();
        checks[name] = { status: 'passed' };
    } catch (error) {
        checks[name] = {
            status: 'failed',
            reason:
                error instanceof Error ? error.message : JSON.stringify(error),
        };
    }
    receipt();
}
async function runContracts(): Promise<void> {
    if (running) return;
    running = true;
    Object.keys(checks).forEach((key) => delete checks[key]);
    receipt();
    await check('inertia-v3-startup-without-data-page', async () => {
        await until(
            () =>
                live?.identity === owner &&
                !live.bell.loading &&
                live.bell.unreadCount === 4,
            'Bell must initialize from real Inertia/private-scope props.',
        );
        assert(
            !document.querySelector('[data-page]'),
            'Fixture must exercise script/page initialization without a data-page attribute.',
        );
        assert(
            live?.scope.userId === owner,
            'Private scope must use the actual usePage identity.',
        );
    });
    await check(
        'page-mark-read-synchronizes-bell-and-idempotent-read',
        async () => {
            await reset();
            await live!.page.markAsRead('ui001-1');
            await until(
                () =>
                    live!.page.notifications.find(
                        (item) => item.uuid === 'ui001-1',
                    )?.is_read &&
                    live!.page.unreadCount === 3 &&
                    live!.bell.unreadCount === 3 &&
                    !live!.bell.recentNotifications.some(
                        (item) => item.uuid === 'ui001-1',
                    ),
                'Page read succeeded but page/bell/count did not converge.',
            );
            await live!.bell.markAsRead('ui001-1');
            await settled();
            assert(
                live!.bell.unreadCount === 3 && live!.page.unreadCount === 3,
                'Re-reading an already-read row must not subtract a second unread item.',
            );
        },
    );
    await check(
        'page-delete-synchronizes-bell-count-and-pagination',
        async () => {
            await reset();
            await live!.page.deleteNotification('ui001-1');
            await until(
                () =>
                    live!.page.pagination?.total === 3 &&
                    live!.bell.unreadCount === 3 &&
                    live!.page.unreadCount === 3 &&
                    !live!.bell.recentNotifications.some(
                        (item) => item.uuid === 'ui001-1',
                    ),
                'Delete must invalidate both views and pagination metadata.',
            );
        },
    );
    await check('bell-mark-all-invalidates-page-read-filter', async () => {
        await reset();
        live!.setFilters({ page: 1, per_page: 2, read: 'false' });
        await until(
            () => !live!.page.loading && live!.page.pagination?.total === 4,
            'Unread filter must settle.',
        );
        await live!.bell.markAllAsRead();
        await until(
            () =>
                live!.page.pagination?.total === 0 &&
                live!.page.notifications.length === 0 &&
                live!.page.unreadCount === 0 &&
                live!.bell.unreadCount === 0,
            'Bell mark-all left stale unread rows or counts in the page.',
        );
    });
    await check(
        'pagination-clamps-after-deletion-and-can-navigate-again',
        async () => {
            await reset(rows(3));
            live!.setFilters({ page: 2, per_page: 2 });
            await until(
                () =>
                    live!.page.pagination?.current_page === 2 &&
                    live!.page.notifications[0]?.uuid === 'ui001-3',
                'Second page must be fetched and displayed.',
            );
            await live!.page.deleteNotification('ui001-3');
            await until(
                () =>
                    live!.filters.page === 1 &&
                    live!.page.pagination?.current_page === 1 &&
                    live!.page.pagination?.total === 2 &&
                    live!.page.notifications.length === 2,
                'Deleting the final row must clamp both external page state and displayed data.',
            );
            emit(row(99));
            await until(
                () => live!.page.pagination?.total === 3,
                'Realtime must refresh pagination metadata.',
            );
            live!.setFilters((current) => ({ ...current, page: 2 }));
            await until(
                () =>
                    live!.page.pagination?.current_page === 2 &&
                    live!.page.notifications[0]?.uuid === 'ui001-2',
                'Navigation after clamping must reach the recreated second page.',
            );
        },
    );
    await check(
        'real-page-controls-navigate-and-reset-both-filters-to-page-one',
        async () => {
            await reset(rows(16));
            const pageElement = document.getElementById(
                'real-notification-page',
            )!;
            const next = () =>
                Array.from(pageElement.querySelectorAll('button')).find(
                    (button) => button.textContent?.trim() === 'Próxima',
                );
            assert(
                next(),
                'The real notifications page must expose a Próxima pagination control.',
            );
            next()!.click();
            await until(
                () =>
                    calls.some(
                        (call) =>
                            call.owner === owner &&
                            call.kind === 'list' &&
                            call.filters?.per_page !== 2 &&
                            call.filters?.page === 2,
                    ) && pageElement.textContent?.includes('UI001 notice 16'),
                'The real page pagination control must query and display page two.',
            );
            const changeFilter = async (index: number, label: string) => {
                (
                    pageElement.querySelectorAll('[role="combobox"]')[
                        index
                    ] as HTMLElement
                ).click();
                await until(
                    () =>
                        Array.from(
                            document.querySelectorAll('[role="option"]'),
                        ).some(
                            (option) => option.textContent?.trim() === label,
                        ),
                    'The real filter menu must open.',
                );
                (
                    Array.from(
                        document.querySelectorAll('[role="option"]'),
                    ).find(
                        (option) => option.textContent?.trim() === label,
                    ) as HTMLElement
                ).click();
            };
            await changeFilter(0, 'Sistema');
            await until(
                () =>
                    calls.some(
                        (call) =>
                            call.owner === owner &&
                            call.kind === 'list' &&
                            call.filters?.type === 'system' &&
                            call.filters?.page === 1,
                    ) && pageElement.textContent?.includes('UI001 notice 1'),
                'Changing the real type filter must reset page two to page one.',
            );
            next()!.click();
            await until(
                () => pageElement.textContent?.includes('UI001 notice 16'),
                'The filtered page must remain navigable.',
            );
            await changeFilter(1, 'Lidas');
            await until(
                () =>
                    calls.some(
                        (call) =>
                            call.owner === owner &&
                            call.kind === 'list' &&
                            call.filters?.read === 'true' &&
                            call.filters?.page === 1,
                    ) &&
                    pageElement.textContent?.includes(
                        'Nenhuma notificação encontrada',
                    ),
                'Changing the real read-status filter must reset to page one.',
            );
        },
    );
    await check(
        'realtime-deduplicates-beyond-the-recent-ten-and-refreshes-page',
        async () => {
            await reset(rows(2));
            const history = toast.getHistory().length;
            for (let index = 100; index < 112; index++) emit(row(index));
            await until(
                () =>
                    live!.bell.unreadCount === 14 &&
                    live!.page.pagination?.total === 14,
                'Realtime must refresh both views from canonical server data.',
            );
            const uniqueHistory = toast.getHistory().length;
            emit(row(100));
            await settled();
            assert(
                live!.bell.unreadCount === 14,
                'An older duplicate outside the recent ten must not inflate the count.',
            );
            assert(
                new Set(live!.bell.recentNotifications.map((item) => item.uuid))
                    .size === live!.bell.recentNotifications.length,
                'Recent notifications must contain unique UUIDs.',
            );
            assert(
                uniqueHistory - history === 12 &&
                    toast.getHistory().length === uniqueHistory,
                'Each new event must toast once and duplicate events must not toast again.',
            );
        },
    );
    await check('failed-mutations-preserve-both-views-and-counts', async () => {
        await reset();
        for (const kind of ['read', 'delete', 'all'] as const) {
            failures.add(kind);
            try {
                if (kind === 'read') await live!.page.markAsRead('ui001-1');
                else if (kind === 'delete')
                    await live!.page.deleteNotification('ui001-1');
                else await live!.bell.markAllAsRead();
            } catch {
                /* Existing API may propagate a refusal; state still must stay intact. */
            }
            await settled();
            assert(
                live!.page.unreadCount === 4 &&
                    live!.bell.unreadCount === 4 &&
                    live!.page.notifications.some(
                        (item) => item.uuid === 'ui001-1' && !item.is_read,
                    ) &&
                    owned().length === 4,
                `A refused ${kind} must not mutate either UI view.`,
            );
        }
    });
    await check(
        'old-page-and-bell-responses-cannot-undo-a-mutation',
        async () => {
            await reset();
            const pageGate = hold(
                'list',
                (call) => call.filters?.per_page === 2,
            );
            live!.page.refresh();
            await until(
                () => Boolean(pageGate.call),
                'Delayed page request must start.',
            );
            const bellGate = hold('unread');
            live!.bell.refresh();
            await until(
                () => Boolean(bellGate.call),
                'Delayed bell request must start.',
            );
            await live!.page.markAsRead('ui001-1');
            pageGate.release!();
            bellGate.release!();
            await until(
                () =>
                    live!.page.unreadCount === 3 &&
                    live!.bell.unreadCount === 3 &&
                    !live!.bell.recentNotifications.some(
                        (item) => item.uuid === 'ui001-1',
                    ),
                'Late GET snapshots must not restore an unread record after a successful mutation.',
            );
        },
    );
    await check(
        'simultaneous-read-from-page-and-bell-sends-one-command',
        async () => {
            await reset();
            const gate = hold('read');
            const before = calls.length;
            const first = live!.page.markAsRead('ui001-1');
            const second = live!.bell.markAsRead('ui001-1');
            await until(() => Boolean(gate.call), 'Read command must start.');
            await settled();
            assert(
                calls.slice(before).filter((call) => call.kind === 'read')
                    .length === 1,
                'Concurrent identical read commands must share one pending request.',
            );
            gate.release!();
            await Promise.all([first, second]);
            await until(
                () =>
                    live!.page.unreadCount === 3 &&
                    live!.bell.unreadCount === 3,
                'The shared command must converge without duplicate subtraction.',
            );
        },
    );
    await check(
        'rejected-actions-use-server-status-and-refusals-stay-pending',
        async () => {
            await reset([
                row(1, {
                    type: 'action',
                    action_status: 'pending',
                    actions: [
                        {
                            label: 'Rejeitar',
                            type: 'reject_request',
                            variant: 'destructive',
                        },
                    ],
                }),
            ]);
            failures.add('action');
            try {
                await live!.page.executeAction('ui001-1', 'reject_request');
            } catch {
                /* Expected synthetic 422. */
            }
            await settled();
            assert(
                live!.page.notifications[0]?.action_status === 'pending' &&
                    owned()[0]?.action_status === 'pending',
                'A refused action must keep its pending server status.',
            );
            await live!.page.executeAction('ui001-1', 'reject_request');
            await until(
                () => live!.page.notifications[0]?.action_status === 'rejected',
                'Reject action must display rejected rather than executed.',
            );
        },
    );
    await check(
        'identity-change-discards-old-http-realtime-and-mutation-errors',
        async () => {
            await reset();
            const previousOwner = owner;
            const oldListeners = listeners
                .filter((listener) => listener.owner === previousOwner)
                .map((listener) => listener.callback);
            const pageGate = hold(
                'list',
                (call) => call.filters?.per_page === 2,
            );
            live!.page.refresh();
            const bellGate = hold('unread');
            live!.bell.refresh();
            const mutationGate = hold('read');
            const mutation = live!.page
                .markAsRead('ui001-1')
                .catch(() => undefined);
            await until(
                () =>
                    Boolean(
                        pageGate.call && bellGate.call && mutationGate.call,
                    ),
                'All account A requests must be pending before switching.',
            );
            // Keep A's deferred promises unresolved across the real Inertia identity transition.
            gates.length = 0;
            await reset(rows(2, 900));
            const history = toast.getHistory().length;
            pageGate.release!();
            bellGate.release!();
            mutationGate.reject!();
            oldListeners.forEach((callback) =>
                callback({ notification: row(777) }),
            );
            await mutation;
            await settled();
            assert(
                live!.identity === owner &&
                    live!.scope.userId === owner &&
                    live!.bell.unreadCount === 2 &&
                    live!.page.unreadCount === 2,
                'Old account callbacks must not change the new identity or counts.',
            );
            assert(
                live!.page.notifications.every((item) => item.id >= 900) &&
                    live!.bell.recentNotifications.every(
                        (item) => item.id >= 900,
                    ),
                'No old private rows may enter account B.',
            );
            assert(
                toast.getHistory().length === history,
                'Account A delayed failures/events must not toast in B.',
            );
        },
    );
    running = false;
    receipt();
}
document.getElementById('run-contracts')!.addEventListener('click', () => {
    void runContracts();
});
await createInertiaApp({
    id: 'app',
    page: {
        component: 'Ui001Fixture',
        props: { auth: { user: user(owner) }, errors: {} },
        url: location.pathname,
        version: null,
        clearHistory: false,
        encryptHistory: false,
        flash: {},
        rememberedState: {},
    },
    resolve: () => ProbePage,
    setup: ({ el, App, props }) => {
        createRoot(el).render(
            <App {...props} defaultLayout={() => [PrivateStateLayout]} />,
        );
    },
});
result.textContent =
    'Inertia inicializado sem data-page. Clique em Executar regressões UI-001.';
