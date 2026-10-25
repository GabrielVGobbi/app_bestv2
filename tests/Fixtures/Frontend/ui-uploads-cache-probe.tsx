import { createInertiaApp } from '@inertiajs/react';
import { QueryObserver, useQueryClient } from '@tanstack/react-query';
import type { QueryKey, QueryClient } from '@tanstack/react-query';
import type {
    AxiosAdapter,
    AxiosResponse,
    InternalAxiosRequestConfig,
} from 'axios';
import { AxiosError, AxiosHeaders } from 'axios';
import { StrictMode } from 'react';
import { useLayoutEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { PrivateStateLayout } from '../../../resources/js/contexts/private-state-context';
import { http } from '../../../resources/js/lib/http';
import type {
    PaginatedResponse,
    PaginationLinks,
    PaginationMeta,
} from '../../../resources/js/lib/http';
import { uploadsKeys } from '../../../resources/js/modules/uploads/api/keys';
import {
    useBulkDeleteUploads,
    useDeleteUpload,
    useUpdateUpload,
} from '../../../resources/js/modules/uploads/api/mutations';
import { uploadsListOptions } from '../../../resources/js/modules/uploads/api/queries';
import type {
    ModelId,
    Upload,
    UploadFilters,
    UploadUpdateInput,
} from '../../../resources/js/modules/uploads/api/types';
import { FileAttachments } from '../../../resources/js/modules/uploads/components/file-attachments';

type Gate = {
    config: InternalAxiosRequestConfig;
    settled: boolean;
    resolve: (data: unknown, status?: number) => void;
    reject: () => void;
};
type View = {
    modelId: ModelId;
    tag?: string | null;
    collection?: string | null;
    resetKey: number;
};
type UploadMutationApi = {
    deleteOne: (id: number) => Promise<unknown>;
    deleteMany: (ids: number[]) => Promise<unknown>;
    update: (id: number, data: UploadUpdateInput) => Promise<unknown>;
};
type Harness = {
    client: QueryClient;
    setView: (view: Omit<View, 'resetKey'>) => void;
    resetView: () => void;
    mutations: UploadMutationApi;
};
type Check = { name: string; status: 'passed' | 'failed'; error?: string };
type WindowWithHarness = Window & { __uiUploadsCacheHarness?: Harness };

const result = document.getElementById('fixture-result')!;
const gates: Gate[] = [];
let forbiddenNetwork = 0;
let requestCount = 0;
let harness: Harness | null = null;
const serverDeletedIds = new Set<number>();

function denyNetwork(): never {
    forbiddenNetwork++;

    throw new Error('UI-002 fixture forbids native network access.');
}

globalThis.fetch = denyNetwork;
XMLHttpRequest.prototype.open = denyNetwork;

if (navigator.sendBeacon) {
    navigator.sendBeacon = () => denyNetwork();
}

function response(
    config: InternalAxiosRequestConfig,
    data: unknown,
): AxiosResponse {
    return {
        data,
        status: 200,
        statusText: 'OK',
        headers: new AxiosHeaders(),
        config,
    };
}

function upload(
    id: number,
    collection: string | null,
    tag: string | null,
): Upload {
    return {
        id,
        name: `upload-${id}.pdf`,
        original_name: `upload-${id}.pdf`,
        file_name: `upload-${id}.pdf`,
        extension: 'pdf',
        mime_type: 'application/pdf',
        size: 10,
        size_human: '10 B',
        url: `/uploads/${id}`,
        download_url: `/uploads/${id}/download`,
        preview_url: `/uploads/${id}/preview`,
        icon: 'file-text',
        is_image: false,
        is_cover: false,
        is_favorite: false,
        tag,
        collection,
        source: 'fixture',
        created_at: '2026-01-01T00:00:00.000000Z',
        updated_at: '2026-01-01T00:00:00.000000Z',
        uploader: { id: 1, name: 'Fixture' },
        permissions: {
            can_update: true,
            can_delete: true,
            can_set_cover: false,
        },
    };
}

function page<T>(
    data: T[],
    current: number,
    total: number,
): PaginatedResponse<T> {
    const lastPage = Math.max(1, Math.ceil(total / 2));
    const meta: PaginationMeta = {
        current_page: current,
        from: data.length === 0 ? null : (current - 1) * 2 + 1,
        last_page: lastPage,
        per_page: 2,
        to: data.length === 0 ? null : (current - 1) * 2 + data.length,
        total,
    };
    const links: PaginationLinks = {
        first: '/api/v1/uploads?page=1',
        last: `/api/v1/uploads?page=${lastPage}`,
        prev: current > 1 ? `/api/v1/uploads?page=${current - 1}` : null,
        next: current < lastPage ? `/api/v1/uploads?page=${current + 1}` : null,
    };

    return { data, meta, links };
}

function fixtureList(config: InternalAxiosRequestConfig): unknown {
    const params = (config.params ?? {}) as Record<string, unknown>;
    const modelId = Number(params.parentable_id ?? 101);
    const collection = (params.collection as string | undefined) ?? null;
    const tag = (params.tag as string | undefined) ?? null;
    const current = Number(params.page ?? 1);

    if (modelId === 101 && collection === 'docs' && tag === 'blue') {
        const rows = [
            upload(11, 'docs', 'blue'),
            upload(12, 'docs', 'blue'),
            upload(13, 'docs', 'blue'),
        ].filter((item) => !serverDeletedIds.has(item.id));

        return page(
            rows.slice((current - 1) * 2, current * 2),
            current,
            rows.length,
        );
    }

    if (modelId === 101 && collection === 'archive' && tag === 'green') {
        const rows = [upload(41, 'archive', 'green')].filter(
            (item) => !serverDeletedIds.has(item.id),
        );

        return page(rows, current, rows.length);
    }

    if (modelId === 202) {
        return page([upload(91, collection, tag)], current, 1);
    }

    return page([upload(71, collection, tag)], current, 1);
}

function requestAdapter(): AxiosAdapter {
    return (config) => {
        requestCount++;
        const method = (config.method ?? 'get').toLowerCase();
        const url = config.url ?? '';

        if (method === 'get') {
            if (url.endsWith('/tags')) {
                return Promise.resolve(response(config, { data: [] }));
            }

            if (url === '/api/v1/uploads') {
                return Promise.resolve(response(config, fixtureList(config)));
            }

            return Promise.resolve(response(config, { data: [] }));
        }

        return new Promise<AxiosResponse>((resolve, reject) => {
            const gate: Gate = {
                config,
                settled: false,
                resolve(data, status = 200) {
                    if (gate.settled) {
                        return;
                    }

                    gate.settled = true;
                    resolve({
                        ...response(config, data),
                        status,
                        statusText: status === 200 ? 'OK' : 'Fixture failure',
                    });
                },
                reject() {
                    if (gate.settled) {
                        return;
                    }

                    gate.settled = true;
                    const failedResponse: AxiosResponse = {
                        ...response(config, { message: 'Synthetic failure' }),
                        status: 500,
                        statusText: 'Synthetic failure',
                    };
                    reject(
                        new AxiosError(
                            'Synthetic upload API failure',
                            AxiosError.ERR_BAD_RESPONSE,
                            config,
                            undefined,
                            failedResponse,
                        ),
                    );
                },
            };
            gates.push(gate);
        });
    };
}

http.defaults.adapter = requestAdapter();

function UploadsProbe() {
    const client = useQueryClient();
    const [view, setViewState] = useState<View>({ modelId: 101, resetKey: 0 });
    const remove = useDeleteUpload('fixture-parent', view.modelId);
    const bulkRemove = useBulkDeleteUploads('fixture-parent', view.modelId);
    const update = useUpdateUpload('fixture-parent', view.modelId);

    useLayoutEffect(() => {
        const nextHarness: Harness = {
            client,
            setView: (next) =>
                setViewState((current) => ({
                    ...next,
                    resetKey: current.resetKey,
                })),
            resetView: () =>
                setViewState((current) => ({
                    modelId: 101,
                    resetKey: current.resetKey + 1,
                })),
            mutations: {
                deleteOne: (id) => remove.mutateAsync(id),
                deleteMany: (ids) => bulkRemove.mutateAsync(ids),
                update: (id, data) => update.mutateAsync({ id, data }),
            },
        };
        harness = nextHarness;
        (window as WindowWithHarness).__uiUploadsCacheHarness = nextHarness;
    });

    return (
        <FileAttachments
            key={view.resetKey}
            modelType="fixture-parent"
            modelId={view.modelId}
            tag={view.tag}
            collection={view.collection}
            concurrency={1}
            title="Fixture uploads"
        />
    );
}

type CacheEntry = [QueryKey, unknown];

function snapshotKeys(filters: UploadFilters[]): CacheEntry[] {
    return filters.map((filter) => {
        const key = uploadsKeys.list('fixture-parent', 101, filter);
        const value = harness!.client.getQueryData(key);

        return [key, value];
    });
}

function same(a: unknown, b: unknown): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

function assert(condition: unknown, message: string): asserts condition {
    if (!condition) {
        throw new Error(message);
    }
}

const delay = (ms = 10) => new Promise((resolve) => setTimeout(resolve, ms));

async function until(check: () => boolean, message: string): Promise<void> {
    for (let attempt = 0; attempt < 120; attempt++) {
        if (check()) {
            return;
        }

        await delay();
    }

    throw new Error(message);
}

function pendingGate(method: string, urlPart: string): Gate | undefined {
    return gates.find(
        (gate) =>
            !gate.settled &&
            gate.config.method?.toLowerCase() === method &&
            (gate.config.url ?? '').includes(urlPart),
    );
}

async function nextGate(method: string, urlPart: string): Promise<Gate> {
    await until(
        () => pendingGate(method, urlPart) !== undefined,
        `Timed out waiting for ${method.toUpperCase()} ${urlPart}.`,
    );

    return pendingGate(method, urlPart)!;
}

function seedParentLists(): Array<[QueryKey, PaginatedResponse<Upload>]> {
    const filters: UploadFilters[] = [
        { tag: 'blue', collection: 'docs', page: 1, per_page: 2 },
        { tag: 'blue', collection: 'docs', page: 2, per_page: 2 },
        { tag: 'green', collection: 'archive', page: 1, per_page: 2 },
    ];
    const values = [
        page([upload(11, 'docs', 'blue'), upload(12, 'docs', 'blue')], 1, 3),
        page([upload(13, 'docs', 'blue')], 2, 3),
        page([upload(21, 'archive', 'green')], 1, 1),
    ];

    return filters.map((filter, index) => [
        uploadsKeys.list('fixture-parent', 101, filter),
        values[index]!,
    ]);
}

function seed(client: QueryClient, entries: CacheEntry[]): void {
    for (const [key, value] of entries) {
        client.setQueryData(key, value);
    }
}

function uploadPayload(id: number, collection: string, tag: string): Upload {
    return upload(id, collection, tag);
}

function setFiles(files: File[]): void {
    const input = document.querySelector<HTMLInputElement>(
        '#app input[type="file"]',
    );
    assert(input, 'FileAttachments file input is missing.');
    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    Object.defineProperty(input, 'files', {
        configurable: true,
        value: transfer.files,
    });
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

async function reset(): Promise<void> {
    assert(harness, 'The real private layout and QueryClient are not ready.');
    serverDeletedIds.clear();

    for (const gate of gates) {
        if (!gate.settled) {
            gate.resolve({ data: [] });
        }
    }

    await delay();
    harness.client.clear();
    gates.splice(0, gates.length);
    await flushSync(() => {
        harness!.resetView();
    });
    await delay();
}

async function run(): Promise<void> {
    const checks: Check[] = [];
    const runCase = async (name: string, test: () => Promise<void>) => {
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

    await runCase('delete-failure-restores-full-filtered-pages', async () => {
        const entries = seedParentLists();
        const foreignKey = uploadsKeys.list('fixture-parent', 202, {
            tag: 'blue',
            collection: 'docs',
            page: 1,
            per_page: 2,
        });
        const foreignValue = page([upload(91, 'docs', 'blue')], 1, 1);
        seed(harness!.client, [...entries, [foreignKey, foreignValue]]);
        const foreignBefore = harness!.client.getQueryData(foreignKey);
        const before = [
            ...snapshotKeys([
                { tag: 'blue', collection: 'docs', page: 1, per_page: 2 },
                { tag: 'blue', collection: 'docs', page: 2, per_page: 2 },
                { tag: 'green', collection: 'archive', page: 1, per_page: 2 },
            ]),
            [foreignKey, foreignValue],
        ];
        const mutation = harness!.mutations.deleteOne(11).then(
            () => undefined,
            () => undefined,
        );
        const gate = await nextGate('delete', '/11');
        gate.reject();
        await mutation;
        await until(
            () =>
                same(before, [
                    ...snapshotKeys([
                        {
                            tag: 'blue',
                            collection: 'docs',
                            page: 1,
                            per_page: 2,
                        },
                        {
                            tag: 'blue',
                            collection: 'docs',
                            page: 2,
                            per_page: 2,
                        },
                        {
                            tag: 'green',
                            collection: 'archive',
                            page: 1,
                            per_page: 2,
                        },
                    ]),
                    [foreignKey, harness!.client.getQueryData(foreignKey)],
                ]),
            'Delete failure did not restore exact cached page responses.',
        );
        assert(
            same(before, [
                ...snapshotKeys([
                    { tag: 'blue', collection: 'docs', page: 1, per_page: 2 },
                    { tag: 'blue', collection: 'docs', page: 2, per_page: 2 },
                    {
                        tag: 'green',
                        collection: 'archive',
                        page: 1,
                        per_page: 2,
                    },
                ]),
                [foreignKey, harness!.client.getQueryData(foreignKey)],
            ]),
            'A cache envelope changed after delete failure.',
        );
        assert(
            same(foreignBefore, harness!.client.getQueryData(foreignKey)),
            'Delete failure changed the foreign parent cache.',
        );
    });

    await runCase(
        'bulk-delete-failure-restores-full-page-envelopes',
        async () => {
            const entries = seedParentLists();
            seed(harness!.client, entries);
            const filters = [
                { tag: 'blue', collection: 'docs', page: 1, per_page: 2 },
                { tag: 'blue', collection: 'docs', page: 2, per_page: 2 },
                { tag: 'green', collection: 'archive', page: 1, per_page: 2 },
            ];
            const before = snapshotKeys(filters);
            const mutation = harness!.mutations.deleteMany([11, 21]).then(
                () => undefined,
                () => undefined,
            );
            const gate = await nextGate('delete', '/bulk');
            gate.reject();
            await mutation;
            await until(
                () => same(before, snapshotKeys(filters)),
                'Bulk delete failure did not restore exact cached page responses.',
            );
        },
    );

    await runCase(
        'concurrent-delete-failure-does-not-resurrect-success',
        async () => {
            const entries = seedParentLists();
            seed(harness!.client, entries);
            const filter = {
                tag: 'blue',
                collection: 'docs',
                page: 1,
                per_page: 2,
            };
            const key = uploadsKeys.list('fixture-parent', 101, filter);
            const options = uploadsListOptions('fixture-parent', 101, filter);
            const observer = new QueryObserver(harness!.client, {
                ...options,
                staleTime: Infinity,
                retry: false,
            });
            const stopObserving = observer.subscribe(() => undefined);

            try {
                const first = harness!.mutations.deleteOne(11).then(
                    () => undefined,
                    () => undefined,
                );
                const firstGate = await nextGate('delete', '/11');
                const second = harness!.mutations.deleteOne(12);
                const secondGate = await nextGate('delete', '/12');
                serverDeletedIds.add(12);
                secondGate.resolve({ data: null, message: 'Deleted.' });
                await second;
                firstGate.reject();
                await first;
                await until(() => {
                    const state = observer.getCurrentResult();

                    return (
                        state.fetchStatus === 'idle' &&
                        state.data?.data.some((item) => item.id === 11) ===
                            true &&
                        state.data?.data.some((item) => item.id === 12) ===
                            false
                    );
                }, 'The active list did not settle with the failed item restored and successful item absent.');
            } finally {
                stopObserving();
                observer.destroy();
            }

            assert(
                harness!.client
                    .getQueryData<PaginatedResponse<Upload>>(key)
                    ?.data.some((item) => item.id === 11),
                'The failed delete target was not restored.',
            );
        },
    );

    await runCase(
        'collection-move-invalidates-without-patching-membership',
        async () => {
            const oldFilter = {
                tag: 'blue',
                collection: 'docs',
                page: 1,
                per_page: 2,
            };
            const newFilter = {
                tag: 'blue',
                collection: 'archive',
                page: 1,
                per_page: 2,
            };
            const oldKey = uploadsKeys.list('fixture-parent', 101, oldFilter);
            const newKey = uploadsKeys.list('fixture-parent', 101, newFilter);
            seed(harness!.client, [
                [oldKey, page([upload(51, 'docs', 'blue')], 1, 1)],
                [newKey, page([], 1, 0)],
            ]);
            const mutation = harness!.mutations.update(51, {
                collection: 'archive',
            });
            const gate = await nextGate('put', '/51');
            gate.resolve({ data: uploadPayload(51, 'archive', 'blue') });
            await mutation;
            const oldValue =
                harness!.client.getQueryData<PaginatedResponse<Upload>>(oldKey);
            const newValue =
                harness!.client.getQueryData<PaginatedResponse<Upload>>(newKey);
            assert(
                oldValue?.data[0]?.collection === 'docs',
                'The old collection cache now contains a row moved elsewhere.',
            );
            assert(
                oldValue.data[0]?.id === 51 && newValue?.data.length === 0,
                'A collection move rewrote cached membership before refetch.',
            );
            assert(
                harness!.client.getQueryState(oldKey)?.isInvalidated === true &&
                    harness!.client.getQueryState(newKey)?.isInvalidated ===
                        true,
                'Both affected collection queries must be invalidated after the move.',
            );
        },
    );

    await runCase(
        'upload-success-does-not-prepend-into-unrelated-pages',
        async () => {
            const filters: UploadFilters[] = [
                { tag: 'blue', collection: 'docs', page: 1, per_page: 2 },
                { tag: 'blue', collection: 'docs', page: 2, per_page: 2 },
                { tag: 'green', collection: 'archive', page: 1, per_page: 2 },
            ];
            const entries = seedParentLists();
            const foreignKey = uploadsKeys.list('fixture-parent', 202, {
                tag: 'blue',
                collection: 'docs',
                page: 1,
                per_page: 2,
            });
            const foreignValue = page([upload(91, 'docs', 'blue')], 1, 1);
            seed(harness!.client, [...entries, [foreignKey, foreignValue]]);
            const before = [
                ...snapshotKeys(filters),
                [foreignKey, foreignValue],
            ];
            setFiles([
                new File(['fixture'], 'new-upload.pdf', {
                    type: 'application/pdf',
                }),
            ]);
            const gate = await nextGate('post', '/api/v1/uploads');
            gate.resolve({
                success: true,
                message: 'Uploaded.',
                data: [upload(81, 'misc', 'receipt')],
            });
            await until(
                () =>
                    document
                        .querySelector('[aria-live="polite"]')
                        ?.textContent?.includes('new-upload.pdf') === true,
                'The upload queue did not report completion.',
            );
            await until(() => {
                const queueItem = [
                    ...document.querySelectorAll<HTMLElement>(
                        '#app [aria-live="polite"]',
                    ),
                ].find((item) => item.textContent?.includes('new-upload.pdf'));

                return (
                    queueItem !== undefined &&
                    !queueItem.querySelector(
                        'button[aria-label="Cancelar"], button[aria-label="Descartar"]',
                    )
                );
            }, 'The upload queue did not publish the completed state.');
            await until(
                () =>
                    same(before, [
                        ...snapshotKeys(filters),
                        [foreignKey, harness!.client.getQueryData(foreignKey)],
                    ]),
                'Upload success inserted a row into a page or filter that did not prove membership.',
            );
            assert(
                same(before, [
                    ...snapshotKeys(filters),
                    [foreignKey, harness!.client.getQueryData(foreignKey)],
                ]),
                'Upload success changed unrelated paginated cache data.',
            );
        },
    );

    await runCase(
        'queued-file-does-not-post-to-new-parent-after-switch',
        async () => {
            setFiles([
                new File(['first'], 'first.pdf', { type: 'application/pdf' }),
                new File(['queued'], 'queued-for-a.pdf', {
                    type: 'application/pdf',
                }),
            ]);
            const first = await nextGate('post', '/api/v1/uploads');
            const firstForm = first.config.data as FormData;
            assert(
                firstForm.get('parentable_id') === '101',
                'First upload lost parent A.',
            );
            flushSync(() => harness!.setView({ modelId: 202 }));
            first.resolve({ success: true, message: 'Uploaded.', data: [] });
            await delay(100);
            const posts = gates.filter(
                (gate) =>
                    gate.config.method?.toLowerCase() === 'post' &&
                    (gate.config.url ?? '') === '/api/v1/uploads',
            );
            assert(
                posts.length === 1,
                `A queued file from parent A produced ${posts.length - 1} additional POST(s) after switching to B.`,
            );
        },
    );

    result.textContent = JSON.stringify(
        {
            status:
                checks.every((item) => item.status === 'passed') &&
                forbiddenNetwork === 0
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

const initialPage = {
    component: 'UploadsFixture',
    props: {
        auth: {
            user: {
                id: 902002,
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

const pageComponent = Object.assign(UploadsProbe, {
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

const runButton = document.getElementById('run-contracts')!;
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
