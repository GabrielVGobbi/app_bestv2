import {
    QueryCache,
    QueryClient,
    QueryClientProvider,
} from '@tanstack/react-query';
import { AxiosHeaders } from 'axios';
import type { AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import type { ReactNode } from 'react';
import { useLayoutEffect } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { useDataTableInfiniteQuery } from '../../../resources/js/components/data-table/hooks/use-data-table-infinite-query';
import { useDataTableQuery } from '../../../resources/js/components/data-table/hooks/use-data-table-query';
import { DataTableExportDialog } from '../../../resources/js/components/data-table/shell/data-table-export-dialog';
import { DatabaseExportDialog } from '../../../resources/js/components/database-export/database-export-dialog';
import { useTableQuery } from '../../../resources/js/hooks/use-table-query';
import { http } from '../../../resources/js/lib/http';

type Row = { id: number; label: string };
type Gate = {
    config: InternalAxiosRequestConfig;
    settled: boolean;
    resolve: (data: unknown) => void;
};
type Live = {
    rows: Row[];
    isFetching: boolean;
    error: unknown;
    fetchNextPage?: () => void;
};
let live: Live;
let client: QueryClient;
let businessErrors: unknown[] = [];
let normalizedErrors: unknown[] = [];
let forbiddenNetwork = 0;
const gates: Gate[] = [];
const result = document.getElementById('fixture-result')!;
const root = createRoot(document.getElementById('app')!);

function denyNetwork(): never {
    forbiddenNetwork++;

    throw new Error('UI005 fixture forbids real network access.');
}
globalThis.fetch = denyNetwork;
XMLHttpRequest.prototype.open = denyNetwork;
http.defaults.adapter = (config) =>
    new Promise<AxiosResponse>((resolveResponse) => {
        const gate: Gate = {
            config,
            settled: false,
            resolve(data) {
                if (gate.settled) {
                    return;
                }

                gate.settled = true;
                resolveResponse({
                    data,
                    status: 200,
                    statusText: 'OK',
                    headers: new AxiosHeaders(),
                    config,
                });
            },
        };
        gates.push(gate);
    });
http.interceptors.response.use(undefined, (error: unknown) => {
    normalizedErrors.push(error);

    return Promise.reject(error);
});

const initialState = (filter: string) => ({
    page: 1,
    perPage: 10,
    search: '',
    sorting: [],
    columnVisibility: {},
    rowSelection: {},
    filters: { status: filter },
});
const filters = [{ id: 'status', label: 'Status', type: 'select' as const }];
function PaginatedProbe({ filter = 'old' }: { filter?: string }) {
    const value = useDataTableQuery<Row>({
        queryKey: 'ui005-paginated',
        endpoint: '/ui005/paginated',
        state: initialState(filter),
        filters,
    });
    useLayoutEffect(() => {
        live = value;
    });

    return <output>{value.rows.map((row) => row.label).join(',')}</output>;
}
function InfiniteProbe({ filter = 'old' }: { filter?: string }) {
    const value = useDataTableInfiniteQuery<Row>({
        queryKey: 'ui005-infinite',
        endpoint: '/ui005/infinite',
        state: initialState(filter),
        filters,
    });
    useLayoutEffect(() => {
        live = value;
    });

    return <output>{value.rows.map((row) => row.label).join(',')}</output>;
}
function LegacyProbe({ filter = 'old' }: { filter?: string }) {
    const value = useTableQuery<Row>('ui005-legacy', '/ui005/legacy', {
        search: filter,
    });
    const rows = value.data?.data ?? [];
    useLayoutEffect(() => {
        live = { rows, isFetching: value.isFetching, error: value.error };
    });

    return <output>{rows.map((row) => row.label).join(',')}</output>;
}
const rejectSubmission = async () => {
    throw new Error('Export submission forbidden by UI005 fixture.');
};
function ExportProbe({ open }: { open: boolean }) {
    return (
        <DataTableExportDialog
            open={open}
            onClose={() => undefined}
            modelClass="App\\Models\\Synthetic"
            columnsEndpoint="/ui005/export-columns"
            activeExports={[]}
            onStartExport={rejectSubmission}
        />
    );
}
function DatabaseProbe({ open }: { open: boolean }) {
    return (
        <DatabaseExportDialog
            open={open}
            onClose={() => undefined}
            onStartExport={rejectSubmission}
        />
    );
}
function render(node: ReactNode) {
    root.render(
        <QueryClientProvider client={client}>{node}</QueryClientProvider>,
    );
}
const delay = (ms = 20) => new Promise((resolve) => setTimeout(resolve, ms));
function assert(condition: unknown, message: string): void {
    if (!condition) {
        throw new Error(message);
    }
}
async function until(check: () => boolean, message: string) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (check()) {
            return;
        }

        await delay(10);
    }

    throw new Error(message);
}
async function nextGate(index: number) {
    await until(
        () => gates.length > index,
        'Actual HTTP adapter was not reached.',
    );

    return gates[index];
}
function page(label: string, current = 1, last = 2) {
    return {
        data: [{ id: current, label }],
        meta: {
            current_page: current,
            from: current,
            to: current,
            per_page: 10,
            last_page: last,
            total: last,
        },
        links: { first: null, last: null, prev: null, next: null },
    };
}
async function reset() {
    if (client) {
        flushSync(() => render(null));
        client.clear();
    }

    gates
        .filter((gate) => !gate.settled)
        .forEach((gate) => gate.resolve({ data: [] }));
    await delay();
    gates.length = 0;
    businessErrors = [];
    normalizedErrors = [];
    client = new QueryClient({
        queryCache: new QueryCache({
            onError: (error) => businessErrors.push(error),
        }),
        defaultOptions: { queries: { retry: false, gcTime: Infinity } },
    });
}
async function run() {
    const checks: { name: string; status: string; error?: string }[] = [];
    const check = async (name: string, test: () => Promise<void>) => {
        try {
            await reset();
            await test();
            checks.push({ name, status: 'passed' });
        } catch (error) {
            checks.push({ name, status: 'failed', error: String(error) });
        }

        result.textContent = JSON.stringify(
            { status: 'running', checks, forbiddenNetwork },
            null,
            2,
        );
    };
    const sources = [
        { name: 'data-table-paginated', node: () => <PaginatedProbe /> },
        { name: 'data-table-infinite', node: () => <InfiniteProbe /> },
        { name: 'legacy-table-query', node: () => <LegacyProbe /> },
    ];

    for (const source of sources) {
        await check(
            `${source.name}:unmount-aborts-real-http-without-stale-cache-or-error`,
            async () => {
                flushSync(() => render(source.node()));
                const gate = await nextGate(0);
                const key = client.getQueryCache().getAll()[0].queryKey;
                flushSync(() => render(null));
                assert(
                    gate.config.signal?.aborted === true,
                    'Unmounting the actual hook must abort its HTTP request.',
                );
                gate.resolve(page('obsolete-unmounted-result'));
                await delay();
                assert(
                    client.getQueryData(key) === undefined,
                    'Unmounted query populated stale cache.',
                );
                assert(
                    client.getQueryState(key)?.error === null &&
                        businessErrors.length === 0,
                    'Cancellation became a business error.',
                );
                assert(
                    (normalizedErrors.at(-1) as { type?: string })?.type ===
                        'canceled',
                    'HTTP did not preserve normalized cancellation.',
                );
            },
        );
    }

    const metadata = [
        {
            name: 'export-columns',
            node: (open: boolean) => <ExportProbe open={open} />,
            response: { data: [{ key: 'label', label: 'Synthetic column' }] },
        },
        {
            name: 'database-export-tables',
            node: (open: boolean) => <DatabaseProbe open={open} />,
            response: { data: [{ name: 'synthetic_table' }] },
        },
    ];

    for (const source of metadata) {
        await check(
            `${source.name}:enabled-close-and-unmount-preserve-observer-contract`,
            async () => {
                flushSync(() => render(source.node(false)));
                await delay();
                assert(
                    gates.length === 0,
                    'Initially closed metadata dialog must not fetch.',
                );
                flushSync(() => render(source.node(true)));
                const gate = await nextGate(0);
                const key = client.getQueryCache().getAll()[0].queryKey;
                flushSync(() => render(source.node(false)));
                assert(
                    !gate.config.signal?.aborted,
                    'Closing alone must preserve existing enabled-observer semantics.',
                );
                flushSync(() => render(null));
                assert(
                    gate.config.signal?.aborted === true,
                    'Unmounting metadata dialog must abort its HTTP request.',
                );
                gate.resolve(source.response);
                await delay();
                assert(
                    client.getQueryData(key) === undefined &&
                        client.getQueryState(key)?.error === null,
                    'Late metadata entered cache or became an error.',
                );
                assert(
                    businessErrors.length === 0,
                    'Canceled metadata emitted a business error.',
                );
            },
        );
    }

    for (const source of [
        {
            name: 'data-table-paginated',
            node: (filter: string) => <PaginatedProbe filter={filter} />,
        },
        {
            name: 'legacy-table-query',
            node: (filter: string) => <LegacyProbe filter={filter} />,
        },
    ]) {
        await check(
            `${source.name}:new-filter-wins-over-late-old-response`,
            async () => {
                flushSync(() => render(source.node('old')));
                const oldGate = await nextGate(0);
                const oldKey = client.getQueryCache().getAll()[0].queryKey;
                flushSync(() => render(source.node('new')));
                const newGate = await nextGate(1);
                assert(
                    oldGate.config.signal?.aborted === true,
                    'Filter change did not abort its obsolete hook request.',
                );
                newGate.resolve(page('new-filter'));
                await until(
                    () => live.rows[0]?.label === 'new-filter',
                    'New filter did not render.',
                );
                oldGate.resolve(page('old-filter-finished-last'));
                await delay();
                assert(
                    live.rows[0]?.label === 'new-filter' && live.error === null,
                    'Old filter replaced current visible rows or produced an error.',
                );
                assert(
                    client.getQueryData(oldKey) === undefined,
                    'Old filter warmed stale cache after consumer moved.',
                );
            },
        );
    }

    await check(
        'infinite-next-page-cancel-retains-committed-pages-and-no-business-error',
        async () => {
            flushSync(() => render(<InfiniteProbe />));
            (await nextGate(0)).resolve(page('page-one', 1, 3));
            await until(
                () => live.rows[0]?.label === 'page-one' && !live.isFetching,
                'First page did not commit.',
            );
            const key = client.getQueryCache().getAll()[0].queryKey;
            flushSync(() => live.fetchNextPage!());
            const next = await nextGate(1);
            assert(
                next.config.params.page === 2,
                'Next-page request lost its page parameter.',
            );
            await client.cancelQueries({ queryKey: key, exact: true });
            assert(
                next.config.signal?.aborted === true,
                'Canceling next page did not abort its actual HTTP request.',
            );
            next.resolve(page('obsolete-page-two', 2, 3));
            await delay();
            assert(
                live.rows.length === 1 &&
                    live.rows[0]?.label === 'page-one' &&
                    live.error === null,
                'Canceled next page changed retained rows or emitted an error.',
            );
            const cached = client.getQueryData<{
                pages: unknown[];
                pageParams: unknown[];
            }>(key)!;
            assert(
                cached.pages.length === 1 && cached.pageParams.length === 1,
                'Canceled next page changed committed page cache.',
            );
            assert(
                businessErrors.length === 0,
                'Canceled next page emitted a business error.',
            );
        },
    );
    await check(
        'infinite-filter-change-cancels-old-next-page-and-keeps-new-filter',
        async () => {
            flushSync(() => render(<InfiniteProbe filter="old" />));
            (await nextGate(0)).resolve(page('old-page-one', 1, 3));
            await until(
                () =>
                    live.rows[0]?.label === 'old-page-one' && !live.isFetching,
                'Old first page did not commit.',
            );
            flushSync(() => live.fetchNextPage!());
            const oldNext = await nextGate(1);
            flushSync(() => render(<InfiniteProbe filter="new" />));
            const current = await nextGate(2);
            assert(
                oldNext.config.signal?.aborted === true,
                'Moving to another filter left old pagination running.',
            );
            current.resolve(page('new-page-one'));
            await until(
                () => live.rows[0]?.label === 'new-page-one',
                'New infinite filter did not render.',
            );
            oldNext.resolve(page('old-page-two-finished-last', 2, 3));
            await delay();
            assert(
                live.rows.length === 1 &&
                    live.rows[0]?.label === 'new-page-one' &&
                    live.error === null,
                'Late old page entered the new filter.',
            );
            assert(
                businessErrors.length === 0,
                'Moving pagination produced a business error.',
            );
        },
    );
    await reset();
    result.textContent = JSON.stringify(
        {
            status:
                checks.every((item) => item.status === 'passed') &&
                forbiddenNetwork === 0
                    ? 'passed'
                    : 'failed',
            checks,
            forbiddenNetwork,
        },
        null,
        2,
    );
}
document.getElementById('run-contracts')!.addEventListener('click', () => {
    void run();
});
