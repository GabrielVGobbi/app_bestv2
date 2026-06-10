import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import { create, destroy, index, show } from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { DataTable } from '@/components/data-table';
import { apiRoutes } from '@/lib/api/api-routes';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { Client } from '@/types/app/client';

import { columns, dateFilterColumns, exportConfig, filters } from './config';
import { ClientDeleteDialog } from './partials/client-delete-dialog';

export default function ClientIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Clients', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [clientToDelete, setClientToDelete] = useState<Client | null>(null);

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
            <Head title="Clients" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex items-center justify-between gap-4">
                        <Heading title="Clients" description="Gerencie os registros de clients" />
                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                Novo Client
                            </Link>
                        </Button>
                    </div>

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
                        dateFilterColumns={dateFilterColumns}
                        actions={actions}
                        actionsConfig={{ display: 'inline', label: 'Ações', buttonSize: 'icon', showTooltips: true, sticky: true }}
                        infiniteScrollConfig={{ fitViewport: true, viewportBottomOffset: 96, minHeight: 320 }}
                        appearance={{ size: 'md', gridLines: 'none', rowStyle: 'soft-striped' }}
                        searchPlaceholder="Buscar clients..."
                    />
                </div>
            </div>
        </>
    );
}
