import { createInertiaApp } from '@inertiajs/react';
import { AxiosHeaders } from 'axios';
import { useEffect } from 'react';
import { createRoot } from 'react-dom/client';

import '../../../resources/css/app.css';
import { DataTable } from '@/components/data-table';
import {
    PrivateStateLayout,
    usePrivateState,
} from '@/contexts/private-state-context';
import { http } from '@/lib/http';

type FixtureRow = { id: number; name: string; email: string; amount: number };
const account =
    new URL(location.href).searchParams.get('account') === 'B' ? 902 : 901;
const requests: { path: string; params: Record<string, unknown> }[] = [];
const text =
    'Texto completo longo para leitura acessível sem serializar componentes React. '.repeat(
        5,
    );
http.defaults.adapter = async (config) => {
    requests.push({ path: config.url ?? '', params: config.params ?? {} });
    let data: unknown = { data: [], count: 0 };

    if (config.url === '/fixture/rows') {
        const page = Number(config.params?.page ?? 1);
        const perPage = Number(config.params?.per_page ?? 30);
        const search = String(config.params?.search ?? '');
        data = {
            data: Array.from({ length: 3 }, (_, index) => ({
                id: (page - 1) * 3 + index + 1,
                name: `${search || 'Pessoa'} ${page}-${index + 1}`,
                email: text,
                amount: index,
            })),
            meta: {
                current_page: page,
                per_page: perPage,
                last_page: 3,
                total: 9,
                from: (page - 1) * 3 + 1,
                to: page * 3,
            },
        };
    }

    return {
        config,
        data,
        status: 200,
        statusText: 'OK',
        headers: new AxiosHeaders(),
    };
};

function Probe() {
    const { scope } = usePrivateState();
    useEffect(() => {
        Object.assign(window, {
            tableProbe: {
                requests,
                logout: () => scope.invalidate(),
                account,
                text,
            },
        });
    }, [scope]);

    return (
        <main className="mx-auto max-w-6xl p-6">
            <h1 className="mb-5 text-xl">
                Preferências da tabela: conta {account}
            </h1>
            <DataTable<FixtureRow>
                queryKey="preferences-fixture"
                tableId="preferences-fixture"
                endpoint="/fixture/rows"
                columns={[
                    {
                        accessorKey: 'name',
                        header: 'Nome',
                        size: 200,
                        meta: { label: 'Nome' },
                    },
                    {
                        accessorKey: 'email',
                        header: 'Descrição',
                        size: 250,
                        meta: { label: 'Descrição', truncate: true },
                    },
                    {
                        accessorKey: 'amount',
                        header: 'Valor',
                        size: 120,
                        meta: { label: 'Valor' },
                    },
                ]}
                keyExtractor={(row) => row.id}
                features={{ selection: true, filters: true }}
                bulkActions={[
                    {
                        id: 'inspect',
                        label: 'Revisar',
                        onClick: () => undefined,
                    },
                ]}
                quickFilters={[
                    {
                        id: 'active',
                        label: 'Status',
                        options: [
                            { value: false, label: 'Inativo' },
                            { value: true, label: 'Ativo' },
                        ],
                    },
                ]}
                filters={[
                    { id: 'amount', label: 'Valor', type: 'number-range' },
                    { id: 'name', label: 'Nome', type: 'text' },
                ]}
                dateFilterColumns={[
                    { value: 'created_at', label: 'Criado em' },
                ]}
                actions={[{ label: 'Abrir', onClick: () => undefined }]}
                actionsConfig={{ sticky: true }}
                infiniteScrollConfig={{ height: '280px' }}
                appearance={{ size: 'md', gridLines: 'both' }}
            />
        </main>
    );
}

void createInertiaApp({
    page: {
        component: 'DataTablePreferencesFixture',
        props: {
            auth: {
                user: {
                    id: account,
                    email_verified_at: '2026-01-01',
                    must_change_password: false,
                },
            },
        },
        url: location.pathname + location.search,
        version: null,
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: async () => Object.assign(Probe, { layout: [PrivateStateLayout] }),
    strictMode: true,
    setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />),
});
