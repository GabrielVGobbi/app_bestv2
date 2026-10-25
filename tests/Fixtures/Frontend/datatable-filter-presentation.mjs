import assert from 'node:assert/strict';
import http from 'node:http';
import https from 'node:https';
import net from 'node:net';
import process from 'node:process';

let networkCalls = 0;
const denyNetwork = () => {
    networkCalls++;

    throw new Error(
        'Network is forbidden in the DataTable presentation fixture.',
    );
};
http.request = http.get = https.request = https.get = denyNetwork;
net.connect = net.createConnection = globalThis.fetch = denyNetwork;

const { buildFilterChips, filterPlaceholder, filterValueLabel } =
    await import('../../../resources/js/components/data-table/core/filter-presentation.ts');
const { filtersToApiParams } =
    await import('../../../resources/js/components/data-table/core/query-params.ts');
const { createElement } = await import('react');
const { renderToStaticMarkup } = await import('react-dom/server');
const { QuickFilterSelect } =
    await import('../../../resources/js/components/data-table/shell/quick-filter-select.tsx');
const { CategoryContent } =
    await import('../../../resources/js/components/data-table/shell/filter-category-content.tsx');

const booleanFilter = Object.freeze({
    id: 'verified',
    label: 'Verificação',
    type: 'boolean',
    options: Object.freeze([
        Object.freeze({ value: false, label: 'Não verificado' }),
        Object.freeze({ value: 0, label: 'Zero' }),
        Object.freeze({
            value: 'missing',
            label: 'Sem registro',
            operator: 'null',
            filterValue: null,
        }),
    ]),
});
const quantity = Object.freeze({
    id: 'quantity',
    label: 'Quantidade',
    type: 'number-range',
});
const date = Object.freeze({
    id: 'created_at',
    label: 'Criação',
    type: 'date-range',
});
assert.equal(filterValueLabel(booleanFilter, false), 'Não verificado');
assert.equal(filterValueLabel(booleanFilter, 0), 'Zero');
assert.equal(
    filterValueLabel(booleanFilter, [false, 0]),
    'Não verificado, Zero',
);
assert.equal(filterValueLabel(booleanFilter, undefined), 'Todos');
assert.equal(
    filterValueLabel(
        { ...booleanFilter, placeholder: 'Qualquer verificação' },
        '',
    ),
    'Qualquer verificação',
);
assert.equal(filterValueLabel(quantity, { from: 0, to: 15 }), '0 a 15');
assert.equal(filterValueLabel(quantity, { to: 0 }), 'Até 0');
assert.equal(filterValueLabel(quantity, { from: 0 }), 'A partir de 0');
assert.equal(
    filterValueLabel(date, {
        from: '2031-05-02 00:00:00',
        to: '2031-05-10 23:59:59',
    }),
    '02/05/2031 a 10/05/2031',
);
assert.equal(
    filterValueLabel(date, { from: '2031-05-02' }),
    'A partir de 02/05/2031',
);
assert.equal(filterValueLabel(date, { to: '2031-05-10' }), 'Até 10/05/2031');
assert.equal(filterPlaceholder(quantity, 'from'), 'Valor inicial');
assert.equal(filterPlaceholder(quantity, 'to'), 'Valor final');
assert.equal(filterPlaceholder(date, 'from'), 'Data inicial');
assert.equal(filterPlaceholder(date, 'to'), 'Data final');
assert.equal(
    filterPlaceholder({ label: 'Nome', type: 'text' }),
    'Filtrar por Nome',
);
assert.equal(
    filterPlaceholder({ label: 'Quantidade', type: 'number' }),
    'Informe Quantidade',
);
assert.equal(
    filterPlaceholder({ label: 'Criação', type: 'date' }),
    'Selecione a data',
);

const filters = Object.freeze([booleanFilter, quantity, date]);
const values = Object.freeze({
    verified: false,
    quantity: Object.freeze({ from: 0 }),
    created_at: Object.freeze({ to: '2031-05-10' }),
});
const before = JSON.stringify({ filters, values });
assert.deepEqual(buildFilterChips(filters, values), [
    {
        id: 'verified',
        title: 'Verificação',
        label: 'Não verificado',
        value: 'false',
    },
    {
        id: 'quantity',
        title: 'Quantidade',
        label: 'A partir de 0',
        type: 'filter',
    },
    {
        id: 'created_at',
        title: 'Criação',
        label: 'Até 10/05/2031',
        type: 'date',
    },
]);
assert.deepEqual(
    buildFilterChips([booleanFilter], { verified: [false, 0] }).map(
        (chip) => chip.value,
    ),
    ['false', '0'],
);
assert.deepEqual(buildFilterChips(filters, {}), []);
assert.equal(JSON.stringify({ filters, values }), before);

for (const [value, expected] of [
    [false, 'Não verificado'],
    [0, 'Zero'],
    [[false, 0], 'Não verificado, Zero'],
]) {
    const markup = renderToStaticMarkup(
        createElement(QuickFilterSelect, {
            filter: booleanFilter,
            value,
            onChange() {},
        }),
    );
    assert.ok(markup.includes(`aria-label="Verificação: ${expected}"`));
    assert.ok(markup.includes('aria-label="Limpar Verificação"'));
    assert.ok(!markup.includes('role="button"'));
}

const emptyMarkup = renderToStaticMarkup(
    createElement(QuickFilterSelect, {
        filter: booleanFilter,
        value: '',
        onChange() {},
    }),
);
assert.ok(emptyMarkup.includes('aria-label="Verificação"'));
assert.ok(!emptyMarkup.includes('Verificação: Todos'));
assert.ok(!emptyMarkup.includes('aria-label="Limpar Verificação"'));
const rangeMarkup = renderToStaticMarkup(
    createElement(CategoryContent, {
        filter: quantity,
        value: { from: 0 },
        onChange() {},
    }),
);
assert.ok(rangeMarkup.includes('aria-label="Quantidade: início"'));
assert.ok(rangeMarkup.includes('placeholder="Valor inicial"'));
assert.ok(rangeMarkup.includes('placeholder="Valor final"'));
assert.ok(rangeMarkup.includes('value="0"'));
assert.deepEqual(filtersToApiParams({ verified: 'missing' }, [booleanFilter]), {
    'filter[verified][operator]': 'null',
});
assert.deepEqual(filtersToApiParams({ quantity: { from: 0 } }, [quantity]), {
    'filter[quantity][operator]': 'between',
    'filter[quantity][value][from]': 0,
});
assert.equal(networkCalls, 0);
const { createInertiaApp } = await import('@inertiajs/react');
const { PrivateStateLayout } =
    await import('../../../resources/js/contexts/private-state-context.tsx');
const { default: ActivityLogIndex } =
    await import('../../../resources/js/pages/app/activity-log/index.tsx');
const activityPage = await createInertiaApp({
    page: {
        component: 'ActivityLog',
        props: { activityActionCatalog: {}, auth: { user: null } },
        url: '/activity-log',
        version: null,
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: () =>
        Object.assign(ActivityLogIndex, { layout: [PrivateStateLayout] }),
    render: renderToStaticMarkup,
    setup: ({ App, props }) => createElement(App, props),
});
assert.ok(activityPage.body.includes('Filtros'));

for (const label of ['Acao', 'Canal', 'Tipo de alvo']) {
    assert.ok(
        !activityPage.body.includes(`aria-label="${label}:`),
        `${label} must only appear in the panel until active`,
    );
}

assert.equal(networkCalls, 0);
process.stdout.write(
    JSON.stringify({ status: 'passed', network_calls: networkCalls }) + '\n',
);
