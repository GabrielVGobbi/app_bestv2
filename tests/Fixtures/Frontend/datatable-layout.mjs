import assert from 'node:assert/strict';
import {
    clampColumnWidth,
    getStickyGeometry,
    moveColumn,
    resolveColumnWidths,
} from '../../../resources/js/components/data-table/core/table-layout.ts';

assert.equal(clampColumnWidth(9999, 80, 500), 500);
assert.equal(clampColumnWidth(-100, 80, 500), 80);
assert.deepEqual(moveColumn(['select', 'a', 'b', 'actions'], 'b', 'a'), [
    'select',
    'b',
    'a',
    'actions',
]);
assert.deepEqual(moveColumn(['select', 'a', 'b', 'actions'], 'select', 'b'), [
    'select',
    'a',
    'b',
    'actions',
]);
const columns = [
    { id: 'select', size: 44 },
    { id: 'a', size: 100 },
    { id: 'b', size: 120 },
    { id: 'actions', size: 52 },
];
const pinning = { left: ['select', 'a'], right: ['b', 'actions'] };
assert.deepEqual(getStickyGeometry(columns, pinning, 800), {
    select: { left: 0 },
    a: { left: 44 },
    actions: { right: 0 },
    b: { right: 52 },
});
assert.deepEqual(getStickyGeometry(columns, pinning, 300), {
    select: { left: 0 },
    actions: { right: 0 },
});
assert.deepEqual(getStickyGeometry(columns, pinning, 60), {});
assert.deepEqual(pinning.left, ['select', 'a']);

const layoutColumns = [
    { id: 'select', size: 44, resizable: false },
    { id: 'name', size: 150, resizable: true },
    { id: 'email', size: 150, resizable: true },
    { id: 'actions', size: 132, resizable: false },
];
assert.deepEqual(resolveColumnWidths(layoutColumns, {}, 776), {
    select: 44,
    name: 300,
    email: 300,
    actions: 132,
});
assert.deepEqual(resolveColumnWidths(layoutColumns, { name: 200 }, 776), {
    select: 44,
    name: 200,
    email: 400,
    actions: 132,
});
assert.deepEqual(
    resolveColumnWidths(layoutColumns, { name: 200, email: 200 }, 776),
    {
        select: 44,
        name: 200,
        email: 400,
        actions: 132,
    },
);
assert.equal(
    resolveColumnWidths(layoutColumns, { actions: 400, select: 100 }, 776)
        .actions,
    132,
);
assert.deepEqual(
    resolveColumnWidths(
        layoutColumns.filter(({ resizable }) => !resizable),
        {},
        776,
    ),
    { select: 44, actions: 132 },
);
assert.deepEqual(resolveColumnWidths(layoutColumns, {}, 300), {
    select: 44,
    name: 150,
    email: 150,
    actions: 132,
});
assert.equal(
    resolveColumnWidths(
        [{ id: 'a', size: 100, maxSize: 200, resizable: true }],
        {},
        1000,
    ).a,
    200,
);
console.log(JSON.stringify({ status: 'passed', checks: 15 }));
