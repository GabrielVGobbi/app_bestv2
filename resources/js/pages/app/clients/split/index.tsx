import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

import {
    create,
    destroy,
    index,
    split as splitView,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import Header from '@/components/dashboard/header';
import { Button } from '@/components/ui/button';
import type { Client } from '@/types/app/client';

import { ClientSplitView } from '../components/client-split-view';
import {
    ClientViewSwitch,
    clientViewStorageKey,
    writeStoredViewMode,
} from '../components/client-view-switch';
import {
    clientDetailQueryKey,
    useClientDetails,
} from '../hooks/use-client-master-detail';
import { ClientDeleteDialog } from '../partials/client-delete-dialog';

export default function ClientSplitIndex() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clientes', href: index.url() },
            { title: 'Duas colunas', href: splitView.url() },
        ],
    });

    const { auth } = usePage().props;
    const viewStorageKey = clientViewStorageKey(auth.user.id);
    const queryClient = useQueryClient();
    const [clientSearch, setClientSearch] = useState('');
    const [selectedUuid, setSelectedUuid] = useState<string | null>(null);
    const [clientToDelete, setClientToDelete] = useState<Client | null>(null);
    const detailsQuery = useClientDetails(selectedUuid);
    const selectedClient = detailsQuery.data?.data ?? null;

    useEffect(() => {
        writeStoredViewMode(viewStorageKey, 'split');
    }, [viewStorageKey]);

    function selectClient(client: Client): void {
        if (client.uuid === selectedUuid) {
            return;
        }

        setSelectedUuid(client.uuid);
    }

    function selectTableView(): void {
        writeStoredViewMode(viewStorageKey, 'table');

        router.visit(index.url(), {
            preserveScroll: true,
        });
    }

    function deleteClient(): void {
        if (!clientToDelete) {
            return;
        }

        const deletedUuid = clientToDelete.uuid;

        router.delete(destroy.url(clientToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setClientToDelete(null);

                if (selectedUuid === deletedUuid) {
                    setSelectedUuid(null);
                }

                void queryClient.invalidateQueries({ queryKey: ['clients'] });
                void queryClient.removeQueries({
                    queryKey: clientDetailQueryKey(deletedUuid),
                });
            },
        });
    }

    return (
        <div className="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
            <Head title="Clientes em duas colunas" />

            <Header title="Clientes" subtitle="Lista e detalhes lado a lado">
                <ClientViewSwitch value="split" onChange={selectTableView} />
                <Button asChild>
                    <Link href={create.url()}>
                        <Plus className="size-4" aria-hidden="true" />
                        Novo cliente
                    </Link>
                </Button>
            </Header>

            <ClientDeleteDialog
                client={clientToDelete}
                onCancel={() => setClientToDelete(null)}
                onConfirm={deleteClient}
            />

            <div className="min-h-0 flex-1 overflow-hidden px-4 pb-4 md:px-6">
                <ClientSplitView
                    selectedUuid={selectedUuid}
                    selectedClient={selectedClient}
                    search={clientSearch}
                    isLoading={detailsQuery.isLoading}
                    isFetching={detailsQuery.isFetching}
                    error={detailsQuery.error}
                    onSearchChange={setClientSearch}
                    onSelectClient={selectClient}
                    onDeleteClient={setClientToDelete}
                    onRetryDetails={() => void detailsQuery.refetch()}
                />
            </div>
        </div>
    );
}
