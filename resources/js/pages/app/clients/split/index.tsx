import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { Columns2, Plus, Table2 } from 'lucide-react';
import { useEffect, useState } from 'react';

import {
    create,
    destroy,
    index,
    split as splitView,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { Client } from '@/types/app/client';

import { ClientSplitView } from '../components/client-split-view';
import {
    clientDetailQueryKey,
    useClientDetails,
} from '../hooks/use-client-master-detail';
import { ClientDeleteDialog } from '../partials/client-delete-dialog';

type ClientViewMode = 'table' | 'split';

const CLIENT_VIEW_STORAGE_PREFIX = 'bestplay:clients:view-mode';

function clientViewStorageKey(userId: number): string {
    return `${CLIENT_VIEW_STORAGE_PREFIX}:${userId}`;
}

function writeStoredViewMode(
    storageKey: string,
    viewMode: ClientViewMode,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(storageKey, viewMode);
    } catch {
        // localStorage is only a preference cache; ignore privacy/quota failures.
    }
}

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
        <>
            <Head title="Clientes - Duas colunas" />

            <div className="flex h-full min-h-0 flex-1 flex-col overflow-hidden px-0 pb-0 md:px-2">
                <ClientDeleteDialog
                    client={clientToDelete}
                    onCancel={() => setClientToDelete(null)}
                    onConfirm={deleteClient}
                />

                <div className="flex shrink-0 justify-end px-2 pt-2 pb-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            size="sm"
                            value="split"
                            onValueChange={(value) => {
                                if (value === 'table') {
                                    selectTableView();
                                }
                            }}
                            aria-label="Visualização de clientes"
                            className="rounded-lg bg-background"
                        >
                            <ToggleGroupItem
                                value="table"
                                aria-label="Visualização em tabela"
                                className="gap-2 px-3"
                            >
                                <Table2 className="size-4" />
                                <span className="text-muted-foreground">
                                    Tabela
                                </span>
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="split"
                                aria-label="Visualização em duas colunas"
                                className="gap-2 px-3"
                            >
                                <Columns2 className="size-4" />
                                <span>Duas colunas</span>
                            </ToggleGroupItem>
                        </ToggleGroup>

                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                Novo cliente
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="min-h-0 flex-1 overflow-hidden px-2 pb-2">
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
        </>
    );
}
