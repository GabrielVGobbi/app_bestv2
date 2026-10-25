import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React, {
    StrictMode,
    Suspense,
    startTransition,
    useLayoutEffect,
    useState,
} from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { useDataTableSelection } from '../../../resources/js/components/data-table/hooks/use-data-table-selection';
import { useDataTableState } from '../../../resources/js/components/data-table/hooks/use-data-table-state';
import {
    NavModuleProvider,
    useNavModule,
} from '../../../resources/js/contexts/nav-module-context';
import type { Upload } from '../../../resources/js/modules/uploads/api/types';
import { FileAttachments } from '../../../resources/js/modules/uploads/components/file-attachments';
import { UploadQueueItem } from '../../../resources/js/modules/uploads/components/upload-queue-item';
import { useUploadQueue } from '../../../resources/js/modules/uploads/hooks/use-upload-queue';
import type { UseUploadQueueReturn } from '../../../resources/js/modules/uploads/hooks/use-upload-queue';
import { ScopeProvider } from './ui-purity-scope';

type Row = { id: string; name: string };
type Call = {
    form: FormData;
    signal: AbortSignal;
    resolve: () => void;
    reject: () => void;
};
const calls: Call[] = [];
const pending = new Promise<never>(() => undefined);
let attempts = 0;
let owner = 8101;
let current = true;
const scope = () => ({
    userId: owner,
    isCurrent: () => current,
    storageKey: (key: string) => `private-state:${owner}:${key}`,
});
const modules = [
    {
        slug: 'clients',
        label: 'Clientes',
        default: true,
        routePrefixes: ['/clients'],
        items: [{ title: 'Clientes', href: '/clients' }],
    },
    {
        slug: 'settings',
        label: 'Configurações',
        routePrefixes: ['/settings'],
        items: [{ title: 'Configurações', href: '/settings' }],
    },
];
const noMutation = {
    isPending: false,
    mutate: () => {
        throw new Error('Unexpected attachment mutation.');
    },
};
const fixture = {
    scope: scope(),
    page: { url: '/clients', props: { nav: { modules } } },
    uploads: {
        list: { data: { data: [] as Upload[] }, isLoading: false },
        tags: { data: { data: [] } },
        update: noMutation,
        remove: noMutation,
        setCover: noMutation,
        toggleFavorite: noMutation,
    },
    zips: [] as { ids: number[] }[],
    post: (_url: string, form: FormData, options: { signal: AbortSignal }) =>
        new Promise((resolve, reject) => {
            const call = {
                form,
                signal: options.signal,
                resolve: () => resolve({ data: { success: true, data: [] } }),
                reject: () => reject({ message: 'Synthetic failure' }),
            };
            calls.push(call);
            options.signal.addEventListener(
                'abort',
                () => reject({ name: 'CanceledError' }),
                { once: true },
            );
        }),
};
Object.assign(globalThis, { __uiPurity: fixture });
const result = document.getElementById('fixture-result')!;
const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
});
const root = createRoot(document.getElementById('app')!);
let epoch = 0;
let selected: ReturnType<typeof useDataTableSelection<Row>>;
let table: ReturnType<typeof useDataTableState>;
let queue: UseUploadQueueReturn;
let navigation: ReturnType<typeof useNavModule>;

function SelectionProbe({
    rows,
    suspend = false,
    enabled = true,
}: {
    rows: Row[];
    suspend?: boolean;
    enabled?: boolean;
}) {
    const [selection, setSelection] = useState<Record<string, boolean>>({
        a: true,
    });
    const value = useDataTableSelection({
        storageKey: 'ui008-selection',
        keyExtractor: (row: Row) => row.id,
        currentRows: rows,
        rowSelection: selection,
        setRowSelection: setSelection,
        enabled,
    });
    useLayoutEffect(() => {
        selected = value;
    });

    if (suspend) {
        attempts++;

        throw pending;
    }

    return (
        <output>{value.selectedRows.map((row) => row.name).join(',')}</output>
    );
}

function TableProbe({
    columnIds = ['name'],
    initialPerPage = 10,
    suspend = false,
}: {
    columnIds?: string[];
    initialPerPage?: number;
    suspend?: boolean;
}) {
    const value = useDataTableState({
        queryKey: 'ui008-table',
        initialPerPage,
        filters: [{ id: 'status', label: 'Status', type: 'text' }],
        columnIds,
        persistState: true,
    });
    useLayoutEffect(() => {
        table = value;
    });

    if (suspend) {
        attempts++;

        throw pending;
    }

    return <output>{value.state.search}</output>;
}

function QueueProbe({
    ttl = 60000,
    suspend = false,
    concurrency = 1,
}: {
    ttl?: number;
    suspend?: boolean;
    concurrency?: number;
}) {
    const value = useUploadQueue({
        modelType: 'client',
        modelId: 71,
        successTtl: ttl,
        concurrency,
    });
    useLayoutEffect(() => {
        queue = value;
    });

    if (suspend) {
        attempts++;

        throw pending;
    }

    return (
        <output>
            {value.items
                .map((task) => `${task.file.name}:${task.status}`)
                .join(',')}
        </output>
    );
}

function NavProbe() {
    const value = useNavModule();
    useLayoutEffect(() => {
        navigation = value;
    });

    return (
        <output>
            {value.activeModule.slug}:{value.sidebarView}
        </output>
    );
}

function render(node: React.ReactNode) {
    root.render(
        <StrictMode>
            <QueryClientProvider client={client}>
                <ScopeProvider scope={fixture.scope}>
                    <Suspense key={epoch} fallback={<span>Suspenso</span>}>
                        {node}
                    </Suspense>
                </ScopeProvider>
            </QueryClientProvider>
        </StrictMode>,
    );
}
const delay = (ms = 30) => new Promise((resolve) => setTimeout(resolve, ms));
function assert(condition: unknown, message: string): void {
    if (!condition) {
        throw new Error(message);
    }
}
async function until(check: () => boolean, message: string) {
    for (let i = 0; i < 100; i++) {
        if (check()) {
            return;
        }

        await delay(10);
    }

    throw new Error(message);
}
async function reset() {
    flushSync(() => render(null));
    epoch++;
    current = true;
    owner++;
    fixture.scope = scope();
    calls.length = 0;
    client.clear();
    await delay();
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
            { status: 'running', checks },
            null,
            2,
        );
    };
    await check(
        'abandoned-selection-render-does-not-poison-committed-row-cache',
        async () => {
            flushSync(() =>
                render(
                    <SelectionProbe
                        rows={[{ id: 'a', name: 'Committed A' }]}
                    />,
                ),
            );
            assert(
                selected.selectedRows[0]?.name === 'Committed A',
                'Initial selected row missing.',
            );
            const before = attempts;
            startTransition(() =>
                render(
                    <SelectionProbe
                        rows={[{ id: 'a', name: 'Uncommitted secret' }]}
                        suspend
                    />,
                ),
            );
            await until(
                () => attempts > before,
                'Transition did not render the suspended branch.',
            );
            flushSync(() => render(<SelectionProbe rows={[]} />));
            assert(
                selected.selectedRows[0]?.name === 'Committed A',
                'Abandoned render replaced the committed off-page row.',
            );
            assert(
                selected.selectedCount === 1,
                'Off-page selected count changed.',
            );
            flushSync(() => selected.clearSelection());
            assert(
                selected.selectedRows.length === 0 &&
                    selected.selectedCount === 0,
                'Clear must remove IDs and row cache.',
            );
        },
    );
    await check(
        'selection-restores-storage-and-does-not-cross-identity',
        async () => {
            localStorage.setItem(
                fixture.scope.storageKey('ui008-selection'),
                '["b"]',
            );
            flushSync(() =>
                render(
                    <SelectionProbe rows={[{ id: 'b', name: 'Restored B' }]} />,
                ),
            );
            await until(
                () => selected.selectedRows[0]?.name === 'Restored B',
                'Stored selection was not restored.',
            );
            current = false;
            fixture.scope = { ...fixture.scope, isCurrent: () => false };
            flushSync(() =>
                render(
                    <SelectionProbe rows={[{ id: 'b', name: 'Restored B' }]} />,
                ),
            );
            assert(
                selected.selectedCount === 0 &&
                    selected.selectedRows.length === 0,
                'Invalidated identity exposed selected data.',
            );
        },
    );
    await check(
        'table-storage-sanitization-and-edits-survive-new-prop-arrays',
        async () => {
            localStorage.setItem(
                fixture.scope.storageKey('datatable-state:ui008-table'),
                JSON.stringify({
                    search: 'stored',
                    perPage: 25,
                    sorting: [
                        { id: 'name', desc: true },
                        { id: 'secret', desc: false },
                    ],
                    filters: { status: 'active', secret: 'hidden' },
                    columnVisibility: { name: false, secret: true },
                }),
            );
            flushSync(() => render(<TableProbe />));
            await until(
                () => table.state.search === 'stored',
                'Table did not restore.',
            );
            assert(
                table.state.perPage === 25 &&
                    table.state.sorting.length === 1 &&
                    !('secret' in table.state.filters),
                'Stored state sanitization failed.',
            );
            flushSync(() => table.setSearch('edited'));
            flushSync(() => render(<TableProbe />));
            await delay(50);
            assert(
                table.state.search === 'edited',
                'New filter/column arrays restored over the edited search.',
            );
            await delay(350);
            assert(
                JSON.parse(
                    localStorage.getItem(
                        fixture.scope.storageKey('datatable-state:ui008-table'),
                    )!,
                ).search === 'edited',
                'Edited search was not persisted.',
            );
        },
    );
    await check(
        'interrupted-table-render-cannot-replace-committed-restore-inputs',
        async () => {
            localStorage.setItem(
                fixture.scope.storageKey('datatable-state:ui008-table'),
                JSON.stringify({
                    search: 'restore gate',
                    sorting: [
                        { id: 'name', desc: true },
                        { id: 'secret', desc: false },
                    ],
                }),
            );
            const originalSetTimeout = window.setTimeout;
            const restoreCallbacks: (() => void)[] = [];

            // Hold the storage read at its external timer boundary. React's
            // render/effects remain real, including StrictMode cleanup.
            window.setTimeout = ((
                handler: TimerHandler,
                timeout?: number,
                ...args: unknown[]
            ) => {
                if (timeout === 0 && typeof handler === 'function') {
                    return originalSetTimeout(
                        () => restoreCallbacks.push(() => handler(...args)),
                        0,
                    );
                }

                return originalSetTimeout(handler, timeout, ...args);
            }) as typeof window.setTimeout;

            try {
                flushSync(() => render(<TableProbe />));
                await until(
                    () => restoreCallbacks.length > 0,
                    'Storage restore timer did not reach the gate.',
                );
                const before = attempts;
                startTransition(() =>
                    render(
                        <TableProbe
                            columnIds={['secret']}
                            initialPerPage={99}
                            suspend
                        />,
                    ),
                );
                await until(
                    () => attempts > before,
                    'Speculative table inputs did not render.',
                );
                flushSync(() =>
                    restoreCallbacks.forEach((callback) => callback()),
                );
                await until(
                    () => table.state.search === 'restore gate',
                    'Gated table restore did not finish.',
                );
                flushSync(() => render(<TableProbe />));
                assert(
                    table.state.perPage === 10,
                    'Suspended render replaced the committed default page size.',
                );
                assert(
                    table.state.sorting.length === 1 &&
                        table.state.sorting[0].id === 'name',
                    'Suspended render replaced the committed allowed columns.',
                );
            } finally {
                window.setTimeout = originalSetTimeout;
            }
        },
    );
    await check(
        'abandoned-upload-render-does-not-change-success-expiry',
        async () => {
            flushSync(() => render(<QueueProbe />));
            flushSync(() =>
                queue.enqueue([
                    new File(['one'], 'one.txt', { type: 'text/plain' }),
                ]),
            );
            await until(
                () =>
                    calls.length === 1 &&
                    queue.items[0]?.status === 'uploading',
                'Upload did not start exactly once.',
            );
            const before = attempts;
            startTransition(() => render(<QueueProbe ttl={0} suspend />));
            await until(
                () => attempts > before,
                'Upload transition did not suspend.',
            );
            calls[0].resolve();
            await delay(100);
            flushSync(() => render(<QueueProbe />));
            assert(
                queue.items[0]?.status === 'success',
                'Uncommitted TTL removed the successful upload.',
            );
            assert(calls.length === 1, 'StrictMode duplicated the upload.');
        },
    );
    await check(
        'queue-retry-cancel-rejections-and-concurrency-preserve-files',
        async () => {
            flushSync(() => render(<QueueProbe />));
            const a = new File(['A'], 'a.txt', { type: 'text/plain' });
            const b = new File(['B'], 'b.txt', { type: 'text/plain' });
            flushSync(() => queue.enqueue([a, b]));
            await until(() => calls.length === 1, 'First upload missing.');
            assert(
                calls[0].form.get('parentable_id') === '71' &&
                    (calls[0].form.get('files[]') as File).name === 'a.txt',
                'Upload form uses wrong parent/file.',
            );
            calls[0].reject();
            await until(
                () => queue.hasErrors && calls.length === 2,
                'Failure did not release concurrency slot.',
            );
            const failed = queue.items.find(
                (task) => task.file.name === 'a.txt',
            )!;
            flushSync(() => queue.retry(failed.id));
            calls[1].resolve();
            await until(
                () => calls.length === 3,
                'Retry was not scheduled after pending upload.',
            );
            assert(
                (calls[2].form.get('files[]') as File).name === 'a.txt',
                'Retry lost its original file.',
            );
            flushSync(() => queue.cancel(failed.id));
            assert(
                calls[2].signal.aborted &&
                    !queue.items.some((task) => task.id === failed.id),
                'Cancel did not abort and remove the task.',
            );
            flushSync(() =>
                queue.enqueueRejected([
                    { file: a, reason: 'Synthetic rejection' },
                ]),
            );
            assert(queue.hasErrors, 'Rejected files must be displayed.');
            flushSync(() => queue.clearErrors());
            assert(!queue.hasErrors, 'Clear errors left a rejected task.');
            current = false;
            const count = calls.length;
            flushSync(() => queue.enqueue([a]));
            await delay();
            assert(
                calls.length === count,
                'Invalidated identity queued another upload.',
            );
        },
    );
    await check(
        'navigation-keeps-manual-choice-until-route-or-modules-change',
        async () => {
            fixture.page = { url: '/clients', props: { nav: { modules } } };
            const screen = () => (
                <NavModuleProvider>
                    <NavProbe />
                </NavModuleProvider>
            );
            flushSync(() => render(screen()));
            assert(
                navigation.activeModule.slug === 'clients' &&
                    navigation.sidebarView === 'items',
                'Route detection failed.',
            );
            flushSync(() => navigation.showModuleList());
            flushSync(() => render(screen()));
            assert(
                navigation.sidebarView === 'modules',
                'Ordinary render reset manual module list.',
            );
            flushSync(() => navigation.selectModule('settings'));
            assert(
                navigation.activeModule.slug === 'settings',
                'Manual module choice failed.',
            );
            fixture.page = { ...fixture.page, url: '/clients/42' };
            flushSync(() => render(screen()));
            await until(
                () =>
                    navigation.activeModule.slug === 'clients' &&
                    navigation.sidebarView === 'items',
                'Route change did not settle on clients.',
            );
            assert(
                navigation.activeModule.slug === 'clients' &&
                    navigation.sidebarView === 'items',
                'Route change did not detect clients.',
            );
            fixture.page = { ...fixture.page, url: '/outside' };
            flushSync(() => render(screen()));
            await until(
                () => navigation.sidebarView === 'modules',
                'Outside route did not settle on module list.',
            );
            assert(
                navigation.sidebarView === 'modules',
                'Outside route must show module list.',
            );
        },
    );
    await check(
        'queue-item-file-icon-and-actions-survive-rerenders',
        async () => {
            let retries = 0;
            let canceled = 0;
            const task = {
                id: 'synthetic',
                file: new File(['pdf'], 'report.pdf', {
                    type: 'application/pdf',
                }),
                status: 'pending' as const,
                progress: 0,
            };
            flushSync(() =>
                render(
                    <UploadQueueItem
                        task={task}
                        onCancel={() => {
                            canceled++;
                        }}
                        onRetry={() => {
                            retries++;
                        }}
                    />,
                ),
            );
            const icon = document.querySelector('.lucide-file-text');
            assert(icon, 'PDF must display the document icon.');
            flushSync(() =>
                render(
                    <UploadQueueItem
                        task={{ ...task, status: 'uploading', progress: 33 }}
                        onCancel={() => {
                            canceled++;
                        }}
                        onRetry={() => {
                            retries++;
                        }}
                    />,
                ),
            );
            assert(
                document.querySelector('.lucide-file-text') === icon,
                'Progress rerender remounted the MIME icon.',
            );
            flushSync(() =>
                render(
                    <UploadQueueItem
                        task={{ ...task, status: 'error', error: 'Synthetic' }}
                        onCancel={() => {
                            canceled++;
                        }}
                        onRetry={() => {
                            retries++;
                        }}
                    />,
                ),
            );
            (
                document.querySelector(
                    '[aria-label="Tentar novamente"]',
                ) as HTMLButtonElement
            ).click();
            (
                document.querySelector(
                    '[aria-label="Descartar"]',
                ) as HTMLButtonElement
            ).click();
            assert(
                retries === 1 && canceled === 1,
                'Retry/discard actions failed.',
            );
        },
    );
    await check(
        'attachment-selection-prunes-removed-files-without-resurrection',
        async () => {
            const upload: Upload = {
                id: 901,
                name: 'Synthetic attachment',
                original_name: 'sample.txt',
                file_name: 'sample.txt',
                extension: 'txt',
                mime_type: 'text/plain',
                size: 1,
                size_human: '1 B',
                url: '#',
                download_url: '#',
                preview_url: '#',
                icon: 'file-text',
                is_image: false,
                is_cover: false,
                is_favorite: false,
                tag: null,
                collection: null,
                source: 'fixture',
                created_at: null,
                updated_at: null,
                permissions: {
                    can_update: false,
                    can_delete: false,
                    can_set_cover: false,
                },
            };
            fixture.uploads.list.data.data = [upload];
            const screen = () => (
                <FileAttachments modelType="client" modelId={71} />
            );
            flushSync(() => render(screen()));
            flushSync(() =>
                (
                    document.querySelector(
                        '[title="Selecionar arquivos"]',
                    ) as HTMLButtonElement
                ).click(),
            );
            flushSync(() =>
                (
                    document.querySelector(
                        '[aria-label="Selecionar Synthetic attachment"]',
                    ) as HTMLButtonElement
                ).click(),
            );
            assert(
                document
                    .querySelector(
                        '[aria-label="Selecionar Synthetic attachment"]',
                    )
                    ?.getAttribute('aria-checked') === 'true',
                'File selection failed.',
            );
            fixture.uploads.list.data.data = [];
            flushSync(() => render(screen()));
            fixture.uploads.list.data.data = [upload];
            flushSync(() => render(screen()));
            assert(
                document
                    .querySelector(
                        '[aria-label="Selecionar Synthetic attachment"]',
                    )
                    ?.getAttribute('aria-checked') === 'false',
                'Removed file reappeared selected.',
            );
            assert(
                !document.querySelector('[aria-label="Selecionar todos"]'),
                'Pruned selection left a bulk action bar.',
            );
        },
    );
    await reset();
    result.textContent = JSON.stringify(
        {
            status: checks.every((item) => item.status === 'passed')
                ? 'passed'
                : 'failed',
            checks,
        },
        null,
        2,
    );
}
document.getElementById('run-contracts')!.addEventListener('click', () => {
    void run();
});
