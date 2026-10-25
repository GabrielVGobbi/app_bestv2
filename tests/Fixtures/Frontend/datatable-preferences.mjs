import assert from 'node:assert/strict';
import http from 'node:http';
import https from 'node:https';
import net from 'node:net';
import process from 'node:process';

let networkCalls = 0;
const denyNetwork = () => {
    networkCalls++;

    throw new Error('Network forbidden in DataTable preference contracts.');
};
http.request = http.get = https.request = https.get = denyNetwork;
net.connect = net.createConnection = globalThis.fetch = denyNetwork;

const {
    resolveTablePreferencesConfig,
    readTablePreferences,
    writeTablePreferences,
    sanitizeColumnVisibility,
    sanitizeColumnOrder,
    sanitizeColumnPinning,
    sanitizeColumnSizing,
} =
    await import('../../../resources/js/components/data-table/core/table-preferences.ts');

const values = new Map();
const storage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
};
let current = true;
const scope = { userId: 101, isCurrent: () => current };
const config = resolveTablePreferencesConfig({
    scope,
    queryKey: 'clients',
    persistState: true,
});
const context = {
    config,
    scope,
    getStorage: () => storage,
    columnIds: ['select', 'name', 'email', 'actions'],
    filterIds: ['status'],
    initialColumnVisibility: { email: false },
};
assert.equal(config.key, 'datatable-preferences:v1:101:clients');
assert.equal(
    resolveTablePreferencesConfig({
        scope,
        queryKey: 'clients',
        persistState: { key: 'separate-view' },
    }).key,
    'datatable-preferences:v1:101:separate-view',
);
assert.equal(
    resolveTablePreferencesConfig({
        scope,
        queryKey: 'legacy',
        tableId: 'stable',
        persistState: true,
    }).key,
    'datatable-preferences:v1:101:stable',
);
assert.equal(
    resolveTablePreferencesConfig({
        scope,
        queryKey: 'clients',
        persistState: false,
    }),
    null,
);

values.set('datatable-state:clients', JSON.stringify({ search: 'unowned' }));
values.set(
    'private-state:202:datatable-state:clients',
    JSON.stringify({ search: 'another account' }),
);
assert.deepEqual(readTablePreferences(context).preferences, {});
values.set(
    'private-state:101:datatable-state:clients',
    JSON.stringify({
        search: 'owned',
        perPage: 50,
        rows: [{ secret: 1 }],
        rowSelection: { secret: true },
    }),
);
const migrated = readTablePreferences(context);
assert.equal(migrated.preferences.search, 'owned');
assert.equal(migrated.preferences.perPage, 50);
assert.equal(migrated.storageStatus, 'saved');
assert.equal(values.has('private-state:101:datatable-state:clients'), false);
assert.equal(values.has('private-state:202:datatable-state:clients'), true);
const payload = JSON.parse(values.get('datatable-preferences:v1:101:clients'));
assert.equal(payload.version, 1);
assert.equal(payload.rows, undefined);
assert.equal(payload.rowSelection, undefined);
assert.equal(payload.page, undefined);

values.set(
    config.key,
    JSON.stringify({
        version: 1,
        search: 'new',
        filters: { status: 'active', unknown: 'secret' },
        sorting: [
            { id: 'gone', desc: false },
            { id: 'name', desc: true },
        ],
        columnOrder: ['email', 'gone', 'email'],
        columnPinning: { left: ['name'], right: ['name', 'email'] },
        columnSizing: { name: 180, gone: 50, email: -1 },
        columnVisibility: {
            name: false,
            email: false,
            select: false,
            actions: false,
        },
        density: 'sm',
    }),
);
values.set(config.legacyKey, JSON.stringify({ search: 'old' }));
const restored = readTablePreferences(context);
assert.equal(restored.preferences.search, 'new');
assert.deepEqual(restored.preferences.filters, { status: 'active' });
assert.deepEqual(restored.preferences.sorting, [{ id: 'name', desc: true }]);
assert.deepEqual(restored.preferences.columnOrder, [
    'select',
    'email',
    'name',
    'actions',
]);
assert.deepEqual(restored.preferences.columnPinning, {
    left: ['name'],
    right: ['email'],
});
assert.deepEqual(restored.preferences.columnSizing, { name: 180 });
assert.equal(restored.preferences.columnVisibility.name, true);
assert.equal(restored.preferences.columnVisibility.select, true);
assert.equal(restored.preferences.columnVisibility.actions, true);
assert.equal(restored.preferences.density, 'sm');
assert.equal(values.has(config.legacyKey), true);

const custom = resolveTablePreferencesConfig({
    scope,
    queryKey: 'old-query',
    tableId: 'custom-table',
    persistState: { key: 'custom-old-key' },
});
values.set(
    'private-state:101:custom-old-key',
    JSON.stringify({ search: 'custom owned' }),
);
const customResult = readTablePreferences({
    ...context,
    config: custom,
    getStorage: () => ({
        ...storage,
        removeItem: () => {
            throw new Error('removal blocked');
        },
    }),
});
assert.equal(customResult.preferences.search, 'custom owned');
assert.equal(customResult.storageStatus, 'unavailable');
assert.equal(
    JSON.parse(values.get('datatable-preferences:v1:101:custom-table')).search,
    'custom owned',
);
assert.equal(values.has('private-state:101:custom-old-key'), true);

for (const corrupt of ['{', '[]', '{"version":2,"search":"future"}']) {
    values.set(config.key, corrupt);
    assert.deepEqual(readTablePreferences(context), {
        preferences: {},
        storageStatus: 'invalid',
    });
    assert.equal(values.get(config.key), corrupt);
}

assert.equal(
    readTablePreferences({
        ...context,
        getStorage: () => {
            throw new Error('blocked');
        },
    }).storageStatus,
    'unavailable',
);
assert.equal(
    writeTablePreferences({
        ...context,
        preferences: { search: 'changed' },
        getStorage: () => ({
            ...storage,
            setItem: () => {
                throw new Error('quota');
            },
        }),
    }),
    'unavailable',
);
values.delete(config.key);
assert.equal(
    readTablePreferences({
        ...context,
        getStorage: () => ({
            ...storage,
            setItem: () => {
                throw new Error('quota');
            },
        }),
    }).storageStatus,
    'unavailable',
);
assert.equal(values.has(config.legacyKey), true);

const disabled = resolveTablePreferencesConfig({
    scope,
    queryKey: 'clients',
    persistState: {
        search: false,
        filters: false,
        layout: false,
        density: false,
        columnVisibility: false,
    },
});
assert.equal(
    writeTablePreferences({
        ...context,
        config: disabled,
        preferences: {
            search: 'private',
            filters: { status: true },
            perPage: 20,
            sorting: [],
            columnVisibility: { name: false },
            columnOrder: ['email'],
            columnPinning: { left: ['email'] },
            columnSizing: { email: 200 },
            density: 'sm',
            rows: [{ secret: true }],
            rowSelection: { secret: true },
        },
    }),
    'saved',
);
assert.deepEqual(JSON.parse(values.get(config.key)), {
    version: 1,
    perPage: 20,
    sorting: [],
});

const other = resolveTablePreferencesConfig({
    scope: { userId: 202, isCurrent: () => true },
    queryKey: 'clients',
    persistState: true,
});
assert.equal(
    readTablePreferences({
        ...context,
        scope: { userId: 202, isCurrent: () => true },
        config: other,
    }).preferences.search,
    'another account',
);
assert.deepEqual(
    sanitizeColumnVisibility(
        {
            name: false,
            email: false,
            select: false,
            actions: false,
            gone: true,
        },
        context.columnIds,
    ),
    { name: true, email: false, select: true, actions: true },
);
assert.deepEqual(
    sanitizeColumnVisibility(
        { name: false, email: false },
        context.columnIds,
        {},
        ['email'],
    ),
    { name: false, email: true, select: true, actions: true },
);
assert.deepEqual(
    sanitizeColumnOrder(
        ['email', 'select', 'email', 'gone', 'actions'],
        context.columnIds,
    ),
    ['select', 'email', 'name', 'actions'],
);
assert.deepEqual(
    sanitizeColumnPinning(
        {
            left: ['name', 'name', 'select', 'gone'],
            right: ['name', 'email', 'actions'],
        },
        context.columnIds,
    ),
    { left: ['name'], right: ['email'] },
);
assert.deepEqual(
    sanitizeColumnPinning(
        { left: ['email', 'actions'], right: ['name', 'select'] },
        context.columnIds,
        { left: ['select'], right: ['actions'] },
    ),
    { left: ['select', 'email'], right: ['name', 'actions'] },
);
assert.deepEqual(
    sanitizeColumnSizing(
        { name: 100, email: Infinity, select: 40, actions: 90 },
        context.columnIds,
    ),
    { name: 100 },
);

current = false;
const before = [...values];
assert.equal(
    writeTablePreferences({ ...context, preferences: { search: 'late' } }),
    'disabled',
);
assert.deepEqual(readTablePreferences(context), {
    preferences: {},
    storageStatus: 'disabled',
});
assert.deepEqual([...values], before);
assert.equal(networkCalls, 0);
process.stdout.write(
    JSON.stringify({
        status: 'passed',
        cases: 9,
        network_calls: networkCalls,
    }) + '\n',
);
