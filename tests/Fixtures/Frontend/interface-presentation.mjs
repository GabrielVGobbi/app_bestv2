import assert from 'node:assert/strict';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

import Header from '../../../resources/js/components/dashboard/header.tsx';
import UserFormFields from '../../../resources/js/pages/app/users/partials/user-form-fields.tsx';

const userFields = renderToStaticMarkup(
    createElement(UserFormFields, {
        user: { name: 'Exemplo', email: 'example@example.test' },
        errors: { name: 'Nome inválido', email: 'E-mail inválido' },
    }),
);
const labels = [...userFields.matchAll(/<label\b([^>]*)>(.*?)<\/label>/g)];
assert.equal(labels.length, 2);
assert.equal(
    labels[0][1].match(/class="([^"]*)"/)[1],
    labels[1][1].match(/class="([^"]*)"/)[1],
);
for (const [id, label] of [
    ['name', 'Nome'],
    ['email', 'E-mail'],
]) {
    assert.match(userFields, new RegExp(`for="${id}"`));
    assert.match(userFields, new RegExp(`${label} <span[^>]*>\\*</span>`));
    assert.match(userFields, new RegExp(`id="${id}"[^>]*required=""`));
}
assert.ok(userFields.includes('value="Exemplo"'));
assert.ok(userFields.includes('value="example@example.test"'));
assert.ok(userFields.includes('Nome inválido'));
assert.ok(userFields.includes('E-mail inválido'));

const empty = renderToStaticMarkup(createElement(Header, { title: 'Cliente' }));
assert.match(empty, /<h1[^>]*>Cliente<\/h1>/);
assert.ok(!empty.includes('<p'));

const detail = renderToStaticMarkup(
    createElement(Header, {
        title: 'User',
        subtitle: 'user@example.test',
        backHref: '/users',
        children: createElement('button', {}, 'Editar'),
    }),
);
assert.match(detail, /href="\/users"/);
assert.match(detail, /aria-label="Voltar"/);
assert.match(detail, /<p[^>]*>user@example.test<\/p>/);
assert.equal((detail.match(/<h1/g) ?? []).length, 1);
assert.ok(detail.includes('Editar'));

const { createInertiaApp } = await import('@inertiajs/react');
const { PrivateStateLayout } =
    await import('../../../resources/js/contexts/private-state-context.tsx');
const { DataTable } =
    await import('../../../resources/js/components/data-table/core/data-table.tsx');
function TablePage({ sticky }) {
    return createElement(DataTable, {
        queryKey: 'interface-test',
        endpoint: '/synthetic-never-requested',
        columns: [{ id: 'name', accessorKey: 'name', header: 'Nome' }],
        keyExtractor: (row) => row.id,
        actions: [{ label: 'Ver', onClick() {} }],
        actionsConfig: { label: 'Ações', sticky },
    });
}

async function renderPage(component, props) {
    return (
        await createInertiaApp({
            page: {
                component: 'Probe',
                props: { ...props, auth: { user: null } },
                url: '/',
                version: null,
                clearHistory: false,
                encryptHistory: false,
            },
            resolve: () =>
                Object.assign(component, { layout: [PrivateStateLayout] }),
            render: renderToStaticMarkup,
            setup: ({ App, props }) => createElement(App, props),
        })
    ).body;
}

const pinned = await renderPage(TablePage, {});
assert.match(pinned, /<th[^>]*data-column-id="actions"[^>]*style="right:0/);
const unpinned = await renderPage(TablePage, { sticky: false });
assert.ok(
    !/<th[^>]*data-column-id="actions"[^>]*style="right:0/.test(unpinned),
);

const { useReactTable, getCoreRowModel } =
    await import('@tanstack/react-table');
const { DataTableToolbar } =
    await import('../../../resources/js/components/data-table/shell/data-table-toolbar.tsx');
function FilterPage() {
    const table = useReactTable({
        data: [],
        columns: [],
        getCoreRowModel: getCoreRowModel(),
    });
    const verified = {
        id: 'verified',
        label: 'Verificação',
        options: [{ value: false, label: 'Não verificado' }],
    };
    return createElement(DataTableToolbar, {
        table,
        features: { filters: true },
        filters: [
            { ...verified, type: 'boolean' },
            {
                id: 'action',
                label: 'Ação',
                type: 'select',
                options: [{ value: 'login', label: 'Login' }],
            },
        ],
        quickFilters: [verified],
        filterValues: { verified: false, action: 'login' },
        dateFilterColumns: [],
        search: '',
        searchPlaceholder: 'Buscar',
        isFetching: false,
        onSearchChange() {},
        onFilterChange() {},
        onResetFilters() {},
        onRefresh() {},
    });
}
const filtered = await renderPage(FilterPage, {});
assert.ok(filtered.includes('aria-label="Verificação: Não verificado"'));
assert.ok(!filtered.includes('aria-label="Remover filtro Verificação:'));
assert.equal(
    (filtered.match(/aria-label="Remover filtro Ação: Login"/g) ?? []).length,
    1,
);
assert.ok(!filtered.includes('aria-label="Filtros ativos"'));

process.stdout.write(JSON.stringify({ status: 'passed' }) + '\n');
