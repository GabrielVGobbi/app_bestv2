import { createInertiaApp, router } from '@inertiajs/react';
import type {
    AxiosAdapter,
    AxiosResponse,
    InternalAxiosRequestConfig,
} from 'axios';
import { AxiosError, AxiosHeaders } from 'axios';
import { StrictMode, useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';

import { useExport } from '@/components/data-table/hooks/use-export';
import { DataTableExportStatus } from '@/components/data-table/shell/data-table-export-status';
import { DatabaseExportStatus } from '@/components/database-export/database-export-status';
import { useDatabaseExport } from '@/components/database-export/use-database-export';
import {
    PrivateStateLayout,
    usePrivateState,
} from '@/contexts/private-state-context';
import type { PrivateStateScope } from '@/contexts/private-state-context';
import { http } from '@/lib/http';
import type {
    DatabaseExportRecord,
    StoredDatabaseExport,
} from '@/types/database-export';
import type { ExportRecord, StoredExport } from '@/types/export';

type Failure = { status: number; message: string };
type StatusBehavior =
    | 'processing'
    | 'completed'
    | 'failed'
    | Failure
    | 'deferred';
type Gate = {
    uuid: string;
    signal: AbortSignal | undefined;
    resolve: (
        record: ExportRecord | DatabaseExportRecord | DatabaseExportRecord[],
    ) => void;
    reject: (status: number) => void;
    settled: boolean;
};
type RequestRecord = { method: string; url: string; uuid: string | null };
type Check = { name: string; status: 'passed' | 'failed'; error?: string };
type PrepareOptions = {
    exports?: StoredExport[];
    databaseExports?: StoredDatabaseExport[];
    exportConsumers?: number;
    statusByUuid?: Record<
        string,
        Array<'processing' | 'completed' | 'failed' | Failure | 'deferred'>
    >;
    databaseStatusByUuid?: Record<
        string,
        Array<'processing' | 'completed' | 'failed' | Failure | 'deferred'>
    >;
    history?: DatabaseExportRecord[];
    historySequence?: Array<DatabaseExportRecord[] | Failure | 'deferred'>;
    exportPost?: StatusBehavior;
    databasePost?: StatusBehavior;
    enabled?: boolean;
    rawExportStorage?: string;
    rawDatabaseStorage?: string;
};
type Harness = {
    prepare: (options?: PrepareOptions) => Promise<void>;
    tick: () => void;
    advanceClock: (milliseconds: number) => void;
    gates: Gate[];
    requests: RequestRecord[];
    allRequests: RequestRecord[];
    forbiddenNetwork: () => number;
    setExportConsumers: (count: number) => void;
    setAccount: (id: number) => Promise<void>;
};
type WindowWithHarness = Window & { __uiExportPollingHarness?: Harness };

const result = document.getElementById('fixture-result')!;
const runButton = document.getElementById('run-contracts')!;
const userId = 900006;
const exportStorageKey = `private-state:${userId}:data-table-exports`;
const databaseStorageKey = `private-state:${userId}:database-exports`;
const ids = {
    held: '10000000-0000-4000-8000-000000000001',
    shared: '10000000-0000-4000-8000-000000000002',
    downloaded: '10000000-0000-4000-8000-000000000003',
    history: '10000000-0000-4000-8000-000000000004',
    expired: '10000000-0000-4000-8000-000000000005',
    auth: '10000000-0000-4000-8000-000000000006',
    other: '10000000-0000-4000-8000-000000000007',
    created: '10000000-0000-4000-8000-000000000008',
    reload: '10000000-0000-4000-8000-000000000009',
};
const requests: RequestRecord[] = [];
const allRequests: RequestRecord[] = [];
const gates: Gate[] = [];
const intervalCallbacks = new Map<number, () => void>();
const fakeTimeouts = new Map<number, { due: number; callback: () => void }>();
const statusByUuid = new Map<string, StatusBehavior[]>();
const databaseStatusByUuid = new Map<string, StatusBehavior[]>();
const checks: Check[] = [];
let history: DatabaseExportRecord[] = [];
let historySequence: Array<DatabaseExportRecord[] | Failure | 'deferred'> = [];
let exportPost: StatusBehavior = 'processing';
let databasePost: StatusBehavior = 'processing';
let exportEnabled = true;
const exportControls = new Map<number, ReturnType<typeof useExport>>();
let databaseControl: ReturnType<typeof useDatabaseExport> | null = null;
let currentScope: PrivateStateScope | null = null;
const openedDownloads: string[] = [];
window.open = ((url?: string | URL) => {
    openedDownloads.push(String(url));

    return null;
}) as typeof window.open;
let consumers = 1;
let forbidden = 0;
let unexpectedRequests = 0;
let nextIntervalId = 1;
let logicalTime = 0;
let nextTimeoutId = -1;
let clockOffset = 0;
let fixtureHarness: Harness | null = null;
const nativeDateNow = Date.now.bind(Date);
Date.now = () => nativeDateNow() + clockOffset;

const nativeSetInterval = window.setInterval.bind(window);
const nativeClearInterval = window.clearInterval.bind(window);
const nativeSetTimeout = window.setTimeout.bind(window);
const nativeClearTimeout = window.clearTimeout.bind(window);
const navigation = performance.getEntriesByType('navigation')[0] as
    | PerformanceNavigationTiming
    | undefined;
document.getElementById('boot-context')!.textContent =
    'Navegação: ' + (navigation?.type ?? 'unknown');
window.setInterval = ((callback: TimerHandler, delay?: number) => {
    if (delay !== 3000 || typeof callback !== 'function') {
        return nativeSetInterval(callback, delay);
    }

    const id = nextIntervalId++;
    intervalCallbacks.set(id, callback as () => void);

    return id;
}) as typeof window.setInterval;
window.clearInterval = ((id: number) => {
    if (!intervalCallbacks.delete(id)) {
        nativeClearInterval(id);
    }
}) as typeof window.clearInterval;
window.setTimeout = ((
    callback: TimerHandler,
    delay?: number,
    ...args: unknown[]
) => {
    if (!delay || delay < 1000 || typeof callback !== 'function') {
        return nativeSetTimeout(callback, delay, ...args);
    }

    const id = nextTimeoutId--;
    fakeTimeouts.set(id, {
        due: logicalTime + delay,
        callback: callback as () => void,
    });

    return id;
}) as typeof window.setTimeout;
window.clearTimeout = ((id: number) => {
    if (!fakeTimeouts.delete(id)) {
        nativeClearTimeout(id);
    }
}) as typeof window.clearTimeout;

function denyNetwork(): never {
    forbidden++;

    throw new Error('UI-006 fixture forbids native network access.');
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
        statusText: status >= 400 ? 'Fixture failure' : 'OK',
        headers: new AxiosHeaders(),
        config,
    };
}

function rejectStatus(
    config: InternalAxiosRequestConfig,
    status: number,
    message: string,
): Promise<AxiosResponse> {
    const failure = response(config, { message }, status);

    return Promise.reject(
        new AxiosError(
            message,
            AxiosError.ERR_BAD_RESPONSE,
            config,
            undefined,
            failure,
        ),
    );
}

function exportRecord(
    uuid: string,
    status: ExportRecord['status'],
): ExportRecord {
    return {
        uuid,
        status,
        format: 'csv',
        file_name: `${uuid}.csv`,
        file_size: null,
        total_records: 10,
        processed_records: status === 'completed' ? 10 : 3,
        download_url:
            status === 'completed' ? `/api/v1/exports/${uuid}/download` : null,
        expires_at: new Date(Date.now() + 60_000).toISOString(),
        created_at: new Date().toISOString(),
        completed_at: status === 'completed' ? new Date().toISOString() : null,
    };
}

function databaseRecord(
    uuid: string,
    status: DatabaseExportRecord['status'],
): DatabaseExportRecord {
    return {
        uuid,
        status,
        export_type: 'full',
        tables: null,
        file_name: `${uuid}.sql`,
        file_size: null,
        download_url:
            status === 'completed'
                ? `/api/v1/database-exports/${uuid}/download`
                : null,
        expires_at: new Date(Date.now() + 60_000).toISOString(),
        created_at: new Date().toISOString(),
        completed_at: status === 'completed' ? new Date().toISOString() : null,
    };
}

function shiftStatus(
    source: Map<string, StatusBehavior[]>,
    uuid: string,
): StatusBehavior {
    return source.get(uuid)?.shift() ?? 'processing';
}

function deferredRecord(
    config: InternalAxiosRequestConfig,
    uuid: string,
): Promise<AxiosResponse> {
    return new Promise((resolve, reject) => {
        const gate: Gate = {
            uuid,
            signal: config.signal,
            settled: false,
            resolve(record) {
                if (gate.settled) {
                    return;
                }

                gate.settled = true;
                resolve(response(config, { data: record }));
            },
            reject(status) {
                if (gate.settled) {
                    return;
                }

                gate.settled = true;
                void rejectStatus(
                    config,
                    status,
                    'Deferred fixture failure',
                ).then(resolve, reject);
            },
        };
        gates.push(gate);
    });
}

function requestAdapter(): AxiosAdapter {
    return (config) => {
        const method = (config.method ?? 'get').toLowerCase();
        const url = config.url ?? '';
        const match = url.match(/\/api\/v1\/(?:database-)?exports\/([^/]+)$/);
        const uuid = match?.[1] ?? null;
        const request = { method, url, uuid };
        requests.push(request);
        allRequests.push(request);

        if (uuid && !Object.values(ids).includes(uuid)) {
            unexpectedRequests++;

            return rejectStatus(
                config,
                599,
                'UUID outside the finite fixture manifest.',
            );
        }

        if (
            method === 'get' &&
            [
                '/api/v1/notifications/unread',
                '/api/v1/notifications/unread-count',
            ].includes(url)
        ) {
            return Promise.resolve(response(config, { data: [], count: 0 }));
        }

        if (method === 'get' && url === '/api/v1/database-exports') {
            const next = historySequence.shift() ?? history;

            if (next === 'deferred') {
                return deferredRecord(config, 'history');
            }

            if (!Array.isArray(next)) {
                return rejectStatus(config, next.status, next.message);
            }

            return Promise.resolve(
                response(config, { data: structuredClone(next) }),
            );
        }

        if (
            method === 'get' &&
            uuid &&
            url.startsWith('/api/v1/database-exports/')
        ) {
            const behavior = shiftStatus(databaseStatusByUuid, uuid);

            if (behavior === 'deferred') {
                return deferredRecord(config, uuid);
            }

            if (typeof behavior === 'object') {
                return rejectStatus(config, behavior.status, behavior.message);
            }

            return Promise.resolve(
                response(config, { data: databaseRecord(uuid, behavior) }),
            );
        }

        if (method === 'get' && uuid && url.startsWith('/api/v1/exports/')) {
            const behavior = shiftStatus(statusByUuid, uuid);

            if (behavior === 'deferred') {
                return deferredRecord(config, uuid);
            }

            if (typeof behavior === 'object') {
                return rejectStatus(config, behavior.status, behavior.message);
            }

            return Promise.resolve(
                response(config, { data: exportRecord(uuid, behavior) }),
            );
        }

        if (method === 'post' && url === '/api/v1/exports') {
            if (exportPost === 'deferred') {
                return deferredRecord(config, ids.created);
            }

            if (typeof exportPost === 'object') {
                return rejectStatus(
                    config,
                    exportPost.status,
                    exportPost.message,
                );
            }

            return Promise.resolve(
                response(config, {
                    data: exportRecord(ids.created, 'pending'),
                }),
            );
        }

        if (method === 'post' && url === '/api/v1/database-exports') {
            if (databasePost === 'deferred') {
                return deferredRecord(config, ids.created);
            }

            if (typeof databasePost === 'object') {
                return rejectStatus(
                    config,
                    databasePost.status,
                    databasePost.message,
                );
            }

            return Promise.resolve(
                response(config, {
                    data: databaseRecord(ids.created, 'pending'),
                }),
            );
        }

        unexpectedRequests++;

        return rejectStatus(
            config,
            599,
            `Unexpected UI-006 request: ${method.toUpperCase()} ${url}`,
        );
    };
}

http.defaults.adapter = requestAdapter();

function ExportConsumer({ index }: { index: number }) {
    const state = useExport({
        modelClass: 'Fixture',
        columnsEndpoint: '/fixture',
        enabled: exportEnabled,
    });
    useLayoutEffect(() => {
        exportControls.set(index, state);

        return () => {
            if (exportControls.get(index) === state) {
                exportControls.delete(index);
            }
        };
    }, [index, state]);

    return (
        <section data-consumer={`export-${index}`}>
            <span>{`Export consumer ${index}: ${state.activeExports.map((item) => item.uuid).join(',')}`}</span>
            <div data-export-status={index}>
                <DataTableExportStatus
                    activeExports={state.activeExports}
                    onDownload={state.markAsDownloaded}
                />
            </div>
            {state.activeExports.map((item) => (
                <button
                    key={item.uuid}
                    type="button"
                    data-mark-export={item.uuid}
                    onClick={() => state.markAsDownloaded(item.uuid)}
                >
                    {`Marcar baixado ${item.uuid}`}
                </button>
            ))}
        </section>
    );
}

function DatabaseConsumer() {
    const state = useDatabaseExport();
    useLayoutEffect(() => {
        databaseControl = state;

        return () => {
            if (databaseControl === state) {
                databaseControl = null;
            }
        };
    }, [state]);

    return (
        <section data-consumer="database">
            <DatabaseExportStatus
                activeExports={state.activeExports}
                onDownload={state.markAsDownloaded}
            />
            <span>{`Database consumer: ${state.activeExports.map((item) => item.uuid).join(',')}`}</span>
            {state.activeExports.map((item) => (
                <button
                    key={item.uuid}
                    type="button"
                    data-mark-database-export={item.uuid}
                    onClick={() => state.markAsDownloaded(item.uuid)}
                >
                    {`Marcar dump baixado ${item.uuid}`}
                </button>
            ))}
        </section>
    );
}

function CaptureScope() {
    const { scope } = usePrivateState();
    useLayoutEffect(() => {
        currentScope = scope;

        return () => {
            if (currentScope === scope) {
                currentScope = null;
            }
        };
    }, [scope]);

    return null;
}

async function replaceAccount(id: number): Promise<void> {
    const auth = {
        user: {
            id,
            email_verified_at: '2026-01-01T00:00:00.000000Z',
            must_change_password: false,
        },
    };
    // Only the trusted HTTP response boundary is simulated; PageContext and cleanup remain real.
    document.dispatchEvent(
        new CustomEvent('inertia:beforeUpdate', {
            detail: { page: { props: { auth, errors: {} } } },
        }),
    );
    await new Promise<void>((resolve) =>
        router.replace({
            props: { auth, errors: {} },
            preserveState: true,
            preserveScroll: true,
            onFinish: () => resolve(),
        }),
    );
    await settle();
}

function FixturePage() {
    const [epoch, setEpoch] = useState(0);
    const [ready, setReady] = useState(true);
    const [revision, setRevision] = useState(0);

    useLayoutEffect(() => {
        fixtureHarness = {
            async prepare(options = {}) {
                flushSync(() => setReady(false));
                await Promise.resolve();
                await Promise.resolve();
                await until(
                    () => intervalCallbacks.size === 0,
                    'Unmounting the private export tree did not release its polling timers.',
                );
                clockOffset = 0;
                await replaceAccount(userId);
                logicalTime = 0;
                localStorage.setItem(
                    exportStorageKey,
                    options.rawExportStorage ??
                        JSON.stringify(options.exports ?? []),
                );
                localStorage.setItem(
                    databaseStorageKey,
                    options.rawDatabaseStorage ??
                        JSON.stringify(options.databaseExports ?? []),
                );
                statusByUuid.clear();
                databaseStatusByUuid.clear();

                for (const [uuid, values] of Object.entries(
                    options.statusByUuid ?? {},
                )) {
                    statusByUuid.set(uuid, [...values]);
                }

                for (const [uuid, values] of Object.entries(
                    options.databaseStatusByUuid ?? {},
                )) {
                    databaseStatusByUuid.set(uuid, [...values]);
                }

                history = options.history ?? [];
                historySequence = [...(options.historySequence ?? [])];
                exportPost = options.exportPost ?? 'processing';
                databasePost = options.databasePost ?? 'processing';
                exportEnabled = options.enabled ?? true;
                consumers = options.exportConsumers ?? 1;
                requests.length = 0;
                gates.length = 0;
                fakeTimeouts.clear();
                unexpectedRequests = 0;
                toast.dismiss();
                flushSync(() => {
                    setEpoch((value) => value + 1);
                    setReady(true);
                });
                await until(
                    () => document.querySelector('[data-epoch]') !== null,
                    'Private export consumers did not remount after fixture reset.',
                );
            },
            tick() {
                this.advanceClock(3000);

                for (const callback of [...intervalCallbacks.values()]) {
                    callback();
                }
            },
            advanceClock(milliseconds) {
                const target = logicalTime + milliseconds;

                while (true) {
                    const next = [...fakeTimeouts.entries()]
                        .filter(([, timer]) => timer.due <= target)
                        .sort((left, right) => left[1].due - right[1].due)[0];

                    if (!next) {
                        break;
                    }

                    const [id, timer] = next;
                    fakeTimeouts.delete(id);
                    clockOffset += timer.due - logicalTime;
                    logicalTime = timer.due;
                    timer.callback();
                }

                clockOffset += target - logicalTime;
                logicalTime = target;
            },
            gates,
            requests,
            allRequests,
            forbiddenNetwork: () => forbidden,
            setExportConsumers(count) {
                consumers = count;
                flushSync(() => setRevision((value) => value + 1));
            },
            setAccount: replaceAccount,
        };
        (window as WindowWithHarness).__uiExportPollingHarness = fixtureHarness;
    }, []);

    return ready ? (
        <PrivateStateLayout key={epoch}>
            <main data-epoch={epoch} data-revision={revision}>
                <CaptureScope />
                {Array.from({ length: consumers }, (_, index) => (
                    <ExportConsumer key={`export-${index}`} index={index + 1} />
                ))}
                <DatabaseConsumer />
            </main>
        </PrivateStateLayout>
    ) : null;
}

const pageComponent = FixturePage;
const initialPage = {
    component: 'Ui006ExportPollingFixture',
    props: {
        auth: {
            user: {
                id: userId,
                email_verified_at: '2026-01-01T00:00:00.000000Z',
                must_change_password: false,
            },
        },
    },
    url: window.location.pathname + window.location.search,
    version: null,
    clearHistory: false,
    encryptHistory: false,
};

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

async function until(
    predicate: () => boolean,
    message: string,
    timeoutMs = 1800,
): Promise<void> {
    const started = nativeDateNow();

    while (!predicate()) {
        if (nativeDateNow() - started >= timeoutMs) {
            throw new Error(message);
        }

        await new Promise((resolve) => window.setTimeout(resolve, 10));
    }
}

async function settle(): Promise<void> {
    await new Promise((resolve) => window.setTimeout(resolve, 30));
}

function exportItem(
    uuid: string,
    status: StoredExport['status'] = 'pending',
): StoredExport {
    return {
        uuid,
        status,
        modelClass: 'Fixture',
        format: 'csv',
        file_name: `${uuid}.csv`,
        download_url:
            status === 'completed' ? `/api/v1/exports/${uuid}/download` : null,
        total_records: 10,
        processed_records: 0,
        expires_at:
            status === 'completed'
                ? new Date(Date.now() + 60_000).toISOString()
                : null,
        createdAt: nativeDateNow(),
    };
}

function databaseItem(
    uuid: string,
    status: StoredDatabaseExport['status'] = 'pending',
): StoredDatabaseExport {
    return {
        uuid,
        status,
        export_type: 'full',
        file_name: `${uuid}.sql`,
        download_url:
            status === 'completed'
                ? `/api/v1/database-exports/${uuid}/download`
                : null,
        expires_at:
            status === 'completed'
                ? new Date(Date.now() + 60_000).toISOString()
                : null,
        createdAt: nativeDateNow(),
    };
}

async function runCase(
    name: string,
    callback: () => Promise<void>,
): Promise<void> {
    try {
        await callback();

        if (unexpectedRequests > 0) {
            throw new Error(
                `${unexpectedRequests} unexpected API request(s) were rejected by the fixture.`,
            );
        }

        checks.push({ name, status: 'passed' });
    } catch (error) {
        checks.push({ name, status: 'failed', error: String(error) });
    }
}

async function run(): Promise<void> {
    checks.length = 0;
    const harness = (window as WindowWithHarness).__uiExportPollingHarness;
    assert(harness, 'The private export fixture did not mount.');

    await runCase(
        'held-poll-request-does-not-overlap-across-ticks',
        async () => {
            await harness.prepare({
                exports: [exportItem(ids.held)],
                statusByUuid: { [ids.held]: ['deferred'] },
            });
            await settle();
            harness.tick();
            await until(
                () =>
                    harness.requests.filter((request) =>
                        request.url.endsWith(`/${ids.held}`),
                    ).length === 1,
                'First poll request did not start.',
            );
            harness.tick();
            harness.tick();
            await settle();
            const concurrent = harness.requests.filter((request) =>
                request.url.endsWith(`/${ids.held}`),
            ).length;
            assert(
                concurrent === 1,
                `Held poll overlapped; observed ${concurrent} requests before the response settled.`,
            );
            harness.gates[0]?.resolve(exportRecord(ids.held, 'processing'));
        },
    );

    await runCase(
        'two-export-consumers-share-one-poll-and-terminal-notification',
        async () => {
            await harness.prepare({
                exports: [exportItem(ids.shared)],
                exportConsumers: 2,
                statusByUuid: { [ids.shared]: ['completed', 'completed'] },
            });
            await settle();
            harness.tick();
            await until(
                () =>
                    harness.requests.some((request) =>
                        request.url.endsWith(`/${ids.shared}`),
                    ),
                'Shared export was not polled.',
            );
            await settle();
            const polls = harness.requests.filter((request) =>
                request.url.endsWith(`/${ids.shared}`),
            ).length;
            assert(
                polls === 1,
                `Two consumers issued ${polls} status polls for one export.`,
            );
            const terminalToasts = Array.from(
                document.querySelectorAll('[data-sonner-toast]'),
            ).filter((node) =>
                node.textContent?.includes('Exportação concluída!'),
            ).length;
            assert(
                terminalToasts === 1,
                `Expected one completion notification, found ${terminalToasts}.`,
            );
        },
    );

    await runCase(
        'downloaded-export-stays-hidden-and-keeps-timestamp',
        async () => {
            await harness.prepare({
                exports: [exportItem(ids.downloaded, 'completed')],
            });
            await until(
                () =>
                    document.querySelector(
                        `[data-mark-export="${ids.downloaded}"]`,
                    ) !== null,
                'Completed export did not render its local action.',
            );
            await settle();
            (
                document.querySelector(
                    `[data-mark-export="${ids.downloaded}"]`,
                ) as HTMLButtonElement
            ).click();
            await until(
                () =>
                    !localStorage
                        .getItem(exportStorageKey)
                        ?.includes(ids.downloaded) ||
                    JSON.parse(
                        localStorage.getItem(exportStorageKey) ?? '[]',
                    ).some(
                        (item: StoredExport) =>
                            item.uuid === ids.downloaded && item.downloadedAt,
                    ),
                'Downloaded timestamp was not persisted.',
            );
            const stored = JSON.parse(
                localStorage.getItem(exportStorageKey) ?? '[]',
            ) as StoredExport[];
            const downloadedAt = stored.find(
                (item) => item.uuid === ids.downloaded,
            )?.downloadedAt;
            assert(
                typeof downloadedAt === 'number',
                'markAsDownloaded did not record downloadedAt.',
            );
            assert(
                !document.querySelector(
                    `[data-mark-export="${ids.downloaded}"]`,
                ),
                'Downloaded export remained in the active list.',
            );
        },
    );

    await runCase(
        'database-history-reconciles-without-duplicating-or-clearing-download',
        async () => {
            const createdAt = Date.now() - 5000;
            const downloadedAt = Date.now() - 1000;
            const local = {
                ...databaseItem(ids.history, 'completed'),
                file_name: 'old-history-name.sql',
                download_url: '/api/v1/database-exports/old-history/download',
                downloadedAt,
                createdAt,
            };
            const server = databaseRecord(ids.history, 'completed');
            await harness.prepare({
                databaseExports: [local],
                history: [server],
            });
            await until(
                () =>
                    harness.requests.some(
                        (request) => request.url === '/api/v1/database-exports',
                    ),
                'Database export history was not requested.',
            );
            await settle();
            const stored = JSON.parse(
                localStorage.getItem(databaseStorageKey) ?? '[]',
            ) as StoredDatabaseExport[];
            assert(
                stored.filter((item) => item.uuid === ids.history).length === 1,
                'History reconciliation duplicated an existing export.',
            );
            assert(
                stored[0]?.downloadedAt === downloadedAt,
                'History reconciliation erased the local downloadedAt timestamp.',
            );
            assert(
                stored[0]?.file_name === server.file_name &&
                    stored[0]?.download_url === server.download_url &&
                    stored[0]?.status === server.status,
                'History reconciliation did not refresh server-owned status and download metadata.',
            );
        },
    );

    await runCase(
        'expired-terminal-record-is-not-polled-while-page-is-open',
        async () => {
            const stale = {
                ...exportItem(ids.expired, 'completed'),
                expires_at: new Date(Date.now() + 5000).toISOString(),
            };
            await harness.prepare({ exports: [stale] });
            await settle();
            harness.advanceClock(6000);
            harness.tick();
            await until(
                () =>
                    !JSON.parse(
                        localStorage.getItem(exportStorageKey) ?? '[]',
                    ).some((item: StoredExport) => item.uuid === ids.expired),
                'An export that expired while the page was open was not pruned.',
            );
            assert(
                !harness.requests.some((request) =>
                    request.url.endsWith(`/${ids.expired}`),
                ),
                'Expired terminal export was polled.',
            );
        },
    );

    await runCase('two-jobs-finish-in-one-owned-round', async () => {
        await harness.prepare({
            exports: [exportItem(ids.held), exportItem(ids.other)],
            statusByUuid: {
                [ids.held]: ['completed'],
                [ids.other]: ['completed'],
            },
        });
        await settle();
        harness.tick();
        await until(
            () =>
                [ids.held, ids.other].every((uuid) =>
                    JSON.parse(
                        localStorage.getItem(exportStorageKey) ?? '[]',
                    ).some(
                        (row: StoredExport) =>
                            row.uuid === uuid && row.status === 'completed',
                    ),
                ),
            'A round did not finish both queued jobs.',
        );
        assert(
            harness.requests.filter(
                (row) => row.uuid === ids.held || row.uuid === ids.other,
            ).length === 2,
            'A job was skipped or polled twice.',
        );
    });

    await runCase('disabled-consumer-does-not-poll-or-create', async () => {
        await harness.prepare({
            exports: [exportItem(ids.held)],
            enabled: false,
        });
        await settle();
        harness.tick();
        harness.tick();
        await settle();
        assert(
            !harness.requests.some((row) =>
                row.url.startsWith('/api/v1/exports'),
            ),
            'Disabled exports performed a request.',
        );
        const control = exportControls.get(1);
        assert(control, 'Disabled consumer did not mount.');
        const outcome = await control
            .startExport({
                modelClass: 'Fixture',
                format: 'csv',
                columns: ['id'],
            })
            .then(
                () => 'created',
                () => 'rejected',
            );
        assert(
            outcome === 'rejected' &&
                !harness.requests.some((row) => row.method === 'post'),
            'Disabled consumer created an export.',
        );
    });

    await runCase('transient-server-failure-retries-after-rest', async () => {
        await harness.prepare({
            exports: [exportItem(ids.held)],
            statusByUuid: {
                [ids.held]: [
                    { status: 500, message: 'Temporary failure' },
                    'completed',
                ],
            },
        });
        await settle();
        harness.tick();
        await until(
            () => harness.requests.some((row) => row.uuid === ids.held),
            'First failed poll did not start.',
        );
        await settle();
        assert(
            JSON.parse(localStorage.getItem(exportStorageKey) ?? '[]')[0]
                ?.status === 'pending',
            'Transient failure destroyed pending state.',
        );
        harness.tick();
        await until(
            () =>
                JSON.parse(localStorage.getItem(exportStorageKey) ?? '[]')[0]
                    ?.status === 'completed',
            'Serial retry did not complete.',
        );
        assert(
            harness.requests.filter((row) => row.uuid === ids.held).length ===
                2,
            'Retry overlapped or was duplicated.',
        );
    });

    for (const status of [404, 403]) {
        await runCase('status-' + status + '-stops-only-its-job', async () => {
            await harness.prepare({
                exports: [exportItem(ids.held)],
                statusByUuid: {
                    [ids.held]: [{ status, message: 'Access unavailable' }],
                },
            });
            await settle();
            harness.tick();
            await until(() => {
                const stored = JSON.parse(
                    localStorage.getItem(exportStorageKey) ?? '[]',
                ) as StoredExport[];

                return status === 404
                    ? stored.length === 0
                    : stored[0]?.status === 'failed' &&
                          stored[0]?.download_url === null;
            }, 'Unavailable job did not reach the explicit terminal state.');
            assert(
                currentScope?.isCurrent(),
                'One forbidden/missing job invalidated the whole account.',
            );
            const count = harness.requests.filter(
                (row) => row.uuid === ids.held,
            ).length;
            harness.tick();
            harness.tick();
            await settle();
            assert(
                harness.requests.filter((row) => row.uuid === ids.held)
                    .length === count,
                'Unavailable job kept polling.',
            );

            if (status === 403) {
                assert(
                    document.querySelectorAll('[data-sonner-toast]').length >=
                        1,
                    'Forbidden job gave no visible feedback.',
                );
            }
        });
    }

    await runCase(
        'empty-history-server-error-retries-and-restores',
        async () => {
            await harness.prepare({
                historySequence: [
                    { status: 500, message: 'Try later' },
                    [databaseRecord(ids.history, 'completed')],
                ],
            });
            await settle();
            assert(
                harness.requests.filter(
                    (row) => row.url === '/api/v1/database-exports',
                ).length === 1,
                'Initial history was duplicated.',
            );
            harness.tick();
            await until(
                () =>
                    databaseControl?.activeExports.some(
                        (row) => row.uuid === ids.history,
                    ) === true,
                'Empty history never retried after500.',
            );
            assert(
                harness.requests.filter(
                    (row) => row.url === '/api/v1/database-exports',
                ).length === 2,
                'History retry was not single flight.',
            );
        },
    );

    await runCase(
        'history-forbidden-clears-downloads-without-ending-account',
        async () => {
            await harness.prepare({
                databaseExports: [databaseItem(ids.history, 'completed')],
                historySequence: [
                    { status: 403, message: 'History forbidden' },
                ],
            });
            await until(
                () =>
                    (
                        JSON.parse(
                            localStorage.getItem(databaseStorageKey) ?? '[]',
                        ) as StoredDatabaseExport[]
                    ).every((row) => !row.download_url),
                'Forbidden history retained a usable URL.',
            );
            assert(
                currentScope?.isCurrent(),
                'Forbidden dump history ended the account.',
            );
            const count = harness.requests.filter(
                (row) => row.url === '/api/v1/database-exports',
            ).length;
            harness.tick();
            harness.tick();
            await settle();
            assert(
                harness.requests.filter(
                    (row) => row.url === '/api/v1/database-exports',
                ).length === count,
                'Forbidden history kept repeating.',
            );
        },
    );

    await runCase(
        'last-consumer-aborts-and-old-finally-cannot-restart',
        async () => {
            await harness.prepare({
                exports: [exportItem(ids.held)],
                statusByUuid: { [ids.held]: ['deferred', 'processing'] },
            });
            await settle();
            harness.tick();
            await until(
                () => harness.gates.some((gate) => gate.uuid === ids.held),
                'Retained poll did not start.',
            );
            const old = harness.gates.find((gate) => gate.uuid === ids.held)!;
            harness.setExportConsumers(0);
            await until(
                () => old.signal?.aborted === true,
                'Last consumer did not abort its request.',
            );
            harness.setExportConsumers(1);
            await settle();
            old.resolve(exportRecord(ids.held, 'completed'));
            await settle();
            assert(
                !JSON.parse(
                    localStorage.getItem(exportStorageKey) ?? '[]',
                ).some((row: StoredExport) => row.status === 'completed'),
                'Old completion repopulated the new activation.',
            );
            const count = harness.requests.filter(
                (row) => row.uuid === ids.held,
            ).length;
            harness.tick();
            await settle();
            assert(
                harness.requests.filter((row) => row.uuid === ids.held)
                    .length ===
                    count + 1,
                'Old finally canceled or duplicated the fresh activation timer.',
            );
        },
    );

    await runCase(
        'array-enum-storage-is-discarded-rather-than-stuck-visible',
        async () => {
            await harness.prepare({
                rawExportStorage: JSON.stringify([
                    { ...exportItem(ids.held), status: ['pending'] },
                ]),
            });
            await settle();
            assert(
                exportControls.get(1)?.activeExports.length === 0,
                'Array status was accepted as a private export and rendered without a valid polling status.',
            );
            const stored = JSON.parse(
                localStorage.getItem(exportStorageKey) ?? '[]',
            ) as StoredExport[];
            assert(
                stored.length === 0,
                'Invalid enum persisted in private storage.',
            );
            harness.tick();
            await settle();
            assert(
                !harness.requests.some((row) =>
                    row.url.startsWith('/api/v1/exports/'),
                ),
                'Invalid enum generated a status request.',
            );
        },
    );

    const invalidRows = [
        {
            name: 'export-format-array',
            kind: 'exports',
            value: { ...exportItem(ids.held), format: ['csv'] },
        },
        {
            name: 'dump-type-array',
            kind: 'dumps',
            value: { ...databaseItem(ids.history), export_type: ['full'] },
        },
        {
            name: 'future-created-at',
            kind: 'exports',
            value: {
                ...exportItem(ids.held),
                createdAt: nativeDateNow() + 60000,
            },
        },
        {
            name: 'negative-downloaded-at',
            kind: 'exports',
            value: { ...exportItem(ids.held), downloadedAt: -1 },
        },
        {
            name: 'future-downloaded-at',
            kind: 'exports',
            value: {
                ...exportItem(ids.held),
                downloadedAt: nativeDateNow() + 60000,
            },
        },
        {
            name: 'downloaded-before-creation',
            kind: 'exports',
            value: {
                ...exportItem(ids.held),
                downloadedAt: nativeDateNow() - 1000,
            },
        },
    ];

    for (const invalid of invalidRows) {
        await runCase('reject-storage-' + invalid.name, async () => {
            await harness.prepare(
                invalid.kind === 'exports'
                    ? { rawExportStorage: JSON.stringify([invalid.value]) }
                    : { rawDatabaseStorage: JSON.stringify([invalid.value]) },
            );
            await settle();
            const key =
                invalid.kind === 'exports'
                    ? exportStorageKey
                    : databaseStorageKey;
            const state =
                invalid.kind === 'exports'
                    ? exportControls.get(1)?.activeExports
                    : databaseControl?.activeExports;
            assert(
                state?.length === 0,
                'Malformed storage was rendered as an owned export.',
            );
            assert(
                JSON.parse(localStorage.getItem(key) ?? '[]').length === 0,
                'Malformed storage was retained or hidden instead of discarded.',
            );
            harness.tick();
            await settle();
            assert(
                !harness.requests.some(
                    (row) => row.uuid === ids.held || row.uuid === ids.history,
                ),
                'Malformed storage produced a status poll.',
            );
        });
    }

    await runCase(
        'malformed-storage-is-discarded-without-polling',
        async () => {
            await harness.prepare({
                rawExportStorage: '{"unexpected":"object"}',
            });
            await settle();
            harness.tick();
            await settle();
            assert(
                exportControls.get(1)?.activeExports.length === 0,
                'Malformed storage became a visible export.',
            );
            assert(
                !harness.requests.some((row) =>
                    row.url.startsWith('/api/v1/exports'),
                ),
                'Malformed storage produced a request.',
            );
        },
    );

    await runCase(
        'captured-download-link-cancels-default-after-invalidation',
        async () => {
            const item = exportItem(ids.downloaded, 'completed');
            await harness.prepare({ exports: [item] });
            await until(
                () =>
                    document.querySelector(
                        '[data-export-status="1"] button',
                    ) !== null,
                'Status trigger did not mount.',
            );
            (
                document.querySelector(
                    '[data-export-status="1"] button',
                ) as HTMLButtonElement
            ).click();
            await until(
                () =>
                    document.querySelector(
                        'a[href="' + item.download_url + '"]',
                    ) !== null,
                'Actual status download link did not open.',
            );
            const anchor = document.querySelector(
                'a[href="' + item.download_url + '"]',
            )!;
            let applicationPrevented = false;
            window.addEventListener(
                'click',
                (event) => {
                    applicationPrevented = event.defaultPrevented;
                    // Safety boundary: even a broken product must never navigate a fixture download.
                    event.preventDefault();
                },
                { once: true },
            );
            currentScope!.invalidate();
            anchor.dispatchEvent(
                new MouseEvent('click', { bubbles: true, cancelable: true }),
            );
            assert(
                applicationPrevented,
                'The status link failed to prevent its stale default navigation.',
            );
            assert(
                openedDownloads.length === 0,
                'Invalidation opened a download.',
            );
        },
    );

    await runCase(
        'captured-toast-download-does-not-open-after-invalidation',
        async () => {
            openedDownloads.length = 0;
            await harness.prepare({
                exports: [exportItem(ids.held)],
                statusByUuid: { [ids.held]: ['completed'] },
            });
            await settle();
            harness.tick();
            await until(
                () =>
                    Array.from(
                        document.querySelectorAll('[data-sonner-toast] button'),
                    ).some((node) => node.textContent === 'Baixar'),
                'Completion toast action did not render.',
            );
            const action = Array.from(
                document.querySelectorAll('[data-sonner-toast] button'),
            ).find(
                (node) => node.textContent === 'Baixar',
            ) as HTMLButtonElement;
            currentScope!.invalidate();
            action.click();
            await settle();
            assert(
                openedDownloads.length === 0,
                'A captured completion action opened its invalidated download.',
            );
        },
    );

    for (const mode of ['get-success', 'post-auth-failure']) {
        await runCase(
            'late-' + mode + '-from-A-does-not-enter-or-end-B',
            async () => {
                await harness.prepare({
                    exports:
                        mode === 'get-success' ? [exportItem(ids.held)] : [],
                    statusByUuid: { [ids.held]: ['deferred'] },
                    exportPost:
                        mode === 'post-auth-failure'
                            ? 'deferred'
                            : 'processing',
                });
                await settle();
                let pending: Promise<unknown> | undefined;

                if (mode === 'get-success') {
                    harness.tick();
                } else {
                    pending = exportControls
                        .get(1)!
                        .startExport({
                            modelClass: 'Fixture',
                            format: 'csv',
                            columns: ['id'],
                        })
                        .catch((error: unknown) => error);
                }

                await until(
                    () =>
                        harness.gates.some(
                            (gate) =>
                                gate.uuid ===
                                (mode === 'get-success'
                                    ? ids.held
                                    : ids.created),
                        ),
                    'AccountA request was not held.',
                );
                const old = harness.gates.find(
                    (gate) =>
                        gate.uuid ===
                        (mode === 'get-success' ? ids.held : ids.created),
                )!;
                const keyB = 'private-state:900007:data-table-exports';
                localStorage.setItem(
                    keyB,
                    JSON.stringify([exportItem(ids.other, 'completed')]),
                );
                await harness.setAccount(900007);
                await until(
                    () =>
                        currentScope?.userId === 900007 &&
                        exportControls
                            .get(1)
                            ?.activeExports.some(
                                (row) => row.uuid === ids.other,
                            ) === true,
                    'Actual PageContext did not restore accountB.',
                );
                assert(
                    old.signal?.aborted === true,
                    'AccountA transport was not aborted.',
                );

                if (mode === 'get-success') {
                    old.resolve(exportRecord(ids.held, 'completed'));
                } else {
                    old.reject(401);
                }

                await pending;
                await settle();
                assert(
                    currentScope?.userId === 900007 && currentScope.isCurrent(),
                    'Late accountA failure ended accountB.',
                );
                assert(
                    !localStorage.getItem(exportStorageKey),
                    'AccountA storage was restored after switching.',
                );
                const rows = JSON.parse(
                    localStorage.getItem(keyB) ?? '[]',
                ) as StoredExport[];
                assert(
                    rows.length === 1 && rows[0].uuid === ids.other,
                    'AccountA response entered accountB state.',
                );
                assert(
                    document.querySelectorAll('[role="alert"]').length === 0,
                    'Stale accountA response showed recovery in accountB.',
                );
            },
        );
    }

    await runCase('late-history-does-not-overwrite-a-new-post', async () => {
        await harness.prepare({ historySequence: ['deferred'] });
        await until(
            () => harness.gates.some((gate) => gate.uuid === 'history'),
            'History was not held.',
        );
        const old = harness.gates.find((gate) => gate.uuid === 'history')!;
        await settle();
        await databaseControl!.startExport({ export_type: 'full' });
        const stale = {
            ...databaseRecord(ids.created, 'completed'),
            file_name: 'stale-index.sql',
        };
        old.resolve([stale]);
        await settle();
        const stored = JSON.parse(
            localStorage.getItem(databaseStorageKey) ?? '[]',
        ) as StoredDatabaseExport[];
        assert(
            stored.some(
                (row) =>
                    row.uuid === ids.created &&
                    row.file_name !== 'stale-index.sql' &&
                    row.status !== 'completed',
            ),
            'Old index overwrote the newer POST result.',
        );
    });

    for (const flow of ['status419', 'post401', 'history419']) {
        await runCase(
            'auth-' + flow + '-shows-recovery-and-stops',
            async () => {
                await harness.prepare({
                    exports: flow === 'status419' ? [exportItem(ids.auth)] : [],
                    statusByUuid: {
                        [ids.auth]: [{ status: 419, message: 'Expired' }],
                    },
                    exportPost:
                        flow === 'post401'
                            ? { status: 401, message: 'Expired' }
                            : 'processing',
                    historySequence:
                        flow === 'history419'
                            ? [{ status: 419, message: 'Expired' }]
                            : [],
                });
                await settle();

                if (flow === 'status419') {
                    harness.tick();
                }

                if (flow === 'post401') {
                    await exportControls
                        .get(1)!
                        .startExport({
                            modelClass: 'Fixture',
                            format: 'csv',
                            columns: ['id'],
                        })
                        .catch(() => undefined);
                }

                await until(
                    () =>
                        Array.from(
                            document.querySelectorAll('[role="alert"]'),
                        ).some((node) =>
                            node.textContent?.includes(
                                'Sua sessão precisa ser revalidada.',
                            ),
                        ),
                    'Auth failure did not expose recovery.',
                );
                const count = harness.requests.length;
                harness.tick();
                harness.tick();
                await settle();
                assert(
                    harness.requests.length === count,
                    'Expired session continued requesting.',
                );
                assert(
                    !localStorage.getItem(exportStorageKey) &&
                        !localStorage.getItem(databaseStorageKey),
                    'Auth failure retained account storage.',
                );
            },
        );
    }

    await runCase(
        'auth-expiry-stops-private-polling-and-shows-visible-recovery',
        async () => {
            await harness.prepare({
                exports: [exportItem(ids.auth)],
                statusByUuid: {
                    [ids.auth]: [{ status: 401, message: 'expired' }],
                },
            });
            await settle();
            harness.tick();
            await until(
                () =>
                    harness.requests.some((request) =>
                        request.url.endsWith(`/${ids.auth}`),
                    ),
                'Auth-expired export was not polled.',
            );
            await until(
                () =>
                    Array.from(
                        document.querySelectorAll('[role="alert"]'),
                    ).some((node) =>
                        node.textContent?.includes(
                            'Sua sessão precisa ser revalidada.',
                        ),
                    ),
                '401 did not show the visible session-revalidation alert.',
            );
            assert(
                !localStorage.getItem(exportStorageKey),
                'Expired session left private export state in local storage.',
            );
            const requestCount = harness.requests.length;
            harness.tick();
            await settle();
            assert(
                harness.requests.length === requestCount,
                'Polling continued after the private session expired.',
            );
        },
    );

    if (harness.forbiddenNetwork() > 0) {
        checks.push({
            name: 'no-native-network-attempts',
            status: 'failed',
            error: `${harness.forbiddenNetwork()} native network attempts were blocked.`,
        });
    }

    result.textContent = JSON.stringify(
        {
            status: checks.every((check) => check.status === 'passed')
                ? 'passed'
                : 'failed',
            checks,
            requests: harness.requests,
            allRequests: harness.allRequests,
            forbiddenNetwork: harness.forbiddenNetwork(),
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
                () => fixtureHarness !== null,
                'PrivateStateLayout did not mount.',
            );
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
                    forbiddenNetwork: forbidden,
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

document.getElementById('prepare-reload')!.addEventListener('click', () => {
    void (async () => {
        await fixtureHarness!.prepare({ exports: [exportItem(ids.reload)] });
        document.getElementById('manual-result')!.textContent =
            'Exportação pendente preparada: ' + ids.reload;
    })();
});
document.getElementById('manual-tick')!.addEventListener('click', () => {
    fixtureHarness!.tick();
});
document.getElementById('manual-audit')!.addEventListener('click', () => {
    document.getElementById('manual-result')!.textContent = JSON.stringify(
        {
            requests: allRequests,
            scopeUser: currentScope?.userId,
            scopeCurrent: currentScope?.isCurrent(),
            exports: exportControls.get(1)?.activeExports,
            dumps: databaseControl?.activeExports,
            forbidden,
            unexpectedRequests,
        },
        null,
        2,
    );
});
