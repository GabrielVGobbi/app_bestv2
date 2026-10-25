import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
    create,
    destroy,
    index,
    show,
    split as splitView,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import Header from '@/components/dashboard/header';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableFilterOption,
} from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { apiRoutes } from '@/lib/api/api-routes';
import { httpGet } from '@/lib/http';
import type { Client } from '@/types/app/client';
import type { Paginated } from '@/types/pagination';
import type { User } from '@/types/user';

import {
    ClientViewSwitch,
    clientViewStorageKey,
    readStoredViewMode,
    writeStoredViewMode,
} from './components/client-view-switch';
import {
    buildQuickFilters,
    columns,
    dateFilterColumns,
    exportConfig,
    filters,
} from './config';
import type { ClientQuickFilterOptions } from './config';
import { ClientDeleteDialog } from './partials/client-delete-dialog';

interface ClientIndexProps {
    quickFilterOptions: ClientQuickFilterOptions;
}

export default function ClientIndex({ quickFilterOptions }: ClientIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Clientes', href: index.url() }],
    });

    const { auth } = usePage().props;
    const viewStorageKey = clientViewStorageKey(auth.user.id);
    const queryClient = useQueryClient();
    const [clientToDelete, setClientToDelete] = useState<Client | null>(null);

    // shortcut: a DataTable não tem mais filtro rápido assíncrono, então os
    // atendentes (até 100) viram opções prontas; mover para o backend se a lista crescer.
    const commercialUsersQuery = useQuery({
        queryKey: ['users', 'commercial', 'quick-filter-options'],
        queryFn: ({ signal }) =>
            httpGet<Paginated<User>>(apiRoutes.users.index, {
                params: { role: 'commercial', per_page: 100 },
                signal,
            }),
        staleTime: 5 * 60_000,
    });
    const responsibleUsers = useMemo<DataTableFilterOption[]>(
        () =>
            (commercialUsersQuery.data?.data ?? []).map((user) => ({
                value: user.id,
                label: user.name,
            })),
        [commercialUsersQuery.data],
    );
    const quickFilters = useMemo(
        () => buildQuickFilters(quickFilterOptions, responsibleUsers),
        [quickFilterOptions, responsibleUsers],
    );

    useEffect(() => {
        if (readStoredViewMode(viewStorageKey) === 'split') {
            router.visit(splitView.url(), {
                replace: true,
                preserveScroll: true,
            });
        }
    }, [viewStorageKey]);

    const actions = useMemo<DataTableAction<Client>[]>(
        () => [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (client) => show.url(client.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (client) => setClientToDelete(client),
            },
        ],
        [],
    );

    function selectSplitView(): void {
        writeStoredViewMode(viewStorageKey, 'split');

        router.visit(splitView.url(), {
            preserveScroll: true,
        });
    }

    function deleteClient(): void {
        if (!clientToDelete) {
            return;
        }

        router.delete(destroy.url(clientToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setClientToDelete(null);
                void queryClient.invalidateQueries({ queryKey: ['clients'] });
            },
        });
    }

    return (
        <>
            <Head title="Clientes" />

            <Header
                title="Clientes"
                subtitle="Gerencie contatos comerciais e contratantes"
            >
                <ClientViewSwitch value="table" onChange={selectSplitView} />
                <Button asChild>
                    <Link href={create.url()}>
                        <Plus className="size-4" aria-hidden="true" />
                        Novo cliente
                    </Link>
                </Button>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <ClientDeleteDialog
                        client={clientToDelete}
                        onCancel={() => setClientToDelete(null)}
                        onConfirm={deleteClient}
                    />

                    <DataTable<Client>
                        queryKey="clients"
                        endpoint={apiRoutes.clients.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(client) => client.uuid}
                        features={{
                            search: true,
                            filters: true,
                            columnVisibility: true,
                            refresh: true,
                            export: true,
                        }}
                        exportConfig={exportConfig}
                        filters={filters}
                        quickFilters={quickFilters}
                        dateFilterColumns={dateFilterColumns}
                        actions={actions}
                        actionsConfig={{
                            display: 'inline',
                            label: 'Ações',
                            buttonSize: 'icon',
                            showTooltips: true,
                            sticky: true,
                        }}
                        infiniteScrollConfig={{
                            fitViewport: true,
                            viewportBottomOffset: 96,
                            minHeight: 320,
                        }}
                        appearance={{
                            size: 'md',
                            gridLines: 'horizontal',
                            rowStyle: 'soft-striped',
                        }}
                        searchPlaceholder="Buscar clientes"
                    />
                </div>
            </div>
        </>
    );
}
