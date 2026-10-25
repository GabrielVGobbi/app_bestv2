import { createInertiaApp } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { QueryClient } from '@tanstack/react-query';
import type {
    AxiosAdapter,
    AxiosResponse,
    InternalAxiosRequestConfig,
} from 'axios';
import { AxiosError, AxiosHeaders, CanceledError } from 'axios';
import { StrictMode } from 'react';
import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';

import { PrivateStateLayout } from '../../../resources/js/contexts/private-state-context';
import { http } from '../../../resources/js/lib/http';
import { timelineKeys } from '../../../resources/js/modules/timeline/api/keys';
import type {
    CommentPayload,
    TimelineCommentItem,
    TimelineItem,
    TimelineResponse,
} from '../../../resources/js/modules/timeline/api/types';
import { ActivityTimeline } from '../../../resources/js/modules/timeline/components/activity-timeline';
import { useCommentMutations } from '../../../resources/js/modules/timeline/hooks/use-comment-mutations';

type TimelinePage = {
    data: TimelineItem[];
    next: string | null;
};
type View = { modelId: number; revision: number };
type Gate = {
    config: InternalAxiosRequestConfig;
    modelId: number;
    cursor: string | null;
    settled: boolean;
    resolve: (data: unknown) => void;
    reject500: () => void;
    reject403: () => void;
};
type RequestRecord = {
    method: string;
    url: string;
    modelId: number | null;
    cursor: string | null;
    signal: AbortSignal | undefined;
};
type MutationApi = {
    create: (payload: CommentPayload) => Promise<unknown>;
    reply: (commentId: number, payload: CommentPayload) => Promise<unknown>;
    update: (commentId: number, payload: CommentPayload) => Promise<unknown>;
    remove: (commentId: number) => Promise<unknown>;
};
type Harness = {
    client: QueryClient;
    getView: () => View;
    setModelId: (modelId: number) => void;
    resetView: () => void;
    mutations: MutationApi;
};
type Check = { name: string; status: 'passed' | 'failed'; error?: string };
type WindowWithHarness = Window & { __uiTimelineCursorHarness?: Harness };

const result = document.getElementById('fixture-result')!;
const runButton = document.getElementById('run-contracts')!;
const gates: Gate[] = [];
const requests: RequestRecord[] = [];
let forbiddenNetwork = 0;
let requestCount = 0;
let failNextCursor: string | null = null;
let holdNextCursor: string | null = null;
let holdCreateModelId: number | null = null;
let failNextFirstPageStatus: number | null = null;
let harness: Harness | null = null;
let viewRef: View = { modelId: 101, revision: 0 };
const cursorAnchors = new Map<string, { modelId: number; itemKey: string }>();
const cursorByAnchor = new Map<string, string>();
let cursorSequence = 0;
let serverItems: Record<number, TimelineItem[]> = {};

function denyNetwork(): never {
    forbiddenNetwork++;

    throw new Error('UI-003 fixture forbids native network access.');
}

globalThis.fetch = denyNetwork;
XMLHttpRequest.prototype.open = denyNetwork;

if (navigator.sendBeacon) {
    navigator.sendBeacon = () => denyNetwork();
}

function response(
    config: InternalAxiosRequestConfig,
    data: unknown,
    status = 200,
): AxiosResponse {
    return {
        data,
        status,
        statusText: status === 200 ? 'OK' : 'Fixture failure',
        headers: new AxiosHeaders(),
        config,
    };
}

function comment(
    id: number,
    marker: string,
    createdAt: string,
    modelId = 101,
): TimelineCommentItem {
    return {
        type: 'comment',
        id,
        uuid: `30000000-0000-4000-8000-${String(modelId).padStart(12, '0')}${String(id).padStart(4, '0')}`.slice(
            0,
            36,
        ),
        body: marker,
        body_format: 'plain',
        author: { id: 900003, name: 'UI003 fixture' },
        replies: [],
        permissions: {
            can_update: true,
            can_delete: true,
            can_reply: false,
        },
        edited_at: null,
        created_at: createdAt,
    };
}

function activity(id: string, marker: string, createdAt: string): TimelineItem {
    return {
        type: 'activity',
        id,
        event: 'updated',
        label: null,
        description: marker,
        icon: null,
        severity: 'muted',
        causer: { id: 900003, name: 'UI003 fixture' },
        changes: [],
        created_at: createdAt,
    };
}

const tiedDate = '2026-06-10T12:00:00.000000Z';
const newerDate = '2026-06-11T12:00:00.000000Z';
const olderDate = '2026-06-09T12:00:00.000000Z';
const middleDate = '2026-06-10T11:00:00.000000Z';

function itemKey(item: TimelineItem): string {
    return `${item.type}:${item.id}`;
}

function initialServerItems(): Record<number, TimelineItem[]> {
    return {
        101: [
            comment(3, '[UI003:comment:3]', olderDate),
            comment(2, '[UI003:comment:2]', middleDate),
            activity('2', '[UI003:activity:2]', tiedDate),
            activity('1', '[UI003:activity:1]', tiedDate),
            comment(1, '[UI003:comment:1]', tiedDate),
        ],
        202: [comment(1, '[UI003:parent-b:1]', newerDate, 202)],
    };
}

serverItems = initialServerItems();

function cursorFor(modelId: number, item: TimelineItem): string {
    const anchor = `${modelId}:${itemKey(item)}`;
    const existing = cursorByAnchor.get(anchor);

    if (existing) {
        return existing;
    }

    const cursor = `opaque-cursor-${++cursorSequence}`;
    cursorByAnchor.set(anchor, cursor);
    cursorAnchors.set(cursor, { modelId, itemKey: itemKey(item) });

    return cursor;
}

function serverPage(
    modelId: number,
    cursor: string | null,
): TimelinePage | null {
    const items = serverItems[modelId];

    if (!items) {
        return null;
    }

    let end = items.length;

    if (cursor !== null) {
        const anchor = cursorAnchors.get(cursor);

        if (!anchor || anchor.modelId !== modelId) {
            return null;
        }

        const anchorIndex = items.findIndex(
            (item) => itemKey(item) === anchor.itemKey,
        );

        if (anchorIndex < 0) {
            return null;
        }

        end = anchorIndex;
    }

    const start = Math.max(0, end - 2);
    const data = items.slice(start, end);

    return {
        data,
        next: start > 0 ? cursorFor(modelId, items[start]!) : null,
    };
}

function findComment(
    modelId: number,
    commentId: number,
): TimelineCommentItem | null {
    return (
        serverItems[modelId]?.find(
            (item): item is TimelineCommentItem =>
                item.type === 'comment' && item.id === commentId,
        ) ?? null
    );
}

function requestModelId(url: string): number | null {
    const match = url.match(/^\/api\/v1\/timeline\/client\/(\d+)(?:\/|$)/);

    return match ? Number(match[1]) : null;
}

function requestCursor(config: InternalAxiosRequestConfig): string | null {
    const params = config.params as Record<string, unknown> | undefined;
    const cursor = params?.cursor;

    return typeof cursor === 'string' ? cursor : null;
}

function requestBody(
    config: InternalAxiosRequestConfig,
): Record<string, unknown> {
    if (typeof config.data === 'string') {
        return JSON.parse(config.data) as Record<string, unknown>;
    }

    return (config.data ?? {}) as Record<string, unknown>;
}

function timelineResponse(
    modelId: number,
    page: TimelinePage,
): TimelineResponse {
    return {
        data: structuredClone(page.data),
        subject: {
            type: 'client',
            id: modelId,
            uuid: `40000000-0000-4000-8000-${String(modelId).padStart(12, '0')}`,
            label: `UI003 parent ${modelId}`,
        },
        meta: { next_cursor: page.next },
    };
}

function deferredGate(
    config: InternalAxiosRequestConfig,
    modelId: number,
    cursor: string | null,
): Promise<AxiosResponse> {
    return new Promise<AxiosResponse>((resolve, reject) => {
        let settled = false;
        const rejectStatus = (status: number) => {
            if (settled) {
                return;
            }

            settled = true;
            gate.settled = true;
            const failure = response(
                config,
                { message: `Synthetic UI003 server failure (${status}).` },
                status,
            );
            reject(
                new AxiosError(
                    `Synthetic UI003 server failure (${status}).`,
                    AxiosError.ERR_BAD_RESPONSE,
                    config,
                    undefined,
                    failure,
                ),
            );
        };
        const gate: Gate = {
            config,
            modelId,
            cursor,
            settled: false,
            resolve(data) {
                if (settled) {
                    return;
                }

                settled = true;
                gate.settled = true;
                resolve(response(config, data));
            },
            reject500() {
                rejectStatus(500);
            },
            reject403() {
                rejectStatus(403);
            },
        };
        gates.push(gate);
        config.signal?.addEventListener(
            'abort',
            () => reject(new CanceledError('UI003 request canceled.', config)),
            { once: true },
        );
    });
}

function requestAdapter(): AxiosAdapter {
    return (config) => {
        requestCount++;
        const method = (config.method ?? 'get').toLowerCase();
        const url = config.url ?? '';
        const modelId = requestModelId(url);
        const cursor = requestCursor(config);
        requests.push({ method, url, modelId, cursor, signal: config.signal });

        if (method === 'get' && modelId !== null) {
            if (cursor === null && failNextFirstPageStatus !== null) {
                const status = failNextFirstPageStatus;
                failNextFirstPageStatus = null;
                const failure = response(
                    config,
                    { message: `Synthetic UI003 HTTP ${status} failure.` },
                    status,
                );

                return Promise.reject(
                    new AxiosError(
                        `Synthetic UI003 HTTP ${status} failure.`,
                        AxiosError.ERR_BAD_RESPONSE,
                        config,
                        undefined,
                        failure,
                    ),
                );
            }

            const page = serverPage(modelId, cursor);

            if (!page) {
                return Promise.reject(
                    new AxiosError(
                        `Unexpected UI003 timeline cursor: ${String(cursor)}`,
                        AxiosError.ERR_BAD_REQUEST,
                        config,
                    ),
                );
            }

            if (cursor !== null && cursor === failNextCursor) {
                failNextCursor = null;

                return deferredGate(config, modelId, cursor).then(
                    (value) => value,
                );
            }

            if (cursor !== null && cursor === holdNextCursor) {
                holdNextCursor = null;

                return deferredGate(config, modelId, cursor);
            }

            return Promise.resolve(
                response(config, timelineResponse(modelId, page)),
            );
        }

        if (
            method === 'post' &&
            modelId !== null &&
            url.endsWith('/comments')
        ) {
            const payload = requestBody(config) as CommentPayload;
            const created = comment(99, payload.body, newerDate);
            serverItems[modelId]!.push(created);

            if (holdCreateModelId === modelId) {
                holdCreateModelId = null;

                return deferredGate(config, modelId, null);
            }

            return Promise.resolve(
                response(config, {
                    data: structuredClone(created),
                    message: 'Created.',
                }),
            );
        }

        const replyMatch = url.match(
            /^\/api\/v1\/timeline\/comments\/(\d+)\/replies$/,
        );

        if (method === 'post' && replyMatch) {
            const parent = findComment(101, Number(replyMatch[1]));

            if (!parent) {
                return Promise.reject(
                    new AxiosError(
                        'Unexpected UI003 reply parent.',
                        AxiosError.ERR_BAD_REQUEST,
                        config,
                    ),
                );
            }

            const payload = requestBody(config) as CommentPayload;
            const reply = comment(90, payload.body, newerDate);
            parent.replies = [...(parent.replies ?? []), reply];

            return Promise.resolve(
                response(config, {
                    data: structuredClone(reply),
                    message: 'Created.',
                }),
            );
        }

        if (method === 'put' && url.includes('/api/v1/timeline/comments/')) {
            const payload = requestBody(config) as CommentPayload;
            const match = url.match(/\/api\/v1\/timeline\/comments\/(\d+)$/);
            const target = match ? findComment(101, Number(match[1])) : null;

            if (!target) {
                return Promise.reject(
                    new AxiosError(
                        'Unexpected UI003 update target.',
                        AxiosError.ERR_BAD_REQUEST,
                        config,
                    ),
                );
            }

            const updated = { ...target, body: payload.body };
            serverItems[101] = serverItems[101]!.map((item) =>
                item.type === 'comment' && item.id === updated.id
                    ? updated
                    : item,
            );

            return Promise.resolve(
                response(config, { data: structuredClone(updated) }),
            );
        }

        if (method === 'delete' && url.includes('/api/v1/timeline/comments/')) {
            const match = url.match(/\/api\/v1\/timeline\/comments\/(\d+)$/);

            if (match) {
                const commentId = Number(match[1]);
                serverItems[101] = serverItems[101]!.filter(
                    (item) => item.type !== 'comment' || item.id !== commentId,
                );
            }

            return Promise.resolve(response(config, { message: 'Deleted.' }));
        }

        if (method === 'get' && url.includes('/api/v1/notifications/unread')) {
            return Promise.resolve(response(config, { data: [], count: 0 }));
        }

        return Promise.resolve(response(config, { data: [] }));
    };
}

http.defaults.adapter = requestAdapter();

function TimelineProbe() {
    const client = useQueryClient();
    const [view, setView] = useState<View>({ modelId: 101, revision: 0 });
    const [ready, setReady] = useState(false);
    const mutations = useCommentMutations('client', view.modelId);
    const create = mutations.create.mutateAsync;
    const reply = mutations.reply.mutateAsync;
    const update = mutations.update.mutateAsync;
    const remove = mutations.remove.mutateAsync;

    useLayoutEffect(() => {
        let cancelled = false;

        client.setQueryDefaults(timelineKeys.all, {
            retry: false,
            staleTime: Infinity,
        });

        queueMicrotask(() => {
            if (!cancelled) {
                setReady(true);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [client]);

    useLayoutEffect(() => {
        viewRef = view;
        const nextHarness: Harness = {
            client,
            getView: () => viewRef,
            setModelId: (modelId) => {
                setView((current) => ({ ...current, modelId }));
            },
            resetView: () => {
                setView((current) => ({
                    modelId: 101,
                    revision: current.revision + 1,
                }));
            },
            mutations: {
                create: (payload) => create(payload),
                reply: (commentId, payload) => reply({ commentId, payload }),
                update: (commentId, payload) => update({ commentId, payload }),
                remove: (commentId) => remove(commentId),
            },
        };
        harness = nextHarness;
        (window as WindowWithHarness).__uiTimelineCursorHarness = nextHarness;
    }, [client, create, remove, reply, update, view]);

    return (
        <>
            <div>
                <button
                    id="mutate-create"
                    type="button"
                    onClick={() => {
                        void harness?.mutations
                            .create({ body: '[UI003:created]' })
                            .catch(() => undefined);
                    }}
                >
                    Criar comentário de fixture
                </button>
                <button
                    id="mutate-reply"
                    type="button"
                    onClick={() => {
                        void harness?.mutations
                            .reply(1, { body: '[UI003:reply]' })
                            .catch(() => undefined);
                    }}
                >
                    Responder comentário de fixture
                </button>
                <button
                    id="mutate-update"
                    type="button"
                    onClick={() => {
                        void harness?.mutations
                            .update(1, { body: '[UI003:updated]' })
                            .catch(() => undefined);
                    }}
                >
                    Atualizar comentário de fixture
                </button>
                <button
                    id="mutate-delete"
                    type="button"
                    onClick={() => {
                        void harness?.mutations
                            .remove(1)
                            .catch(() => undefined);
                    }}
                >
                    Remover comentário de fixture
                </button>
                <button
                    id="switch-parent"
                    type="button"
                    onClick={() => harness?.setModelId(202)}
                >
                    Trocar pai
                </button>
            </div>
            {ready && (
                <ActivityTimeline
                    key={view.revision}
                    modelType="client"
                    modelId={view.modelId}
                    title="UI003 fixture timeline"
                    readOnly
                    allowAttachments={false}
                    pageSize={2}
                />
            )}
        </>
    );
}

const initialPage = {
    component: 'TimelineCursorFixture',
    props: {
        auth: {
            user: {
                id: 900003,
                email_verified_at: '2026-01-01T00:00:00.000000Z',
                must_change_password: false,
            },
        },
    },
    url: window.location.pathname,
    version: null,
    clearHistory: false,
    encryptHistory: false,
};

const pageComponent = Object.assign(TimelineProbe, {
    layout: [PrivateStateLayout],
});

void createInertiaApp({
    page: initialPage,
    resolve: async () => pageComponent,
    strictMode: true,
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
});

function assert(condition: unknown, message: string): asserts condition {
    if (!condition) {
        throw new Error(message);
    }
}

const delay = (ms = 12) => new Promise((resolve) => setTimeout(resolve, ms));

async function until(
    check: () => boolean,
    message: string,
    attempts = 160,
): Promise<void> {
    for (let attempt = 0; attempt < attempts; attempt++) {
        if (check()) {
            return;
        }

        await delay();
    }

    throw new Error(message);
}

function pageMarkers(): string[] {
    return Array.from(document.querySelectorAll('#app article'))
        .map(
            (article) =>
                article.textContent?.match(/\[UI003:[^\]]+\]/)?.[0] ?? null,
        )
        .filter((marker): marker is string => marker !== null);
}

function loadMoreButton(): HTMLButtonElement | null {
    return (
        (Array.from(document.querySelectorAll('#app button')).find(
            (button) => button.textContent?.trim() === 'Carregar mais',
        ) as HTMLButtonElement | undefined) ?? null
    );
}

function retryMoreButton(): HTMLButtonElement | null {
    return (
        (Array.from(document.querySelectorAll('#app button')).find(
            (button) => button.textContent?.trim() === 'Tentar novamente',
        ) as HTMLButtonElement | undefined) ?? null
    );
}

function dayHeadings(): string[] {
    return Array.from(document.querySelectorAll('#app section time'))
        .filter(
            (node) =>
                node.parentElement?.nextElementSibling?.matches(
                    'ul[role="list"]',
                ) === true,
        )
        .map((node) => node.textContent?.trim() ?? '');
}

function marker(item: TimelineItem): string {
    const text = item.type === 'comment' ? item.body : item.description;

    return text.match(/\[UI003:[^\]]+\]/)?.[0] ?? '';
}

function expectedLoadedMarkers(modelId: number, limit: number): string[] {
    return (serverItems[modelId] ?? [])
        .slice(-limit)
        .flatMap((item) => [
            marker(item),
            ...(item.type === 'comment'
                ? (item.replies ?? []).map((reply) => marker(reply))
                : []),
        ]);
}

function firstPageCursor(modelId = 101): string | null {
    return serverPage(modelId, null)?.next ?? null;
}

function click(button: HTMLButtonElement | null, label: string): void {
    assert(button, `Missing visible ${label} button.`);
    button.click();
}

function timelineRequests(modelId = 101): RequestRecord[] {
    return requests.filter(
        (request) =>
            request.method === 'get' &&
            request.url.includes(`/api/v1/timeline/client/${modelId}`),
    );
}

async function waitForInitialPage(marker: string): Promise<void> {
    await until(
        () => pageMarkers().includes(marker),
        `Initial timeline page did not render ${marker}.`,
    );
}

async function reset(): Promise<void> {
    assert(harness, 'The real Inertia private layout did not mount.');

    await harness.client.cancelQueries();

    for (const gate of gates) {
        if (!gate.settled) {
            gate.resolve({ data: [] });
        }
    }

    gates.splice(0, gates.length);
    requests.splice(0, requests.length);
    failNextCursor = null;
    holdNextCursor = null;
    holdCreateModelId = null;
    failNextFirstPageStatus = null;
    cursorAnchors.clear();
    cursorByAnchor.clear();
    cursorSequence = 0;
    serverItems = initialServerItems();

    harness.client.clear();
    await flushSync(() => {
        harness!.resetView();
    });
    await delay();
    await waitForInitialPage('[UI003:comment:1]');
}

function sameItems(actual: string[], expected: string[]): boolean {
    return JSON.stringify(actual) === JSON.stringify(expected);
}

async function run(): Promise<void> {
    const checks: Check[] = [];
    const runCase = async (
        name: string,
        test: () => Promise<void>,
    ): Promise<void> => {
        try {
            await reset();
            await test();
            checks.push({ name, status: 'passed' });
        } catch (error) {
            checks.push({ name, status: 'failed', error: String(error) });
        }

        result.textContent = JSON.stringify(
            { status: 'running', checks, requestCount, forbiddenNetwork },
            null,
            2,
        );
    };

    await runCase(
        'load-more-prepends-tied-items-and-keeps-days-unique',
        async () => {
            const firstPage = pageMarkers();
            const firstRequestCount = timelineRequests().length;
            const cursor = firstPageCursor();
            assert(
                cursor !== null,
                'The first page should contain older timeline items.',
            );
            assert(
                firstPage.length === 2,
                'The initial page should contain two rows.',
            );
            click(loadMoreButton(), 'Carregar mais');
            await until(
                () => pageMarkers().length === 4,
                'The second page did not prepend its items.',
            );
            const secondPage = pageMarkers();
            assert(
                sameItems(secondPage.slice(-2), firstPage),
                'Loading an older page changed the order of already-rendered items.',
            );
            assert(
                sameItems(
                    [...secondPage].sort(),
                    [...new Set(secondPage)].sort(),
                ),
                'The merged pages contain duplicate timeline markers.',
            );
            assert(
                timelineRequests().length === firstRequestCount + 1 &&
                    timelineRequests().at(-1)?.cursor === cursor,
                'The actual Axios request did not use the opaque next cursor.',
            );

            click(loadMoreButton(), 'Carregar mais');
            await until(
                () => pageMarkers().length === 5,
                'The final page did not render.',
            );
            assert(
                pageMarkers()[0] === '[UI003:comment:3]',
                'The oldest page was not prepended before the newer items.',
            );
            const headings = dayHeadings();
            assert(
                headings.length === 2,
                'Expected one heading for each of two days.',
            );
            assert(
                new Set(headings).size === headings.length,
                'A day split across pages rendered more than one day heading.',
            );
            assert(
                loadMoreButton() === null,
                'The load-more action should disappear only after the terminal cursor.',
            );
            const collidingIds = pageMarkers().filter((marker) =>
                marker.endsWith(':1]'),
            );
            assert(
                collidingIds.includes('[UI003:comment:1]') &&
                    collidingIds.includes('[UI003:activity:1]'),
                'Equal numeric IDs from comment and activity records collided.',
            );
        },
    );

    await runCase(
        'next-page-500-preserves-items-and-retries-same-cursor',
        async () => {
            const before = pageMarkers();
            const retryCursor = firstPageCursor();
            assert(
                retryCursor !== null,
                'The first page should contain older timeline items.',
            );
            failNextCursor = retryCursor;
            click(loadMoreButton(), 'Carregar mais');
            await until(
                () =>
                    gates.some(
                        (gate) => gate.cursor === retryCursor && !gate.settled,
                    ),
                'The controlled next-page request did not start.',
            );
            const failedGate = gates.find(
                (gate) => gate.cursor === retryCursor && !gate.settled,
            )!;
            failedGate.reject500();
            await until(
                () =>
                    document
                        .querySelector('#app')
                        ?.textContent?.includes('Erro ao carregar mais') ===
                    true,
                'The next-page failure did not show its distinct error state.',
            );
            assert(
                sameItems(pageMarkers(), before),
                'A failed next page changed already-rendered timeline items.',
            );
            const retry = retryMoreButton();
            assert(
                retry,
                'The next-page error did not expose its retry action.',
            );
            retry.click();
            await until(
                () => pageMarkers().length === 4,
                'Retrying the failed cursor did not render the next page.',
            );
            const cursorRequests = timelineRequests().filter(
                (request) => request.cursor === retryCursor,
            );
            assert(
                cursorRequests.length === 2,
                'Retry should issue exactly one new request for the failed cursor.',
            );
            assert(
                cursorRequests[0]?.cursor === cursorRequests[1]?.cursor,
                'Retry changed the failed opaque cursor.',
            );
            assert(
                pageMarkers().filter((marker) => marker === '[UI003:comment:1]')
                    .length === 1,
                'Retry duplicated an item from the successful first page.',
            );
        },
    );

    await runCase(
        'concurrent-load-more-clicks-share-one-pending-request',
        async () => {
            const cursor = firstPageCursor();
            assert(
                cursor !== null,
                'The first page should contain older timeline items.',
            );
            holdNextCursor = cursor;
            const before = timelineRequests().length;
            const button = loadMoreButton();
            click(button, 'Carregar mais');
            await until(
                () =>
                    gates.some(
                        (gate) => gate.cursor === cursor && !gate.settled,
                    ),
                'The deferred next-page request did not start.',
            );
            button?.click();
            await delay();
            const pending = gates.filter(
                (gate) => gate.cursor === cursor && !gate.settled,
            );
            assert(
                pending.length === 1 &&
                    timelineRequests().length === before + 1,
                'Concurrent load-more clicks started duplicate cursor requests.',
            );
            pending[0]!.resolve(
                timelineResponse(101, serverPage(101, cursor)!),
            );
            await until(
                () => pageMarkers().length === 4,
                'The single pending request did not complete the page.',
            );
            assert(
                pageMarkers().length === new Set(pageMarkers()).size,
                'Concurrent load-more completion duplicated rendered markers.',
            );
        },
    );

    await runCase(
        'mutation-after-two-pages-refetches-valid-page-data',
        async () => {
            click(loadMoreButton(), 'Carregar mais');
            await until(
                () => pageMarkers().length === 4,
                'Second page did not load.',
            );
            const beforeRequests = timelineRequests().length;
            click(
                document.getElementById(
                    'mutate-create',
                ) as HTMLButtonElement | null,
                'create mutation',
            );
            await until(
                () =>
                    timelineRequests().length >= beforeRequests + 2 &&
                    sameItems(pageMarkers(), expectedLoadedMarkers(101, 4)) &&
                    harness!.client
                        .getQueryCache()
                        .findAll({
                            queryKey: timelineKeys.subject('client', 101),
                        })
                        .every((query) => query.state.fetchStatus === 'idle'),
                'A successful mutation did not invalidate and refetch the loaded pages.',
            );
            assert(
                sameItems(pageMarkers(), [
                    '[UI003:activity:2]',
                    '[UI003:activity:1]',
                    '[UI003:comment:1]',
                    '[UI003:created]',
                ]),
                'Create refetch did not recompute the exact loaded page boundary.',
            );
            const queryStates = harness!.client
                .getQueryCache()
                .findAll({ queryKey: timelineKeys.subject('client', 101) });
            assert(
                queryStates.length > 0 &&
                    queryStates.every(
                        (query) => query.state.status === 'success',
                    ),
                'The actual QueryClient does not contain successful timeline data after refetch.',
            );
        },
    );

    for (const mutationCase of [
        { name: 'reply', buttonId: 'mutate-reply', marker: '[UI003:reply]' },
        {
            name: 'update',
            buttonId: 'mutate-update',
            marker: '[UI003:updated]',
        },
        { name: 'delete', buttonId: 'mutate-delete', marker: null },
    ]) {
        await runCase(
            `${mutationCase.name}-after-two-pages-invalidates-and-refetches`,
            async () => {
                click(loadMoreButton(), 'Carregar mais');
                await until(
                    () => pageMarkers().length === 4,
                    'Second page did not load before mutation.',
                );
                const beforeRequests = timelineRequests().length;
                const button = document.getElementById(mutationCase.buttonId);
                assert(
                    button instanceof HTMLButtonElement,
                    `Missing real ${mutationCase.name} mutation control.`,
                );
                button.click();
                await until(
                    () =>
                        timelineRequests().length >= beforeRequests + 2 &&
                        sameItems(
                            pageMarkers(),
                            expectedLoadedMarkers(101, 4),
                        ) &&
                        harness!.client
                            .getQueryCache()
                            .findAll({
                                queryKey: timelineKeys.subject('client', 101),
                            })
                            .every(
                                (query) => query.state.fetchStatus === 'idle',
                            ),
                    `The ${mutationCase.name} mutation did not refetch and settle both loaded pages.`,
                );
                const markers = pageMarkers();
                assert(
                    markers.length === new Set(markers).size,
                    `${mutationCase.name} refetch duplicated rendered items.`,
                );

                if (mutationCase.marker) {
                    assert(
                        markers.includes(mutationCase.marker),
                        `${mutationCase.name} response was not reflected by the authoritative refetch.`,
                    );
                }

                if (mutationCase.name === 'delete') {
                    assert(
                        !markers.includes('[UI003:comment:1]') &&
                            markers.includes('[UI003:activity:1]'),
                        'Delete must remove only the comment whose numeric ID collides with an activity ID.',
                    );
                }
            },
        );
    }

    await runCase(
        'pending-create-after-parent-switch-invalidates-only-origin-cache',
        async () => {
            const client = harness!.client;
            const originQueries = client
                .getQueryCache()
                .findAll({ queryKey: timelineKeys.subject('client', 101) });
            assert(originQueries.length > 0, 'Parent A query was not cached.');

            holdCreateModelId = 101;
            let mutationError: unknown;
            const mutationSettled = harness!.mutations
                .create({ body: '[UI003:pending-parent-a]' })
                .then(
                    () => undefined,
                    (error: unknown) => {
                        mutationError = error;
                    },
                );
            await until(
                () =>
                    gates.some(
                        (gate) =>
                            gate.modelId === 101 &&
                            gate.config.url?.endsWith(
                                '/api/v1/timeline/client/101/comments',
                            ) === true,
                    ),
                'The controlled parent-A create request did not start.',
            );
            const createRequest = requests.find(
                (request) =>
                    request.method === 'post' &&
                    request.url.endsWith(
                        '/api/v1/timeline/client/101/comments',
                    ),
            );
            assert(
                createRequest,
                'The real mutation did not send its request to parent A.',
            );
            const createGate = gates.find(
                (gate) =>
                    gate.modelId === 101 &&
                    gate.config.url?.endsWith(
                        '/api/v1/timeline/client/101/comments',
                    ) === true,
            )!;

            harness!.setModelId(202);
            await until(
                () => sameItems(pageMarkers(), ['[UI003:parent-b:1]']),
                'Parent B did not replace the visible parent-A timeline.',
            );
            const parentBRequestCount = timelineRequests(202).length;
            const parentBQueries = client
                .getQueryCache()
                .findAll({ queryKey: timelineKeys.subject('client', 202) });
            assert(parentBQueries.length > 0, 'Parent B query was not cached.');

            createGate.resolve({
                data: comment(99, '[UI003:pending-parent-a]', newerDate),
                message: 'Created.',
            });
            await mutationSettled;
            await until(
                () => originQueries.every((query) => query.state.isInvalidated),
                'The completed mutation did not invalidate its captured parent-A query.',
            );
            await delay();

            assert(
                mutationError === undefined,
                `The held parent-A mutation failed: ${String(mutationError)}`,
            );
            assert(
                timelineRequests(202).length === parentBRequestCount &&
                    parentBQueries.every((query) => !query.state.isInvalidated),
                'Completing the parent-A mutation refetched or invalidated parent B.',
            );
            assert(
                sameItems(pageMarkers(), ['[UI003:parent-b:1]']),
                'The parent-A mutation response crossed into parent B.',
            );
            assert(
                originQueries.every(
                    (query) =>
                        !JSON.stringify(query.state.data).includes(
                            '[UI003:pending-parent-a]',
                        ),
                ),
                'The mutation response patched cached parent-A pages before their refetch.',
            );
        },
    );

    for (const status of [500, 403]) {
        await runCase(
            `first-page-refetch-${status}-shows-full-timeline-error`,
            async () => {
                click(loadMoreButton(), 'Carregar mais');
                await until(
                    () => pageMarkers().length === 4,
                    'The second page did not load before refetch.',
                );
                failNextFirstPageStatus = status;
                await harness!.client
                    .refetchQueries(
                        {
                            queryKey: timelineKeys.subject('client', 101),
                            type: 'active',
                        },
                        { throwOnError: true },
                    )
                    .catch(() => undefined);
                await until(
                    () =>
                        document
                            .querySelector('#app')
                            ?.textContent?.includes(
                                'Erro ao carregar timeline',
                            ) === true,
                    `HTTP ${status} did not show the full timeline error state.`,
                );
                assert(
                    document
                        .querySelector('#app')
                        ?.textContent?.includes('Tentar novamente') === true,
                    `HTTP ${status} error state did not offer retry.`,
                );
                assert(
                    harness!.client
                        .getQueryCache()
                        .findAll({
                            queryKey: timelineKeys.subject('client', 101),
                        })
                        .some((query) => query.state.status === 'error'),
                    `HTTP ${status} did not become a failed timeline query.`,
                );

                if (status === 403) {
                    assert(
                        pageMarkers().length === 0,
                        'A forbidden refresh left previously loaded private timeline items visible.',
                    );
                }
            },
        );
    }

    await runCase(
        'third-cursor-403-hides-pages-and-retry-restarts-at-first-page',
        async () => {
            const initialPageMarkers = pageMarkers();
            const firstCursor = firstPageCursor();
            assert(
                firstCursor !== null,
                'The initial page should have a next cursor.',
            );
            click(loadMoreButton(), 'Carregar mais');
            await until(
                () => pageMarkers().length === 4,
                'The second page did not load before the forbidden cursor request.',
            );
            const forbiddenCursor = serverPage(101, firstCursor)?.next ?? null;
            assert(
                forbiddenCursor !== null,
                'The second page should contain a third-page cursor.',
            );
            failNextCursor = forbiddenCursor;
            click(loadMoreButton(), 'Carregar mais');
            await until(
                () =>
                    gates.some(
                        (gate) =>
                            gate.cursor === forbiddenCursor && !gate.settled,
                    ),
                'The third-page cursor request did not start.',
            );
            const deniedGate = gates.find(
                (gate) => gate.cursor === forbiddenCursor && !gate.settled,
            )!;
            deniedGate.reject403();
            await until(
                () =>
                    document
                        .querySelector('#app')
                        ?.textContent?.includes('Erro ao carregar timeline') ===
                    true,
                'The forbidden third cursor did not show the full timeline error.',
            );
            assert(
                pageMarkers().length === 0 && loadMoreButton() === null,
                'A forbidden cursor left private items or another load-more action visible.',
            );
            assert(
                document
                    .querySelector('#app')
                    ?.textContent?.includes('Tentar novamente') === true,
                'The full error state did not offer a retry.',
            );

            const requestsBeforeRetry = timelineRequests().length;
            click(retryMoreButton(), 'Tentar novamente');
            await until(
                () => timelineRequests().length > requestsBeforeRetry,
                'Retry after a forbidden cursor did not start again from the first page.',
            );
            await until(
                () =>
                    pageMarkers().length >= 2 &&
                    !document
                        .querySelector('#app')
                        ?.textContent?.includes('Erro ao carregar timeline'),
                'Retry after a forbidden cursor did not recover the timeline.',
            );
            const retryRequests = timelineRequests().slice(requestsBeforeRetry);
            assert(
                retryRequests[0]?.cursor === null,
                'Retry after a forbidden cursor did not begin with the first-page request.',
            );
            assert(
                sameItems(pageMarkers().slice(-2), initialPageMarkers),
                'Retry after a forbidden cursor did not restore the first-page items.',
            );
        },
    );

    await runCase(
        'changing-parent-aborts-pending-cursor-and-ignores-late-result',
        async () => {
            const cursor = firstPageCursor();
            assert(
                cursor !== null,
                'The first page should contain older timeline items.',
            );
            holdNextCursor = cursor;
            click(loadMoreButton(), 'Carregar mais');
            await until(
                () =>
                    gates.some(
                        (gate) =>
                            gate.modelId === 101 && gate.cursor === cursor,
                    ),
                'The old-parent cursor request did not start.',
            );
            const staleGate = gates.find(
                (gate) => gate.modelId === 101 && gate.cursor === cursor,
            )!;
            const switchButton = document.getElementById('switch-parent');
            assert(
                switchButton instanceof HTMLButtonElement,
                'Parent switch control is missing.',
            );
            switchButton.click();
            await until(
                () => pageMarkers().includes('[UI003:parent-b:1]'),
                'The new parent timeline did not load.',
            );
            assert(
                staleGate.config.signal?.aborted === true,
                'Changing parents did not abort the pending previous cursor request.',
            );
            staleGate.resolve(timelineResponse(101, serverPage(101, cursor)!));
            await delay();
            assert(
                pageMarkers().every((marker) =>
                    marker.startsWith('[UI003:parent-b:'),
                ),
                'A late response for the previous parent appeared in the new timeline.',
            );
            assert(
                timelineRequests(202).some(
                    (request) => request.cursor === null,
                ),
                'The current parent request did not start from its own first page.',
            );
        },
    );

    if (forbiddenNetwork > 0) {
        checks.push({
            name: 'no-native-network-attempts',
            status: 'failed',
            error: `${forbiddenNetwork} native network attempt(s) were blocked.`,
        });
    }

    result.textContent = JSON.stringify(
        {
            status: checks.every((check) => check.status === 'passed')
                ? 'passed'
                : 'failed',
            checks,
            requestCount,
            forbiddenNetwork,
        },
        null,
        2,
    );
}

runButton.addEventListener('click', () => {
    if (runButton instanceof HTMLButtonElement) {
        runButton.disabled = true;
    }

    void (async () => {
        try {
            await until(
                () => harness !== null,
                'PrivateStateLayout did not mount.',
            );
            await waitForInitialPage('[UI003:comment:1]');
            await run();
        } catch (error) {
            result.textContent = JSON.stringify(
                {
                    status: 'failed',
                    checks: [
                        {
                            name: 'fixture-startup',
                            status: 'failed',
                            error: String(error),
                        },
                    ],
                    requestCount,
                    forbiddenNetwork,
                },
                null,
                2,
            );
        } finally {
            if (runButton instanceof HTMLButtonElement) {
                runButton.disabled = false;
            }
        }
    })();
});
