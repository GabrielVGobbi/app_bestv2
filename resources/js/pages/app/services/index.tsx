import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import type { InfiniteData } from '@tanstack/react-query';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, Plus, RotateCcw, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    edit,
    index,
    quickUpdate,
    restore,
    show,
} from '@/actions/App/Modules/Services/Http/Controllers/ServiceController';
import Header from '@/components/dashboard/header';
import { DataTable, InlineCellEditor } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { apiRoutes } from '@/lib/api/api-routes';
import { formatMoney } from '@/lib/utils';
import type { Service } from '@/types/app/service';
import type { Paginated } from '@/types/pagination';

interface Props {
    permissions: {
        can_create: boolean;
        can_update: boolean;
        can_delete: boolean;
        can_restore: boolean;
    };
}

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];

const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Services\\Models\\Service',
    columnsEndpoint: apiRoutes.exports.columns,
};

function updateCachedService(
    queryClient: ReturnType<typeof useQueryClient>,
    serviceUuid: string,
    patch: Partial<Service>,
) {
    queryClient.setQueriesData<InfiniteData<Paginated<Service>>>(
        { queryKey: ['services', 'infinite'], exact: false },
        (old) => {
            if (!old) {
                return old;
            }

            return {
                ...old,
                pages: old.pages.map((page) => ({
                    ...page,
                    data: page.data.map((service) =>
                        service.uuid === serviceUuid
                            ? { ...service, ...patch }
                            : service,
                    ),
                })),
            };
        },
    );
}

function errorMessage(errors: Record<string, string>): string {
    return Object.values(errors)[0] ?? 'Não foi possível salvar o serviço.';
}

function persistServiceName(
    queryClient: ReturnType<typeof useQueryClient>,
    serviceUuid: string,
    name: string,
): Promise<void> {
    return new Promise((resolve, reject) => {
        router.patch(
            quickUpdate.url(serviceUuid),
            {
                field: 'name',
                value: name,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    updateCachedService(queryClient, serviceUuid, { name });
                    resolve();
                },
                onError: (errors) => reject(new Error(errorMessage(errors))),
            },
        );
    });
}

export default function ServiceIndex({ permissions }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Serviços', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [serviceToDelete, setServiceToDelete] = useState<Service | null>(
        null,
    );

    const columns = useMemo<ColumnDef<Service>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Nome',
                size: 280,
                cell: ({ row }) => (
                    <InlineCellEditor
                        mode="inline"
                        label="Editar nome"
                        value={row.original.name}
                        disabled={!permissions.can_update}
                        validate={(value) =>
                            value.trim().length < 3
                                ? 'Informe pelo menos 3 caracteres.'
                                : null
                        }
                        onSave={(name) =>
                            persistServiceName(
                                queryClient,
                                row.original.uuid,
                                name,
                            )
                        }
                    >
                        {({ value, onChange, save, cancel, isLoading }) => (
                            <Input
                                value={value}
                                disabled={isLoading}
                                autoFocus
                                className="h-8"
                                onChange={(event) =>
                                    onChange(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        void save();
                                    }

                                    if (event.key === 'Escape') {
                                        cancel();
                                    }
                                }}
                            />
                        )}
                    </InlineCellEditor>
                ),
                meta: { label: 'Nome' },
            },
            {
                id: 'base_price',
                accessorKey: 'base_price',
                header: 'Preço base',
                cell: ({ row }) => (
                    <span className="tabular-nums">
                        {formatMoney(row.original.base_price, {
                            fromCents: true,
                        })}
                    </span>
                ),
                meta: { label: 'Preço base' },
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.is_active ? 'success' : 'secondary'
                        }
                    >
                        {row.original.is_active ? 'Ativo' : 'Inativo'}
                    </Badge>
                ),
                meta: { label: 'Status' },
            },
        ],
        [permissions.can_update, queryClient],
    );

    const filters = useMemo<DataTableFilterConfig[]>(
        () => [
            {
                id: 'is_active',
                label: 'Status',
                type: 'select',
                operator: 'eq',
                options: [
                    { value: true, label: 'Ativo' },
                    { value: false, label: 'Inativo' },
                ],
            },
        ],
        [],
    );

    const actions = useMemo<DataTableAction<Service>[]>(() => {
        const tableActions: DataTableAction<Service>[] = [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (service) => show.url(service.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
        ];

        if (permissions.can_update) {
            tableActions.push({
                label: 'Editar',
                icon: <Pencil className="size-4" />,
                variant: 'outline',
                href: (service) => edit.url(service.uuid),
                showLabel: false,
                tooltip: 'Editar',
            });
        }

        if (permissions.can_restore) {
            tableActions.push({
                label: 'Reativar',
                icon: <RotateCcw className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Reativar',
                disabled: (service) => service.is_active,
                onClick: (service) => {
                    router.patch(restore.url(service.uuid), undefined, {
                        preserveScroll: true,
                        onSuccess: () => {
                            void queryClient.invalidateQueries({
                                queryKey: ['services'],
                            });
                        },
                    });
                },
            });
        }

        if (permissions.can_delete) {
            tableActions.push({
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (service) => setServiceToDelete(service),
            });
        }

        return tableActions;
    }, [
        permissions.can_delete,
        permissions.can_restore,
        permissions.can_update,
        queryClient,
    ]);

    function deleteService(): void {
        if (!serviceToDelete) {
            return;
        }

        router.delete(destroy.url(serviceToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setServiceToDelete(null);
                void queryClient.invalidateQueries({ queryKey: ['services'] });
            },
        });
    }

    return (
        <>
            <Head title="Serviços" />

            <Header
                title="Serviços"
                subtitle="Catálogo comercial de serviços vendáveis"
            >
                {permissions.can_create ? (
                    <Button asChild>
                        <Link prefetch href={create.url()}>
                            <Plus className="size-4" />
                            Novo serviço
                        </Link>
                    </Button>
                ) : null}
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6">
                    <Dialog
                        open={serviceToDelete !== null}
                        onOpenChange={(open) =>
                            !open && setServiceToDelete(null)
                        }
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Arquivar serviço</DialogTitle>
                                <DialogDescription>
                                    Esta ação remove o serviço de novas
                                    consultas operacionais, preservando o
                                    histórico.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setServiceToDelete(null)}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={deleteService}
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Arquivar
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<Service>
                        queryKey="services"
                        endpoint={apiRoutes.services.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(service) => service.uuid}
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
                        searchPlaceholder="Buscar serviços..."
                        emptyMessage="Nenhum serviço encontrado."
                    />
                </div>
            </div>
        </>
    );
}
