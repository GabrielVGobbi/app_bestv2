import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { Columns2, Eye, Plus, Table2, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
    create,
    destroy,
    index,
    show,
    split as splitView,
} from '@/actions/App/Modules/Clients/Http/Controllers/ClientController';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { apiRoutes } from '@/lib/api/api-routes';
import type { Client } from '@/types/app/client';

import {
    buildQuickFilters,
    columns,
    dateFilterColumns,
    exportConfig,
    filters,
} from './config';
import type { ClientQuickFilterOptions } from './config';
import { ClientDeleteDialog } from './partials/client-delete-dialog';

type ClientIndexViewMode = 'table' | 'split';

interface ClientIndexProps {
    quickFilterOptions: ClientQuickFilterOptions;
}

const CLIENT_VIEW_STORAGE_PREFIX = 'bestplay:clients:view-mode';

function clientViewStorageKey(userId: number): string {
    return `${CLIENT_VIEW_STORAGE_PREFIX}:${userId}`;
}

function readStoredViewMode(storageKey: string): ClientIndexViewMode {
    if (typeof window === 'undefined') {
        return 'table';
    }

    try {
        return window.localStorage.getItem(storageKey) === 'split'
            ? 'split'
            : 'table';
    } catch {
        return 'table';
    }
}

function writeStoredViewMode(
    storageKey: string,
    viewMode: ClientIndexViewMode,
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

export default function ClientIndex({ quickFilterOptions }: ClientIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Clientes', href: index.url() }],
    });

    const { auth } = usePage().props;
    const viewStorageKey = clientViewStorageKey(auth.user.id);
    const queryClient = useQueryClient();
    const [clientToDelete, setClientToDelete] = useState<Client | null>(null);
    const quickFilters = useMemo(
        () => buildQuickFilters(quickFilterOptions),
        [quickFilterOptions],
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

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Clientes"
                            description="Gerencie contatos comerciais e contratantes"
                        />
                        <div className="flex flex-wrap items-center gap-2">
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                size="sm"
                                value="table"
                                onValueChange={(value) => {
                                    if (value === 'split') {
                                        selectSplitView();
                                    }
                                }}
                                aria-label="Visualizacao de clientes"
                                className="rounded-lg"
                            >
                                {/*
                                <ToggleGroupItem
                                    value="table"
                                    aria-label="Visualizacao em tabela"
                                    className="gap-2 px-3"
                                >
                                    <Table2 className="size-4" />
                                    <span>Tabela</span>
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="split"
                                    aria-label="Visualizacao em duas colunas"
                                    className="gap-2 px-3"
                                >
                                    <Columns2 className="size-4" />
                                    <span className="text-muted-foreground">
                                        Duas colunas
                                    </span>
                                </ToggleGroupItem>
                                */}
                            </ToggleGroup>

                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus className="size-4" />
                                    Novo cliente
                                </Link>
                            </Button>
                        </div>
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
                        searchPlaceholder="Buscar clientes..."
                    />
                </div>
            </div>
        </>
    );
}
