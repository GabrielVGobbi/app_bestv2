import { createInertiaApp } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { QueryClient } from '@tanstack/react-query';
import type {
    AxiosAdapter,
    AxiosResponse,
    InternalAxiosRequestConfig,
} from 'axios';
import { AxiosError, AxiosHeaders } from 'axios';
import type { ComponentType } from 'react';
import { StrictMode } from 'react';
import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { toast } from 'sonner';

import { DataTable } from '@/components/data-table';
import type {
    DataTableBulkAction,
    DataTableBulkAggregate,
    DataTableProps,
} from '@/components/data-table';
import { PrivateStateLayout } from '@/contexts/private-state-context';
import { http } from '@/lib/http';
import UsersIndex from '@/pages/app/users/index';
import type { Paginated } from '@/types/pagination';
import type { User } from '@/types/user';

type Dataset = 'A' | 'B';
type Row = { uuid: string; name: string; amount: number };
type Resolution = { rows: Row[]; unavailableIds: string[] };
type Resolver = (
    ids: readonly string[],
    options: { signal: AbortSignal },
) => Promise<Resolution>;
type FixtureTableProps = DataTableProps<Row> & { selectionResolver?: Resolver };
type View = { dataset: Dataset; resolverEnabled: boolean; revision: number };
type Gate = {
    config: InternalAxiosRequestConfig;
    settled: boolean;
    resolve: (data: unknown) => void;
};
type RequestRecord = {
    method: string;
    url: string;
    params: Record<string, unknown>;
    data?: unknown;
    signal: AbortSignal | undefined;
};
type ResolverMode =
    | 'success'
    | 'missing'
    | 'status500'
    | 'status403'
    | 'deferred';
type Harness = {
    client: QueryClient;
    unmount: () => void;
    mount: (dataset: Dataset, resolverEnabled: boolean) => void;
    setDataset: (dataset: Dataset) => void;
    showUsersPage: () => void;
};
type BulkDeleteMode = 'success' | 'status500' | 'deferred';
type BulkDeleteGate = {
    config: InternalAxiosRequestConfig;
    settled: boolean;
    resolve: (data: unknown) => void;
    reject: (status: number) => void;
};
type Check = { name: string; status: 'passed' | 'failed'; error?: string };
type WindowWithHarness = Window & { __uiSelectionHarness?: Harness };

const result = document.getElementById('fixture-result')!;
const runButton = document.getElementById('run-contracts')!;
const fixtureUserId = 900004;
const queryKey = (dataset: Dataset) => `ui-selection-${dataset}`;
const storageKey = (dataset: Dataset) =>
    `private-state:${fixtureUserId}:datatable-selection:${queryKey(dataset)}`;
const userSelectionStorageKey = `private-state:${fixtureUserId}:datatable-selection:users`;
const requests: RequestRecord[] = [];
const actionCalls: string[][] = [];
const clearCallbacks: Array<() => void> = [];
const aggregateCalls: number[] = [];
const resolverCalls: Array<{ ids: string[]; signal: AbortSignal }> = [];
const gates: Gate[] = [];
const userResolverBatches: string[][] = [];
const userResolverResponses: Array<{ ids: string[]; rows: User[] }> = [];
const bulkDeleteRequests: string[][] = [];
const bulkDeleteGates: BulkDeleteGate[] = [];
let forbiddenNetwork = 0;
let requestCount = 0;
let activeDataset: Dataset = 'A';
let resolverMode: ResolverMode = 'success';
let serverRows: Record<Dataset, Row[]> = initialRows();
let userServerRows: User[] = initialUsers();
let bulkDeleteMode: BulkDeleteMode = 'success';
let harness: Harness | null = null;

function initialRows(): Record<Dataset, Row[]> {
    return {
        A: [
            { uuid: 'shared-uuid', name: 'A first', amount: 10 },
            { uuid: 'a-second', name: 'A second', amount: 20 },
        ],
        B: [
            { uuid: 'shared-uuid', name: 'B collision', amount: 100 },
            { uuid: 'b-second', name: 'B second', amount: 200 },
        ],
    };
}

function initialUsers(count = 120): User[] {
    return Array.from({ length: count }, (_, index) => {
        const id = index + 1;
        const uuid = `00000000-0000-4000-8000-${String(id).padStart(12, '0')}`;

        return {
            id,
            uuid,
            name: `UI004 User ${String(id).padStart(3, '0')}`,
            email: `ui004-user-${id}@example.test`,
            email_verified_at: null,
            must_change_password: false,
            can: { update: true, delete: true },
            created_at: '2026-01-01T00:00:00.000000Z',
            updated_at: '2026-01-01T00:00:00.000000Z',
        };
    });
}

function denyNetwork(): never {
    forbiddenNetwork++;

    throw new Error('UI-004 fixture forbids native network access.');
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
    message = `Synthetic UI004 HTTP ${status} failure.`,
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

function paginatedUsers(
    rows: User[],
    page: number,
    perPage: number,
): Paginated<User> {
    const start = (page - 1) * perPage;
    const pageRows = rows.slice(start, start + perPage);

    return {
        data: structuredClone(pageRows),
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: page,
            from: pageRows.length ? start + 1 : null,
            last_page: Math.max(1, Math.ceil(rows.length / perPage)),
            per_page: perPage,
            to: pageRows.length ? start + pageRows.length : null,
            total: rows.length,
        },
    };
}

function arrayOfStrings(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.map(String);
    }

    if (typeof value === 'string') {
        return [value];
    }

    return [];
}

function usersUuidFilter(
    params: Record<string, unknown>,
):
    | { kind: 'absent' }
    | { kind: 'valid'; uuids: string[] }
    | { kind: 'invalid' } {
    if (!Object.hasOwn(params, 'filter')) {
        return { kind: 'absent' };
    }

    const filter = params.filter;

    if (!filter || typeof filter !== 'object' || Array.isArray(filter)) {
        return { kind: 'invalid' };
    }

    const uuidFilter = (filter as Record<string, unknown>).uuid;

    if (
        !uuidFilter ||
        typeof uuidFilter !== 'object' ||
        Array.isArray(uuidFilter)
    ) {
        return { kind: 'invalid' };
    }

    const typedUuidFilter = uuidFilter as Record<string, unknown>;

    if (
        typedUuidFilter.operator !== 'in' ||
        !Array.isArray(typedUuidFilter.value) ||
        !typedUuidFilter.value.every(
            (value): value is string => typeof value === 'string',
        )
    ) {
        return { kind: 'invalid' };
    }

    return { kind: 'valid', uuids: typedUuidFilter.value };
}

function bodyOf(config: InternalAxiosRequestConfig): Record<string, unknown> {
    if (typeof config.data === 'string') {
        try {
            return JSON.parse(config.data) as Record<string, unknown>;
        } catch {
            return {};
        }
    }

    return (config.data ?? {}) as Record<string, unknown>;
}

function deleteGate(
    config: InternalAxiosRequestConfig,
): Promise<AxiosResponse> {
    return new Promise((resolve, reject) => {
        const gate: BulkDeleteGate = {
            config,
            settled: false,
            resolve(data) {
                if (gate.settled) {
                    return;
                }

                gate.settled = true;
                resolve(response(config, data));
            },
            reject(status) {
                if (gate.settled) {
                    return;
                }

                gate.settled = true;
                void rejectStatus(
                    config,
                    status,
                    `UI004 bulk delete fixture rejected with HTTP ${status}.`,
                ).catch(reject);
            },
        };

        bulkDeleteGates.push(gate);
    });
}

function bulkDeleteSuccess(ids: string[], config: InternalAxiosRequestConfig) {
    const requested = new Set(ids);
    userServerRows = userServerRows.filter((user) => !requested.has(user.uuid));

    return Promise.resolve(
        response(config, {
            data: { deleted_ids: ids, deleted_count: ids.length },
        }),
    );
}

function deferredResponse(
    config: InternalAxiosRequestConfig,
): Promise<AxiosResponse> {
    return new Promise((resolve) => {
        const gate: Gate = {
            config,
            settled: false,
            resolve(data) {
                if (gate.settled) {
                    return;
                }

                gate.settled = true;
                resolve(response(config, data));
            },
        };
        gates.push(gate);
    });
}

function paramsOf(config: InternalAxiosRequestConfig): Record<string, unknown> {
    return (config.params ?? {}) as Record<string, unknown>;
}

function paginatedRows(
    rows: Row[],
    page: number,
    perPage: number,
): Paginated<Row> {
    const start = (page - 1) * perPage;
    const pageRows = rows.slice(start, start + perPage);

    return {
        data: structuredClone(pageRows),
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: page,
            from: pageRows.length ? start + 1 : null,
            last_page: Math.max(1, Math.ceil(rows.length / perPage)),
            per_page: perPage,
            to: pageRows.length ? start + pageRows.length : null,
            total: rows.length,
        },
    };
}

function requestAdapter(): AxiosAdapter {
    return (config) => {
        requestCount++;
        const method = (config.method ?? 'get').toLowerCase();
        const url = config.url ?? '';
        const params = paramsOf(config);
        const data = config.data;
        requests.push({ method, url, params, data, signal: config.signal });

        if (method === 'get' && url === '/api/v1/notifications/unread') {
            return Promise.resolve(response(config, { data: [], count: 0 }));
        }

        if (method === 'get' && url === '/api/v1/ui-selection/resolve') {
            const idsValue = params.ids;
            const ids = Array.isArray(idsValue)
                ? idsValue.map(String)
                : typeof idsValue === 'string'
                  ? [idsValue]
                  : [];

            if (resolverMode === 'status500') {
                return rejectStatus(config, 500);
            }

            if (resolverMode === 'status403') {
                return rejectStatus(config, 403);
            }

            if (resolverMode === 'deferred') {
                return deferredResponse(config);
            }

            const rows = serverRows[activeDataset].filter((row) =>
                ids.includes(row.uuid),
            );
            const unavailableIds =
                resolverMode === 'missing'
                    ? ids.filter((id) => !rows.some((row) => row.uuid === id))
                    : ids.filter((id) => !rows.some((row) => row.uuid === id));

            return Promise.resolve(response(config, { rows, unavailableIds }));
        }

        if (method === 'get' && url.startsWith('/api/v1/ui-selection/')) {
            const dataset = url.endsWith('/B') ? 'B' : 'A';
            const page = Number(params.page ?? 1);
            const perPage = Number(params.per_page ?? 1);

            return Promise.resolve(
                response(
                    config,
                    paginatedRows(serverRows[dataset], page, perPage),
                ),
            );
        }

        if (method === 'get' && url === '/api/v1/users') {
            const uuidFilter = usersUuidFilter(params);

            if (uuidFilter.kind === 'invalid') {
                return rejectStatus(
                    config,
                    599,
                    'Malformed UI004 users UUID filter; expected operator "in" and a string value array.',
                );
            }

            const requestedUuids =
                uuidFilter.kind === 'valid' ? uuidFilter.uuids : [];
            const isResolverRequest = uuidFilter.kind === 'valid';
            const matchingUsers = isResolverRequest
                ? userServerRows.filter((user) =>
                      requestedUuids.includes(user.uuid),
                  )
                : userServerRows;
            const page = Number(params.page ?? 1);
            const perPage = Number(params.per_page ?? 30);

            if (isResolverRequest) {
                userResolverBatches.push(requestedUuids);
                userResolverResponses.push({
                    ids: requestedUuids,
                    rows: structuredClone(matchingUsers),
                });
            }

            return Promise.resolve(
                response(config, paginatedUsers(matchingUsers, page, perPage)),
            );
        }

        if (method === 'delete' && url === '/api/v1/users/bulk') {
            const ids = arrayOfStrings(bodyOf(config).ids);
            bulkDeleteRequests.push(ids);

            if (bulkDeleteMode === 'status500') {
                return rejectStatus(
                    config,
                    500,
                    'UI004 bulk delete fixture rejected with HTTP 500.',
                );
            }

            if (bulkDeleteMode === 'deferred') {
                return deleteGate(config);
            }

            return bulkDeleteSuccess(ids, config);
        }

        return rejectStatus(
            config,
            599,
            `Unexpected UI004 fixture API request: ${method.toUpperCase()} ${url}`,
        );
    };
}

http.defaults.adapter = requestAdapter();

const action: DataTableBulkAction<Row> = {
    id: 'fixture-action',
    label: 'Executar ação',
    onClick(rows, clearSelection) {
        actionCalls.push(rows.map((row) => `${row.uuid}:${row.name}`));
        clearCallbacks.push(clearSelection);
    },
};

const aggregate: DataTableBulkAggregate<Row> = {
    id: 'fixture-total',
    label: 'Total',
    fn(rows) {
        aggregateCalls.push(rows.length);

        return rows.reduce((sum, row) => sum + row.amount, 0);
    },
};

const ResolvableDataTable =
    DataTable as unknown as ComponentType<FixtureTableProps>;

async function resolveSelection(
    ids: readonly string[],
    options: { signal: AbortSignal },
): Promise<Resolution> {
    resolverCalls.push({ ids: [...ids], signal: options.signal });
    const response = await http.get<Resolution>(
        '/api/v1/ui-selection/resolve',
        {
            params: { ids: [...ids] },
            signal: options.signal,
        },
    );

    return response.data;
}

function SelectionProbe() {
    const client = useQueryClient();
    const [defaultsReady, setDefaultsReady] = useState(false);
    const [mounted, setMounted] = useState(true);
    const [usersPageVisible, setUsersPageVisible] = useState(false);
    const [view, setView] = useState<View>({
        dataset: 'A',
        resolverEnabled: false,
        revision: 0,
    });

    useLayoutEffect(() => {
        let cancelled = false;

        for (const dataset of ['A', 'B'] as const) {
            client.setQueryDefaults([queryKey(dataset)], {
                retry: false,
                staleTime: Infinity,
            });
        }

        client.setQueryDefaults(['users'], {
            retry: false,
            staleTime: Infinity,
        });

        queueMicrotask(() => {
            if (!cancelled) {
                setDefaultsReady(true);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [client]);

    useLayoutEffect(() => {
        harness = {
            client,
            unmount: () => setMounted(false),
            mount: (dataset, resolverEnabled) => {
                activeDataset = dataset;
                setUsersPageVisible(false);
                setView((current) => ({
                    dataset,
                    resolverEnabled,
                    revision: current.revision + 1,
                }));
                setMounted(true);
            },
            setDataset: (dataset) => {
                activeDataset = dataset;
                setView((current) => ({ ...current, dataset }));
            },
            showUsersPage: () => {
                setUsersPageVisible(true);
            },
        };
        (window as WindowWithHarness).__uiSelectionHarness = harness;
    }, [client]);

    const tableProps: FixtureTableProps = {
        queryKey: queryKey(view.dataset),
        endpoint: `/api/v1/ui-selection/${view.dataset}`,
        mode: 'paginated',
        columns: [
            { id: 'uuid', accessorKey: 'uuid', header: 'UUID' },
            { id: 'name', accessorKey: 'name', header: 'Nome' },
            { id: 'amount', accessorKey: 'amount', header: 'Valor' },
        ],
        keyExtractor: (row) => row.uuid,
        features: { selection: true },
        bulkActions: [action],
        bulkAggregates: [aggregate],
        initialPerPage: 1,
        persistState: false,
        selectionResolver: view.resolverEnabled ? resolveSelection : undefined,
    };

    return (
        <>
            <div>
                <button
                    id="dataset-a"
                    type="button"
                    onClick={() => harness?.setDataset('A')}
                >
                    Dataset A
                </button>
                <button
                    id="dataset-b"
                    type="button"
                    onClick={() => harness?.setDataset('B')}
                >
                    Dataset B
                </button>
            </div>
            {defaultsReady && usersPageVisible ? (
                <UsersIndex
                    can={{ create: false, update: true, delete: true }}
                />
            ) : defaultsReady && mounted ? (
                <ResolvableDataTable key={view.revision} {...tableProps} />
            ) : null}
        </>
    );
}

const initialPage = {
    component: 'Ui004SelectionFixture',
    props: {
        auth: {
            user: {
                id: fixtureUserId,
                email_verified_at: '2026-01-01T00:00:00.000000Z',
                must_change_password: false,
            },
        },
    },
    url: '/__ui_selection',
    version: null,
    clearHistory: false,
    encryptHistory: false,
};

const pageComponent = Object.assign(SelectionProbe, {
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

const delay = (ms = 15) => new Promise((resolve) => setTimeout(resolve, ms));

async function until(
    check: () => boolean,
    message: string,
    attempts = 120,
): Promise<void> {
    for (let attempt = 0; attempt < attempts; attempt++) {
        if (check()) {
            return;
        }

        await delay();
    }

    throw new Error(message);
}

function visibleRows(): Row[] {
    return Array.from(document.querySelectorAll('#app tbody tr'))
        .map((row) => {
            const cells = row.querySelectorAll('td');

            return {
                uuid: cells[1]?.textContent?.trim() ?? '',
                name: cells[2]?.textContent?.trim() ?? '',
                amount: Number(cells[3]?.textContent?.trim() ?? 0),
            };
        })
        .filter((row) => row.uuid !== '');
}

function rowCheckbox(): HTMLButtonElement | null {
    return document.querySelector(
        '#app tbody button[aria-label="Selecionar linha"]',
    );
}

function checkboxSelected(): boolean {
    return rowCheckbox()?.getAttribute('aria-checked') === 'true';
}

function actionButton(): HTMLButtonElement | null {
    return (
        (Array.from(document.querySelectorAll('#app button')).find(
            (button) => button.textContent?.trim() === action.label,
        ) as HTMLButtonElement | undefined) ?? null
    );
}

function clearButton(): HTMLButtonElement | null {
    return document.querySelector<HTMLButtonElement>(
        '#app button[aria-label="Limpar seleção"]',
    );
}

function pageButton(direction: 'previous' | 'next'): HTMLButtonElement | null {
    const pageLabel = Array.from(document.querySelectorAll('#app div')).find(
        (element) =>
            /^Página \d+ de \d+$/.test(element.textContent?.trim() ?? ''),
    );
    const buttons = pageLabel?.parentElement?.querySelectorAll('button');

    return (
        buttons?.item(
            direction === 'next' ? buttons.length - 1 : buttons.length - 2,
        ) ?? null
    );
}

function selectedLabel(): string {
    return [
        document.querySelector('#app')?.textContent,
        document.querySelector('[role="dialog"]')?.textContent,
        ...Array.from(document.querySelectorAll('[data-sonner-toast]')).map(
            (toast) => toast.textContent,
        ),
    ]
        .filter(Boolean)
        .join(' ')
        .replace(/(\d)(selecionados?)/gi, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim();
}

function exactButton(label: string): HTMLButtonElement | null {
    return (
        (Array.from(document.querySelectorAll('body button')).find(
            (button) =>
                button.textContent?.replace(/\s+/g, ' ').trim() === label,
        ) as HTMLButtonElement | undefined) ?? null
    );
}

function selectionCounterText(): string {
    return (
        clearButton()?.parentElement?.parentElement?.firstElementChild
            ?.textContent ?? ''
    )
        .replace(/\s+/g, ' ')
        .trim();
}

function selectedCount(): number {
    const match = selectionCounterText().match(
        /^\s*(\d+)\s*selecionados?\s*$/i,
    );

    return match ? Number(match[1]) : 0;
}

function userCheckbox(name: string): HTMLButtonElement | null {
    const row = Array.from(document.querySelectorAll('#app tbody tr')).find(
        (candidate) => candidate.textContent?.includes(name),
    );

    return (
        row?.querySelector<HTMLButtonElement>(
            'button[aria-label="Selecionar linha"]',
        ) ?? null
    );
}

function userRowsVisible(): string[] {
    return Array.from(document.querySelectorAll('#app tbody tr'))
        .map((row) => row.textContent?.replace(/\s+/g, ' ').trim() ?? '')
        .filter((text) => text.includes('UI004 User'));
}

async function waitForUser(name: string): Promise<void> {
    await until(
        () => userRowsVisible().some((row) => row.includes(name)),
        `User row "${name}" did not render.`,
    );
}

async function waitForRow(uuid: string): Promise<void> {
    await until(
        () => visibleRows().some((row) => row.uuid === uuid),
        `Row ${uuid} did not render.`,
    );
}

async function waitForRowName(name: string): Promise<void> {
    await until(
        () => visibleRows().some((row) => row.name === name),
        `Row named "${name}" did not render.`,
    );
}

async function reset(
    options: {
        selectedIds?: string[];
        resolverEnabled?: boolean;
        dataset?: Dataset;
        rawSelection?: string;
    } = {},
): Promise<void> {
    assert(
        harness,
        'PrivateStateLayout or the selection harness did not mount.',
    );
    const dataset = options.dataset ?? 'A';
    const client = harness.client;

    toast.dismiss();

    if (document.querySelector('[data-sonner-toast]')) {
        await until(
            () => document.querySelector('[data-sonner-toast]') === null,
            'A previous fixture toast did not dismiss.',
        );
    }

    for (const gate of gates) {
        if (!gate.settled) {
            gate.resolve({ rows: [], unavailableIds: [] });
        }
    }

    gates.splice(0, gates.length);

    for (const gate of bulkDeleteGates) {
        if (!gate.settled) {
            gate.reject(500);
        }
    }

    bulkDeleteGates.splice(0, bulkDeleteGates.length);
    await client.cancelQueries();
    flushSync(() => harness!.unmount());
    client.clear();
    localStorage.removeItem(storageKey('A'));
    localStorage.removeItem(storageKey('B'));
    localStorage.removeItem(userSelectionStorageKey);

    if (options.selectedIds) {
        localStorage.setItem(
            storageKey(dataset),
            JSON.stringify(options.selectedIds),
        );
    } else if (options.rawSelection !== undefined) {
        localStorage.setItem(storageKey(dataset), options.rawSelection);
    }

    requests.splice(0, requests.length);
    actionCalls.splice(0, actionCalls.length);
    clearCallbacks.splice(0, clearCallbacks.length);
    aggregateCalls.splice(0, aggregateCalls.length);
    resolverCalls.splice(0, resolverCalls.length);
    userResolverBatches.splice(0, userResolverBatches.length);
    userResolverResponses.splice(0, userResolverResponses.length);
    bulkDeleteRequests.splice(0, bulkDeleteRequests.length);
    resolverMode = 'success';
    bulkDeleteMode = 'success';
    activeDataset = dataset;
    serverRows = initialRows();
    userServerRows = initialUsers();
    flushSync(() => harness!.mount(dataset, options.resolverEnabled ?? false));
    await waitForRowName(dataset === 'A' ? 'A first' : 'B collision');

    if (options.selectedIds?.includes('shared-uuid')) {
        await until(checkboxSelected, 'Restored row selection did not appear.');
    }
}

async function resetUsers(options: {
    selectedIds: string[];
    userCount?: number;
}): Promise<void> {
    await reset();
    const client = harness!.client;

    await client.cancelQueries();
    flushSync(() => harness!.unmount());
    client.clear();
    userServerRows = initialUsers(options.userCount ?? 120);
    requests.splice(0, requests.length);
    userResolverBatches.splice(0, userResolverBatches.length);
    userResolverResponses.splice(0, userResolverResponses.length);
    bulkDeleteRequests.splice(0, bulkDeleteRequests.length);
    bulkDeleteMode = 'success';
    localStorage.setItem(
        userSelectionStorageKey,
        JSON.stringify(options.selectedIds),
    );
    harness!.showUsersPage();
    await waitForUser('UI004 User 001');

    if (options.selectedIds.length > 0) {
        await until(
            () => selectedCount() === options.selectedIds.length,
            `The ${options.selectedIds.length}-ID Users selection was not restored; counter text was "${selectionCounterText()}" (parsed count ${selectedCount()}).`,
        );
    }
}

function same(actual: unknown, expected: unknown): boolean {
    return JSON.stringify(actual) === JSON.stringify(expected);
}

async function run(): Promise<void> {
    const checks: Check[] = [];
    requestCount = 0;
    forbiddenNetwork = 0;
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
        'restored-partial-selection-resolver-completes-exact-rows',
        async () => {
            await reset({
                selectedIds: ['shared-uuid', 'a-second'],
                resolverEnabled: true,
            });
            assert(
                visibleRows().length === 1,
                'The first page should load exactly one row.',
            );
            assert(
                actionCalls.length === 0,
                'An action ran before user confirmation.',
            );
            const button = actionButton();
            assert(button, 'The bulk action is missing for restored IDs.');
            button.click();
            await until(
                () => resolverCalls.length === 1,
                'The resolver was not called.',
            );
            await until(
                () => actionCalls.length === 1,
                'Resolved action did not run.',
            );
            assert(
                same(resolverCalls[0]?.ids, ['shared-uuid', 'a-second']),
                'The resolver did not receive the complete selected ID set.',
            );
            assert(
                same(actionCalls[0], [
                    'shared-uuid:A first',
                    'a-second:A second',
                ]),
                'The action did not receive exactly the freshly resolved rows.',
            );
            assert(
                aggregateCalls.length > 0 &&
                    aggregateCalls.every((length) => length === 2) &&
                    selectedLabel().includes('30'),
                'An aggregate evaluated an incomplete selected row set.',
            );
        },
    );

    await runCase(
        'without-resolver-incomplete-ids-wait-for-another-page',
        async () => {
            await reset({ selectedIds: ['shared-uuid', 'a-second'] });
            const incompleteButton = actionButton();
            assert(incompleteButton, 'The selected action bar is missing.');

            if (!incompleteButton.disabled) {
                incompleteButton.click();
                await delay();
            }

            const partialRan =
                actionCalls.length > 0 || aggregateCalls.includes(1);
            const next = pageButton('next');
            assert(next, 'The second-page control is missing.');
            next.click();
            await waitForRow('a-second');
            await until(
                checkboxSelected,
                'Second-page restored selection did not appear.',
            );
            actionButton()?.click();
            await until(
                () => actionCalls.length > 0,
                'Complete selection action did not run.',
            );
            assert(
                !partialRan,
                'The action or aggregate operated on a partial row set.',
            );
            assert(
                same(actionCalls.at(-1), [
                    'shared-uuid:A first',
                    'a-second:A second',
                ]),
                'Visiting the second page did not complete the exact selected row set.',
            );
        },
    );

    await runCase(
        'snapshot-clear-keeps-ids-added-or-reselected-after-action',
        async () => {
            await reset({ selectedIds: ['shared-uuid'] });
            actionButton()?.click();
            await until(
                () => clearCallbacks.length === 1,
                'Action did not publish its clear callback.',
            );
            const clearSnapshot = clearCallbacks[0]!;
            rowCheckbox()?.click();
            await until(
                () => !checkboxSelected(),
                'Selected row did not toggle off.',
            );
            rowCheckbox()?.click();
            await until(
                checkboxSelected,
                'Reselected row did not become selected.',
            );
            const next = pageButton('next');
            assert(next, 'The second-page control is missing.');
            next.click();
            await waitForRow('a-second');
            rowCheckbox()?.click();
            await until(
                checkboxSelected,
                'Newly added second-page selection did not appear.',
            );
            clearSnapshot();
            await delay(350);
            assert(
                checkboxSelected(),
                'A stale clear callback removed a row selected after its action snapshot.',
            );
            assert(
                same(
                    JSON.parse(
                        localStorage.getItem(storageKey('A')) ?? '[]',
                    ).sort(),
                    ['a-second', 'shared-uuid'],
                ),
                'A stale clear callback removed newly selected IDs from persistent storage.',
            );
        },
    );

    await runCase(
        'dataset-a-b-a-isolates-storage-and-colliding-ids',
        async () => {
            await reset({ selectedIds: ['shared-uuid'] });
            await until(
                checkboxSelected,
                'Dataset A selection was not restored.',
            );
            actionButton()?.click();
            await until(
                () => clearCallbacks.length === 1,
                'Dataset A action did not publish a clear callback.',
            );
            const clearDatasetA = clearCallbacks[0]!;
            localStorage.setItem(storageKey('B'), JSON.stringify(['b-second']));
            harness!.setDataset('B');
            await waitForRowName('B collision');
            const collisionSelectedInB = checkboxSelected();
            const next = pageButton('next');
            assert(next, 'Dataset B second-page control is missing.');
            next.click();
            await waitForRowName('B second');
            const bSecondRestored = checkboxSelected();

            if (!checkboxSelected()) {
                rowCheckbox()?.click();
                await until(
                    checkboxSelected,
                    'Dataset B second row could not be selected.',
                );
            }

            clearDatasetA();
            await delay(350);
            const bSelectionAfterStaleClear = checkboxSelected();
            harness!.setDataset('A');
            await until(
                () =>
                    visibleRows().some(
                        (row) =>
                            row.name === 'A first' || row.name === 'A second',
                    ),
                'Returning to dataset A did not render one of its own pages.',
            );

            if (visibleRows().some((row) => row.name === 'A second')) {
                const previous = pageButton('previous');
                assert(previous, 'Dataset A previous-page control is missing.');
                previous.click();
                await waitForRowName('A first');
            }

            const aSelectionRestored = checkboxSelected();
            const storageIndependent =
                JSON.parse(
                    localStorage.getItem(storageKey('A')) ?? '[]',
                ).includes('shared-uuid') &&
                JSON.parse(
                    localStorage.getItem(storageKey('B')) ?? '[]',
                ).includes('b-second');

            assert(
                !collisionSelectedInB,
                'Dataset A selection leaked onto the colliding row in dataset B.',
            );
            assert(
                bSecondRestored,
                'Dataset B selection was not restored independently.',
            );
            assert(
                bSelectionAfterStaleClear,
                'Dataset A clear callback changed dataset B selection.',
            );
            assert(
                aSelectionRestored,
                'Returning to A did not restore A’s own selection.',
            );
            assert(
                storageIndependent,
                'Dataset selection storage keys were not kept independent.',
            );
        },
    );

    await runCase('malformed-selection-storage-is-ignored', async () => {
        await reset({ rawSelection: '{not-json' });
        assert(!checkboxSelected(), 'Malformed localStorage selected a row.');
        assert(
            actionButton() === null,
            'Malformed localStorage exposed bulk actions.',
        );
    });

    await runCase(
        'late-resolver-response-after-dataset-switch-is-ignored',
        async () => {
            await reset({
                selectedIds: ['shared-uuid', 'a-second'],
                resolverEnabled: true,
            });
            resolverMode = 'deferred';
            actionButton()?.click();
            await until(
                () => gates.length === 1,
                'The delayed resolver request did not start.',
            );
            const gate = gates[0]!;
            harness!.setDataset('B');
            await waitForRowName('B collision');
            assert(
                gate.config.signal?.aborted === true,
                'Dataset switch did not abort A resolution.',
            );
            gate.resolve({
                rows: [
                    { uuid: 'shared-uuid', name: 'A first late', amount: 10 },
                    { uuid: 'a-second', name: 'A second late', amount: 20 },
                ],
                unavailableIds: [],
            });
            await delay();
            assert(
                actionCalls.length === 0,
                'A late resolver response triggered an action.',
            );
            assert(
                visibleRows().every((row) => row.name.startsWith('B ')),
                'A late resolver response contaminated dataset B.',
            );
        },
    );

    for (const status of [500, 403] as const) {
        await runCase(
            `resolver-${status}-keeps-ids-and-shows-error`,
            async () => {
                await reset({
                    selectedIds: ['shared-uuid', 'a-second'],
                    resolverEnabled: true,
                });
                resolverMode = status === 500 ? 'status500' : 'status403';
                actionButton()?.click();
                await until(
                    () => resolverCalls.length === 1,
                    'The fresh resolution request did not start.',
                );
                await until(
                    () =>
                        document.querySelector('#app [role="alert"]') !==
                            null ||
                        document.querySelector('[data-sonner-toast]') !== null,
                    `HTTP ${status} did not show a selection error.`,
                );
                assert(
                    actionCalls.length === 0,
                    `HTTP ${status} still ran the partial action.`,
                );
                assert(
                    selectedLabel().includes('2 selecionados') ||
                        selectedLabel().includes('2 selecionado'),
                    `HTTP ${status} cleared the selected IDs after resolver failure.`,
                );
                assert(
                    same(
                        JSON.parse(
                            localStorage.getItem(storageKey('A')) ?? '[]',
                        ),
                        ['shared-uuid', 'a-second'],
                    ),
                    `HTTP ${status} removed persisted IDs after resolver failure.`,
                );
            },
        );
    }

    await runCase('missing-id-stays-blocked-until-explicit-clear', async () => {
        await reset({
            selectedIds: ['shared-uuid', 'removed-uuid'],
            resolverEnabled: true,
        });
        resolverMode = 'missing';
        actionButton()?.click();
        await until(
            () => resolverCalls.length === 1,
            'Missing-ID resolution did not start.',
        );
        await delay();
        assert(
            actionCalls.length === 0,
            'A missing ID allowed a partial bulk action.',
        );
        assert(
            selectedLabel().includes('removed-uuid') ||
                selectedLabel().toLowerCase().includes('indisponível') ||
                selectedLabel().toLowerCase().includes('indisponivel'),
            'The removed ID was not reported as unavailable.',
        );
        clearButton()?.click();
        await until(
            () => actionButton() === null,
            'Explicitly clearing the unavailable selection did not clear the selection bar.',
        );
        assert(
            !localStorage.getItem(storageKey('A')),
            'Explicit clear left unavailable IDs persisted.',
        );
    });

    await runCase(
        'fresh-preflight-replaces-stale-cached-row-before-action',
        async () => {
            await reset({
                selectedIds: ['shared-uuid'],
                resolverEnabled: true,
            });
            serverRows.A[0] = {
                ...serverRows.A[0]!,
                name: 'A first fresh',
                amount: 77,
            };
            actionButton()?.click();
            await until(
                () => actionCalls.length === 1,
                'Freshly validated action did not run.',
            );
            assert(
                resolverCalls.length === 1,
                'The action did not run a fresh preflight.',
            );
            assert(
                same(actionCalls[0], ['shared-uuid:A first fresh']),
                'The action used a stale cached row instead of freshly resolved data.',
            );
        },
    );

    await runCase(
        'users-101-selection-resolves-in-two-batches-with-visible-cap',
        async () => {
            const ids = initialUsers(101).map((user) => user.uuid);
            await resetUsers({ selectedIds: ids, userCount: 101 });
            await until(
                () => selectedLabel().includes('101 selecionados'),
                'The restored 101-ID selection was not shown in full.',
            );
            const reviewButton = exactButton('Revisar seleção');
            assert(
                reviewButton,
                'The over-limit selection did not offer a review action.',
            );
            reviewButton.click();
            await until(
                () => userResolverResponses.length === 2,
                'The 101 selected IDs did not finish resolving in two API batches.',
            );
            await until(
                () =>
                    selectedLabel().includes('Seleção revisada.') &&
                    !selectedLabel().includes('Revisando seleção...'),
                'The completed 101-ID review did not reach its terminal UI state.',
            );
            const batchLengths = userResolverBatches
                .map((batch) => batch.length)
                .sort((left, right) => left - right);
            const responseLengths = userResolverResponses
                .map((batch) => batch.rows.length)
                .sort((left, right) => left - right);
            const resolvedIds = userResolverBatches.flat().sort();
            const deleteButton = exactButton('Excluir');
            const visibleText = selectedLabel().toLowerCase();

            assert(
                same(batchLengths, [1, 100]),
                'The resolver did not use batches of 100 and 1.',
            );
            assert(
                same(responseLengths, [1, 100]),
                'Both resolver batches did not return every selected user.',
            );
            assert(
                same(resolvedIds, [...ids].sort()),
                'Batched resolver requests did not preserve all 101 selected UUIDs.',
            );
            assert(
                deleteButton?.disabled === true &&
                    visibleText.includes(
                        'selecione até 100 registros para executar esta ação.',
                    ),
                'The visible 100-record limit did not block bulk deletion.',
            );
            assert(
                bulkDeleteRequests.length === 0,
                'Reviewing an over-limit selection started a delete request.',
            );
        },
    );

    await runCase(
        'users-partial-loaded-selection-opens-exact-confirmation',
        async () => {
            const users = initialUsers(101);
            await resetUsers({
                selectedIds: [users[0]!.uuid, users[100]!.uuid],
                userCount: 101,
            });
            assert(
                !userRowsVisible().some((row) =>
                    row.includes('UI004 User 101'),
                ),
                'The second selected user should still be outside the loaded page.',
            );
            const deleteButton = exactButton('Excluir');
            assert(deleteButton, 'The Users bulk-delete action is missing.');
            deleteButton.click();
            await until(
                () => userResolverBatches.length === 1,
                'Users selection was not freshly resolved before confirmation.',
            );
            await until(
                () => selectedLabel().includes('Excluir 2 usuários'),
                'The confirmation did not include both selected users.',
            );
            assert(
                same(userResolverBatches[0], [
                    users[0]!.uuid,
                    users[100]!.uuid,
                ]),
                'The preflight did not resolve the exact persisted UUID set.',
            );
            assert(
                exactButton('Excluir 2 usuários') !== null,
                'The exact two-user confirmation action is missing.',
            );
            assert(
                bulkDeleteRequests.length === 0,
                'Opening the confirmation dialog started a delete request.',
            );
        },
    );

    await runCase(
        'users-bulk-delete-500-keeps-dialog-and-selection-for-single-flight-retry',
        async () => {
            const users = initialUsers(2);
            const ids = users.map((user) => user.uuid);
            await resetUsers({ selectedIds: ids, userCount: 2 });
            exactButton('Excluir')?.click();
            await until(
                () => selectedLabel().includes('Excluir 2 usuários'),
                'The two-user confirmation dialog did not open.',
            );
            bulkDeleteMode = 'deferred';
            exactButton('Excluir 2 usuários')?.click();
            await until(
                () => bulkDeleteGates.length === 1,
                'The bulk DELETE request did not reach the controlled HTTP adapter.',
            );
            exactButton('Excluir 2 usuários')?.click();
            await delay();
            assert(
                bulkDeleteRequests.length === 1,
                'A pending confirmation started more than one bulk DELETE.',
            );
            assert(
                userRowsVisible().some((row) =>
                    row.includes('UI004 User 001'),
                ) &&
                    userRowsVisible().some((row) =>
                        row.includes('UI004 User 002'),
                    ),
                'Rows were removed before the bulk DELETE succeeded.',
            );
            bulkDeleteGates[0]!.reject(500);
            await until(
                () =>
                    selectedLabel().includes(
                        'UI004 bulk delete fixture rejected with HTTP 500.',
                    ) ?? false,
                'The HTTP 500 failure was not surfaced to the user.',
            );
            assert(
                selectedLabel().includes('Excluir 2 usuários') &&
                    same(
                        JSON.parse(
                            localStorage.getItem(userSelectionStorageKey) ??
                                '[]',
                        ),
                        ids,
                    ),
                'The failure closed the dialog or cleared the selected UUIDs.',
            );
            assert(
                userRowsVisible().length === 2,
                'The failed delete optimistically removed one or more users.',
            );

            bulkDeleteMode = 'success';
            exactButton('Excluir 2 usuários')?.click();
            await until(
                () => bulkDeleteRequests.length === 2,
                'Retry did not issue exactly one new bulk DELETE.',
            );
            assert(
                same(bulkDeleteRequests[0], ids) &&
                    same(bulkDeleteRequests[1], ids),
                'The retry changed or truncated the confirmed UUID set.',
            );
            await until(
                () =>
                    !selectedLabel().includes('Excluir 2 usuários') &&
                    !localStorage.getItem(userSelectionStorageKey),
                'Successful retry did not close the dialog and clear its captured selection.',
            );
            await until(
                () => userRowsVisible().length === 0,
                'The successful response did not refresh the deleted users.',
            );
        },
    );

    await runCase(
        'users-revoked-delete-capability-is-rechecked-before-confirmation',
        async () => {
            const user = initialUsers(1)[0]!;
            await resetUsers({ selectedIds: [user.uuid], userCount: 1 });
            exactButton('Excluir')?.click();
            await until(
                () => selectedLabel().includes('Excluir 1 usuários'),
                'The initial authorized user did not reach confirmation.',
            );
            userServerRows[0]!.can!.delete = false;
            exactButton('Excluir 1 usuários')?.click();
            await until(
                () => userResolverResponses.length >= 2,
                'The confirmation did not finish re-resolving current delete authority.',
            );
            await until(
                () =>
                    Array.from(
                        document.querySelectorAll(
                            '[role="dialog"] [role="alert"] [data-slot="alert-description"]',
                        ),
                    ).some(
                        (description) =>
                            description.textContent?.trim() ===
                            'Há usuários indisponíveis, removidos ou sem permissão de exclusão. Revise a seleção.',
                    ),
                'The revoked permission did not reach the specific blocked-confirmation state.',
            );
            assert(
                userResolverResponses.at(-1)?.rows.length === 1 &&
                    userResolverResponses.at(-1)?.rows[0]?.can?.delete ===
                        false,
                'The final preflight response did not contain the revoked capability.',
            );
            assert(
                bulkDeleteRequests.length === 0,
                'A revoked delete capability still reached the DELETE endpoint.',
            );
            assert(
                selectedLabel().includes('Excluir 1 usuários') &&
                    localStorage.getItem(userSelectionStorageKey) ===
                        JSON.stringify([user.uuid]),
                'Fresh authorization failure closed the confirmation or cleared the selected UUID.',
            );
        },
    );

    await runCase(
        'users-page-does-not-offer-placeholder-bulk-verification',
        async () => {
            const user = initialUsers(1)[0]!;
            await resetUsers({ selectedIds: [], userCount: 1 });
            userCheckbox(user.name)?.click();
            await until(
                () => selectedLabel().includes('1 selecionado'),
                'The fixture user did not become selected.',
            );
            assert(
                exactButton('Marcar verificado') === null,
                'Users still exposes the incomplete bulk-verification placeholder.',
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
            await waitForRow('shared-uuid');
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
