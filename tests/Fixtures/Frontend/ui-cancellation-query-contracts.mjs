import assert from 'node:assert/strict';
import httpNative from 'node:http';
import httpsNative from 'node:https';
import { resolve } from 'node:path';
import process from 'node:process';
import { setImmediate } from 'node:timers/promises';
import { pathToFileURL } from 'node:url';
import { QueryCache, QueryClient, QueryObserver } from '@tanstack/react-query';
import { AxiosError, AxiosHeaders } from 'axios';

let realNetworkRequests = 0;
const denyNetwork = () => {
    realNetworkRequests += 1;

    throw new Error('UI005 fixture forbids real network access.');
};
httpNative.request = denyNetwork;
httpNative.get = denyNetwork;
httpsNative.request = denyNetwork;
httpsNative.get = denyNetwork;
globalThis.fetch = denyNetwork;

const { http, bindPrivateHttpScope } = await import(
    pathToFileURL(resolve('resources/js/lib/http.ts')).href
);
const { uploadsListOptions, uploadTagsOptions } = await import(
    pathToFileURL(resolve('resources/js/modules/uploads/api/queries.ts')).href
);
const { timelineOptions } = await import(
    pathToFileURL(resolve('resources/js/modules/timeline/api/queries.ts')).href
);

// The real HTTP interceptor binds a private identity only in a browser. This
// minimal browser marker exercises that same interceptor without a server/DB.
assert.equal(typeof window, 'undefined');
globalThis.window = {};

const gates = [];
const normalizedErrors = [];
const clients = new Set();
const cleanups = [];
const originalAdapter = http.defaults.adapter;
http.defaults.adapter = (config) =>
    new Promise((resolveResponse, rejectResponse) => {
        const gate = {
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
            reject() {
                if (gate.settled) {
                    return;
                }

                gate.settled = true;
                rejectResponse(
                    new AxiosError(
                        'Synthetic late server failure',
                        'ERR_BAD_RESPONSE',
                        config,
                        undefined,
                        {
                            data: { message: 'Synthetic server error' },
                            status: 500,
                            statusText: 'Error',
                            headers: new AxiosHeaders(),
                            config,
                        },
                    ),
                );
            },
        };
        gates.push(gate);
    });
const errorInterceptor = http.interceptors.response.use(undefined, (error) => {
    normalizedErrors.push(error);

    return Promise.reject(error);
});

const samples = [
    {
        name: 'uploads-list',
        options: () =>
            uploadsListOptions('client', 77, {
                tag: 'old',
                collection: 'documents',
                page: 1,
                per_page: 5,
            }),
        next: () =>
            uploadsListOptions('client', 77, {
                tag: 'new',
                collection: 'documents',
                page: 1,
                per_page: 5,
            }),
        payload: (marker) => ({
            data: [{ id: marker, name: marker }],
            meta: { current_page: 1, per_page: 5, last_page: 1, total: 1 },
            links: {},
        }),
        expectedUrl: '/api/v1/uploads',
        expectedParams: {
            parentable_type: 'client',
            parentable_id: 77,
            tag: 'old',
            collection: 'documents',
            page: 1,
            per_page: 5,
        },
    },
    {
        name: 'upload-tags',
        options: () => uploadTagsOptions('client'),
        next: () => uploadTagsOptions('timeline-comment'),
        payload: (marker) => ({ data: [{ value: marker, label: marker }] }),
        expectedUrl: '/api/v1/uploads/tags',
        expectedParams: { parentable_type: 'client' },
    },
    {
        name: 'timeline',
        options: () => timelineOptions('client', 'synthetic/id', 10),
        next: () => timelineOptions('client', 'synthetic/id', 25),
        payload: (marker) => ({
            data: [{ id: marker, type: 'activity', description: marker }],
            subject: {
                type: 'client',
                id: 'synthetic/id',
                uuid: null,
                label: 'Synthetic subject',
            },
            meta: { next_cursor: null },
        }),
        expectedUrl: '/api/v1/timeline/client/synthetic%2Fid',
        expectedParams: { limit: 10 },
    },
];

function privateScope() {
    const controller = new AbortController();

    return {
        signal: controller.signal,
        isCurrent: () => !controller.signal.aborted,
        invalidate: () => controller.abort(),
    };
}
function bind(scope) {
    const cleanup = bindPrivateHttpScope(scope);
    cleanups.push(cleanup);

    return cleanup;
}
function client() {
    const businessErrors = [];
    const queryClient = new QueryClient({
        queryCache: new QueryCache({
            onError: (error) => businessErrors.push(error),
        }),
        defaultOptions: { queries: { retry: false, gcTime: Infinity } },
    });
    clients.add(queryClient);

    return { queryClient, businessErrors };
}
async function settle() {
    await setImmediate();
    await setImmediate();
}
async function until(check, message) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (check()) {
            return;
        }

        await setImmediate();
    }

    throw new Error(message);
}
async function nextGate(offset) {
    await until(
        () => gates.length > offset,
        'Actual HTTP adapter was not reached.',
    );

    return gates[offset];
}
function assertAbort(gate, message) {
    assert.equal(gate.config.signal?.aborted, true, message);
}
async function cleanup() {
    clients.forEach((queryClient) => queryClient.clear());
    clients.clear();

    while (cleanups.length) {
        cleanups.pop()();
    }

    gates
        .filter((gate) => !gate.settled)
        .forEach((gate) => gate.resolve({ data: [] }));
    await settle();
    gates.length = 0;
    normalizedErrors.length = 0;
}

const checks = [];
async function check(name, run) {
    try {
        await cleanup();
        await run();
        checks.push({ name, status: 'passed' });
    } catch (error) {
        checks.push({ name, status: 'failed', error: String(error) });
    } finally {
        await cleanup();
    }

    process.stdout.write(JSON.stringify(checks.at(-1)) + '\n');
}

try {
    for (const sample of samples) {
        await check(
            `${sample.name}:explicit-cancel-aborts-http-and-restores-cache-without-business-error`,
            async () => {
                const scope = privateScope();
                bind(scope);
                const { queryClient, businessErrors } = client();
                const options = sample.options();
                const cached = sample.payload('cached-before-refetch');
                queryClient.setQueryData(options.queryKey, cached);
                const outcome = queryClient
                    .fetchQuery({ ...options, staleTime: 0 })
                    .then(
                        (data) => ({ data }),
                        (error) => ({ error }),
                    );
                const gate = await nextGate(0);
                assert.equal(gate.config.url, sample.expectedUrl);
                assert.deepEqual(gate.config.params, sample.expectedParams);
                await queryClient.cancelQueries({
                    queryKey: options.queryKey,
                    exact: true,
                });
                assertAbort(
                    gate,
                    'Canceling the query must abort its actual HTTP request.',
                );
                assert.equal(
                    scope.signal.aborted,
                    false,
                    'Canceling one request must keep its private session valid.',
                );
                gate.resolve(sample.payload('obsolete-refetch'));
                await outcome;
                await settle();
                assert.deepEqual(
                    queryClient.getQueryData(options.queryKey),
                    cached,
                    'Canceled refetch must retain its previous data.',
                );
                assert.equal(
                    queryClient.getQueryState(options.queryKey).error,
                    null,
                );
                assert.equal(
                    queryClient.getQueryState(options.queryKey).fetchStatus,
                    'idle',
                );
                assert.deepEqual(
                    businessErrors,
                    [],
                    'Query cancellation must not call the business error channel.',
                );
                assert.equal(
                    normalizedErrors.at(-1)?.type,
                    'canceled',
                    'Actual HTTP cancellation must keep its normalized canceled type.',
                );
            },
        );

        await check(
            `${sample.name}:last-observer-unmount-aborts-and-does-not-warm-stale-cache`,
            async () => {
                const scope = privateScope();
                bind(scope);
                const { queryClient, businessErrors } = client();
                const options = sample.options();
                const observer = new QueryObserver(queryClient, options);
                const unsubscribe = observer.subscribe(() => undefined);
                const gate = await nextGate(0);
                unsubscribe();
                assertAbort(
                    gate,
                    'Removing the final consumer must abort the obsolete HTTP request.',
                );
                gate.resolve(sample.payload('unmounted-result'));
                await settle();
                assert.equal(
                    queryClient.getQueryData(options.queryKey),
                    undefined,
                    'Unmounted request must not populate cache with a late result.',
                );
                assert.equal(
                    queryClient.getQueryState(options.queryKey).error,
                    null,
                );
                assert.deepEqual(businessErrors, []);
                assert.equal(scope.signal.aborted, false);
            },
        );

        await check(
            `${sample.name}:new-filters-win-when-old-transport-finishes-last`,
            async () => {
                bind(privateScope());
                const { queryClient, businessErrors } = client();
                const oldOptions = sample.options();
                const newOptions = sample.next();
                const observer = new QueryObserver(queryClient, oldOptions);
                const unsubscribe = observer.subscribe(() => undefined);
                cleanups.push(unsubscribe);
                const oldGate = await nextGate(0);
                observer.setOptions(newOptions);
                const newGate = await nextGate(1);
                assertAbort(
                    oldGate,
                    'Changing filter/model/page-size must abort the previous consumer request.',
                );
                const expected = sample.payload('current-filter');
                newGate.resolve(expected);
                await until(
                    () => observer.getCurrentResult().isSuccess,
                    'Current filter never loaded.',
                );
                oldGate.resolve(sample.payload('old-filter-finished-last'));
                await settle();
                assert.deepEqual(
                    observer.getCurrentResult().data,
                    expected,
                    'Old response replaced the currently observed filter.',
                );
                assert.deepEqual(
                    queryClient.getQueryData(newOptions.queryKey),
                    expected,
                );
                assert.equal(
                    queryClient.getQueryData(oldOptions.queryKey),
                    undefined,
                    'Obsolete filter transport warmed stale cache.',
                );
                assert.deepEqual(businessErrors, []);
            },
        );

        for (const late of ['success', 'failure']) {
            await check(
                `${sample.name}:ended-session-late-${late}-cannot-enter-new-identity-client`,
                async () => {
                    const a = privateScope();
                    const unbindA = bind(a);
                    const first = client();
                    const options = sample.options();
                    const observerA = new QueryObserver(
                        first.queryClient,
                        options,
                    );
                    const unsubscribeA = observerA.subscribe(() => undefined);
                    const oldGate = await nextGate(0);
                    a.invalidate();
                    unsubscribeA();
                    first.queryClient.clear();
                    unbindA();
                    assertAbort(
                        oldGate,
                        'Ending the private identity must abort its transport.',
                    );

                    const b = privateScope();
                    bind(b);
                    const second = client();
                    const observerB = new QueryObserver(
                        second.queryClient,
                        options,
                    );
                    cleanups.push(observerB.subscribe(() => undefined));
                    const ownGate = await nextGate(1);
                    const expected = sample.payload('identity-B');
                    ownGate.resolve(expected);
                    await until(
                        () => observerB.getCurrentResult().isSuccess,
                        'New identity did not load independently.',
                    );

                    if (late === 'success') {
                        oldGate.resolve(sample.payload('private-identity-A'));
                    } else {
                        oldGate.reject();
                    }

                    await settle();
                    assert.deepEqual(
                        observerB.getCurrentResult().data,
                        expected,
                        'Ended identity changed the current screen data.',
                    );
                    assert.deepEqual(
                        second.queryClient.getQueryData(options.queryKey),
                        expected,
                    );
                    assert.equal(
                        first.queryClient.getQueryData(options.queryKey),
                        undefined,
                    );
                    assert.deepEqual(
                        first.businessErrors,
                        [],
                        'Ended identity emitted a late business error.',
                    );
                    assert.deepEqual(second.businessErrors, []);
                    assert.equal(b.signal.aborted, false);
                    assert.equal(
                        normalizedErrors.at(-1)?.type,
                        'canceled',
                        'Ended identity late success/failure was not normalized as cancellation.',
                    );
                },
            );
        }
    }
} finally {
    await cleanup();
    http.interceptors.response.eject(errorInterceptor);
    http.defaults.adapter = originalAdapter;
    delete globalThis.window;
}

const status = checks.every((item) => item.status === 'passed')
    ? 'passed'
    : 'failed';
process.stdout.write(
    JSON.stringify({ status, checks, realNetworkRequests }) + '\n',
);
process.exitCode = status === 'passed' ? 0 : 1;
