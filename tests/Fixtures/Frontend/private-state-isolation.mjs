import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import httpNative from 'node:http';
import httpsNative from 'node:https';
import { resolve } from 'node:path';
import process from 'node:process';
import { setImmediate } from 'node:timers';
import { pathToFileURL } from 'node:url';
import { createInertiaApp, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { AxiosHeaders } from 'axios';
import React, { StrictMode } from 'react';
import { renderToString } from 'react-dom/server';

const phase = (name) =>
    process.stdout.write(JSON.stringify({ phase: name }) + '\n');
phase('initial-imports-ready');

const denyNetwork = () => {
    throw new Error('External transport forbidden by SEC020 fixture.');
};
httpNative.request = denyNetwork;
httpNative.get = denyNetwork;
httpsNative.request = denyNetwork;
httpsNative.get = denyNetwork;
globalThis.fetch = denyNetwork;

const boundaryPath = resolve('resources/js/contexts/private-state-context.tsx');

if (!existsSync(boundaryPath)) {
    process.stdout.write(
        JSON.stringify({
            status: 'wiring_missing',
            reason: 'Private state boundary is not implemented; this is not a behavioral RED receipt.',
        }) + '\n',
    );
    process.exit(0);
}

const { createPrivateStateScope, PrivateStateLayout, usePrivateState } =
    await import(pathToFileURL(boundaryPath).href);
const { http, httpGet, bindPrivateHttpScope } = await import(
    pathToFileURL(resolve('resources/js/lib/http.ts')).href
);
const { useZipDownloadStore } = await import(
    pathToFileURL(
        resolve('resources/js/modules/uploads/stores/zip-download-store.ts'),
    ).href
);
phase('private-modules-ready');

const user = (id, overrides = {}) => ({
    id,
    name: `SEC020 user ${id}`,
    email: `sec020-${id}@example.test`,
    email_verified_at: '2026-10-17T00:00:00Z',
    must_change_password: false,
    ...overrides,
});
const a = createPrivateStateScope(user(101), 1);
const b = createPrivateStateScope(user(202), 2);
const aAgain = createPrivateStateScope(user(101), 3);
assert.equal(a.userId, 101);
assert.equal(a.canRead, true);
assert.equal(a.isCurrent(), true);
assert.equal(b.isCurrent(), true);
assert.notEqual(
    a.storageKey('data-table-exports'),
    b.storageKey('data-table-exports'),
);
assert.notEqual(a.storageKey('data-table-exports'), 'data-table-exports');
assert.equal(
    a.storageKey('data-table-exports'),
    aAgain.storageKey('data-table-exports'),
);
a.invalidate();
a.invalidate();
assert.equal(a.signal.aborted, true);
assert.equal(a.isCurrent(), false);
assert.equal(b.signal.aborted, false);
assert.equal(aAgain.isCurrent(), true);

for (const account of [
    null,
    user(303, { email_verified_at: null }),
    user(404, { must_change_password: true }),
]) {
    const scope = createPrivateStateScope(account, 4);
    assert.equal(scope.canRead, false);
    assert.equal(scope.storageKey('database-exports'), null);
    assert.equal(scope.isCurrent(), false);
    scope.invalidate();
}

phase('epoch-contract-passed');

const persisted = new Map();
globalThis.window = {
    localStorage: {
        get length() {
            return persisted.size;
        },
        key: (index) => [...persisted.keys()][index] ?? null,
        removeItem: (key) => persisted.delete(key),
    },
};

try {
    const preserved = createPrivateStateScope(user(909), 5);
    const ownedKey = preserved.storageKey('data-table-exports');
    persisted.set(ownedKey, 'owned state');
    persisted.set('private-state:808:data-table-exports', 'other owner');
    let disposed = 0;
    preserved.onInvalidate(() => {
        disposed++;
    });
    preserved.invalidate({ preserveStorage: true });
    assert.equal(preserved.signal.aborted, true);
    assert.equal(preserved.isCurrent(), false);
    assert.equal(persisted.get(ownedKey), 'owned state');
    assert.equal(disposed, 1);
    preserved.invalidate();
    preserved.invalidate();
    assert.equal(
        persisted.has(ownedKey),
        false,
        'A later mandatory purge must still run after preserved invalidation.',
    );
    assert.equal(
        persisted.get('private-state:808:data-table-exports'),
        'other owner',
    );
    assert.equal(disposed, 1, 'Abort and resource cleanup remain idempotent.');
} finally {
    delete globalThis.window;
}

phase('storage-escalation-passed');

const observations = [];
const gates = new Map();
const previousAdapter = http.defaults.adapter;
const response = (config, payload) => ({
    data: payload,
    status: 200,
    statusText: 'OK',
    headers: new AxiosHeaders(),
    config,
});
http.defaults.adapter = (config) => {
    assert.ok(config.url.startsWith('https://sec020.example.test/'));
    observations.push({ url: config.url, signal: config.signal });

    return new Promise((resolveResponse, rejectResponse) =>
        gates.set(config.url, {
            config,
            resolve: resolveResponse,
            reject: rejectResponse,
        }),
    );
};
const gate = async (url) => {
    for (let attempt = 0; attempt < 20 && !gates.has(url); attempt++) {
        await new Promise(setImmediate);
    }

    assert.ok(
        gates.has(url),
        'The real Axios adapter must receive the owned request.',
    );

    return gates.get(url);
};
const effects = [];
globalThis.window = {};
let cleanupA;
let cleanupB;

try {
    const transportA = createPrivateStateScope(user(101), 10);
    cleanupA = bindPrivateHttpScope(transportA);
    const caller = new AbortController();
    const canceledUrl = 'https://sec020.example.test/caller-abort';
    const canceled = httpGet(canceledUrl, { signal: caller.signal }).then(
        () => 'unexpected-success',
        (error) => error,
    );
    const callerGate = await gate(canceledUrl);
    caller.abort();
    assert.equal(callerGate.config.signal.aborted, true);
    assert.equal(
        transportA.signal.aborted,
        false,
        'A per-request abort must not invalidate the identity.',
    );
    callerGate.resolve(
        response(callerGate.config, { marker: 'must not resolve' }),
    );
    assert.equal((await canceled).type, 'canceled');

    const successUrl = 'https://sec020.example.test/late-success-a';
    const errorUrl = 'https://sec020.example.test/late-error-a';
    const lateSuccess = httpGet(successUrl).then(
        (value) => effects.push(value),
        (error) => error,
    );
    const lateError = httpGet(errorUrl).then(
        (value) => effects.push(value),
        (error) => error,
    );
    const successGate = await gate(successUrl);
    const errorGate = await gate(errorUrl);
    transportA.invalidate();
    cleanupA();
    cleanupA();
    assert.equal(successGate.config.signal.aborted, true);
    assert.equal(errorGate.config.signal.aborted, true);

    const transportB = createPrivateStateScope(user(202), 11);
    cleanupB = bindPrivateHttpScope(transportB);
    const ownUrl = 'https://sec020.example.test/current-b';
    const current = httpGet(ownUrl);
    const ownGate = await gate(ownUrl);
    ownGate.resolve(
        response(ownGate.config, { marker: 'private B row', count: 1 }),
    );
    assert.deepEqual(await current, { marker: 'private B row', count: 1 });
    successGate.resolve(
        response(successGate.config, { marker: 'private A row' }),
    );
    errorGate.reject(new Error('SEC020 synthetic delayed A failure'));
    assert.equal((await lateSuccess).type, 'canceled');
    assert.equal((await lateError).type, 'canceled');
    assert.deepEqual(effects, []);
    assert.equal(transportB.isCurrent(), true);
    assert.equal(transportB.signal.aborted, false);
} finally {
    cleanupA?.();
    cleanupB?.();
    http.defaults.adapter = previousAdapter;
    delete globalThis.window;
}

phase('transport-contract-passed');

assert.equal(typeof document, 'undefined');
assert.equal(typeof localStorage, 'undefined');
assert.equal(typeof window, 'undefined');
const rendered = [];
function ProbePage() {
    const account = usePage().props.auth.user;
    const privateState = usePrivateState();
    const client = useQueryClient();
    const jobs = useZipDownloadStore((state) => state.jobs);
    const addJob = useZipDownloadStore((state) => state.addJob);
    const previous = client.getQueryData(['sec020-private-key']) ?? null;
    client.setQueryData(['sec020-private-key'], `cache-user-${account.id}`);
    rendered.push({
        client,
        scope: privateState.scope,
        jobs,
        addJob,
        previous,
    });

    return React.createElement(
        'section',
        { 'data-identity': account.id },
        `SSR identity ${account.id}`,
    );
}
async function renderIdentity(id) {
    return createInertiaApp({
        page: {
            component: 'Sec020Probe',
            props: { auth: { user: user(id) }, errors: {} },
            url: '/sec020-owned',
            version: null,
            clearHistory: false,
            encryptHistory: false,
        },
        resolve: () => ProbePage,
        render: renderToString,
        setup: ({ App, props }) =>
            React.createElement(
                StrictMode,
                null,
                React.createElement(App, {
                    ...props,
                    defaultLayout: () => [PrivateStateLayout],
                }),
            ),
    });
}
const first = await renderIdentity(101);
phase('ssr-first-rendered');
const second = await renderIdentity(202);
const third = await renderIdentity(101);
assert.equal(rendered.length, 3);
assert.notEqual(rendered[0].client, rendered[1].client);
assert.notEqual(rendered[0].scope, rendered[1].scope);
assert.notEqual(
    rendered[0].addJob,
    rendered[1].addJob,
    'ZIP store actions must belong to separate SSR trees.',
);
assert.notEqual(
    rendered[0].client,
    rendered[2].client,
    'Even the same owner needs a fresh SSR request cache.',
);
assert.notEqual(rendered[0].scope, rendered[2].scope);
assert.notEqual(rendered[0].addJob, rendered[2].addJob);
assert.equal(rendered[2].previous, null);
assert.deepEqual(rendered[2].jobs, []);
assert.equal(
    rendered[2].client.getQueryData(['sec020-private-key']),
    'cache-user-101',
);
assert.equal(rendered[0].previous, null);
assert.equal(rendered[1].previous, null);
assert.deepEqual(rendered[0].jobs, []);
assert.deepEqual(rendered[1].jobs, []);
assert.equal(
    rendered[0].client.getQueryData(['sec020-private-key']),
    'cache-user-101',
);
assert.equal(
    rendered[1].client.getQueryData(['sec020-private-key']),
    'cache-user-202',
);
assert.equal(rendered[0].scope.userId, 101);
assert.equal(rendered[1].scope.userId, 202);
assert.ok(first.body.includes('SSR identity 101'));
assert.ok(!first.body.includes('SSR identity 202'));
assert.ok(second.body.includes('SSR identity 202'));
assert.ok(!second.body.includes('SSR identity 101'));
assert.ok(third.body.includes('SSR identity 101'));
assert.ok(!third.body.includes('SSR identity 202'));
assert.equal(observations.length, 4, 'SSR must not start HTTP work.');
rendered.forEach(({ client, scope }) => {
    client.clear();
    scope.invalidate();
});
phase('ssr-contract-passed');

const allowed = new Set([
    'SYSTEMROOT',
    'WINDIR',
    'COMSPEC',
    'PATH',
    'PATHEXT',
    'TEMP',
    'TMP',
    'USERPROFILE',
    'LOCALAPPDATA',
    'APPDATA',
]);
const unexpectedEnvironment = Object.keys(process.env).filter(
    (key) =>
        !allowed.has(key.toUpperCase()) &&
        !(key.toUpperCase() === 'PROMPT' && process.env[key] === '$P$G'),
);
assert.deepEqual(unexpectedEnvironment, []);
process.stdout.write(
    JSON.stringify({
        status: 'passed',
        contracts: {
            epochAndEligibility: true,
            partitionedStorageKeys: true,
            storagePurgeEscalation: true,
            combinedCancellation: true,
            delayedResultsDiscarded: true,
            activeIdentityRecovery: true,
            ssrPerRequestQueryClient: true,
            ssrPerRequestZipStore: true,
            ssrNoBrowserGlobalsOrHttp: true,
        },
        adapterCalls: observations.length,
        ssrIdentities: [101, 202, 101],
        unexpectedEnvironment,
    }) + '\n',
);
