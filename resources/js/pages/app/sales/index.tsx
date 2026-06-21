import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Sales/Http/Controllers/SaleController';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { apiRoutes } from '@/lib/api/api-routes';
import { formatDate, formatMoney } from '@/lib/utils';
import type {
    Sale,
    SaleIndexFilters,
    SalePagePermissions,
} from '@/types/app/sale';

interface Props {
    permissions: SalePagePermissions;
    filters: SaleIndexFilters;
}

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criada em' },
    { value: 'updated_at', label: 'Atualizada em' },
    { value: 'confirmed_at', label: 'Confirmada em' },
    { value: 'finished_at', label: 'Finalizada em' },
    { value: 'canceled_at', label: 'Cancelada em' },
];

const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Sales\\Models\\Sale',
    columnsEndpoint: apiRoutes.exports.columns,
};

const columns: ColumnDef<Sale>[] = [
    {
        id: 'order_number',
        accessorKey: 'order_number',
        header: 'Venda',
        size: 220,
        cell: ({ row }) => (
            <div className="min-w-0">
                <p className="truncate font-medium">
                    {row.original.order_number}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {row.original.title ??
                        row.original.client?.name ??
                        'Venda sem título'}
                </p>
            </div>
        ),
        meta: { label: 'Venda' },
    },
    {
        id: 'client_id',
        accessorKey: 'client.name',
        header: 'Cliente',
        size: 220,
        cell: ({ row }) => row.original.client?.name ?? '-',
        meta: { label: 'Cliente' },
    },
    {
        id: 'company_id',
        accessorKey: 'company.name',
        header: 'Empresa',
        size: 200,
        cell: ({ row }) =>
            row.original.company?.nickname ?? row.original.company?.name ?? '-',
        meta: { label: 'Empresa' },
    },
    {
        id: 'seller_id',
        accessorKey: 'seller.name',
        header: 'Vendedor',
        size: 190,
        cell: ({ row }) => row.original.seller?.name ?? '-',
        meta: { label: 'Vendedor' },
    },
    {
        id: 'status',
        accessorKey: 'status',
        header: 'Status',
        size: 170,
        cell: ({ row }) => (
            <StatusBadge presenter={row.original.status_presenter} />
        ),
        meta: { label: 'Status' },
    },
    {
        id: 'total_amount',
        accessorKey: 'total_amount',
        header: 'Total',
        size: 140,
        cell: ({ row }) =>
            formatMoney(row.original.total_amount, {
                fromCents: true,
                fallback: '-',
            }),
        meta: { label: 'Total' },
    },
    {
        id: 'created_at',
        accessorKey: 'created_at',
        header: 'Criada em',
        size: 170,
        cell: ({ row }) => formatDate(row.original.created_at),
        meta: { label: 'Criada em' },
    },
    {
        id: 'confirmed_at',
        accessorKey: 'confirmed_at',
        header: 'Confirmada em',
        size: 170,
        cell: ({ row }) => formatDate(row.original.confirmed_at),
        meta: { label: 'Confirmada em', initialVisibility: false },
    },
    {
        id: 'finished_at',
        accessorKey: 'finished_at',
        header: 'Finalizada em',
        size: 170,
        cell: ({ row }) => formatDate(row.original.finished_at),
        meta: { label: 'Finalizada em', initialVisibility: false },
    },
];

export default function SaleIndex({ permissions, filters }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Vendas', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [saleToDelete, setSaleToDelete] = useState<Sale | null>(null);

    const tableFilters = useMemo<DataTableFilterConfig[]>(
        () => [
            {
                id: 'status',
                label: 'Status',
                type: 'select',
                operator: 'eq',
                options: filters.statuses,
            },
            {
                id: 'company_id',
                label: 'Empresa',
                type: 'select',
                operator: 'eq',
                options: filters.companies,
            },
            {
                id: 'client_id',
                label: 'Cliente',
                type: 'select',
                operator: 'eq',
                options: filters.clients,
            },
            {
                id: 'seller_id',
                label: 'Vendedor',
                type: 'select',
                operator: 'eq',
                options: filters.sellers,
            },
            {
                id: 'order_number',
                label: 'Número',
                type: 'text',
                operator: 'contains',
                placeholder: 'VEN-000000',
            },
            {
                id: 'title',
                label: 'Título',
                type: 'text',
                operator: 'contains',
                placeholder: 'Campanha, orçamento...',
            },
        ],
        [filters.clients, filters.companies, filters.sellers, filters.statuses],
    );

    const actions = useMemo<DataTableAction<Sale>[]>(() => {
        const tableActions: DataTableAction<Sale>[] = [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (sale) => show.url(sale.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
        ];

        if (permissions.can_delete) {
            tableActions.push({
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (sale) => setSaleToDelete(sale),
            });
        }

        return tableActions;
    }, [permissions.can_delete]);

    function deleteSale(): void {
        if (!saleToDelete) {
            return;
        }

        router.delete(destroy.url(saleToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setSaleToDelete(null);
                void queryClient.invalidateQueries({ queryKey: ['sales'] });
            },
        });
    }

    return (
        <>
            <Head title="Vendas" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Vendas"
                            description="Rascunhos, orçamentos e campanhas comerciais."
                        />
                        {permissions.can_create ? (
                            <Button asChild>
                                <Link prefetch href={create.url()}>
                                    <Plus className="size-4" />
                                    Nova venda
                                </Link>
                            </Button>
                        ) : null}
                    </div>

                    <Dialog
                        open={saleToDelete !== null}
                        onOpenChange={(open) => !open && setSaleToDelete(null)}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Excluir venda</DialogTitle>
                                <DialogDescription>
                                    A venda será arquivada e removida da lista
                                    operacional. O histórico permanece
                                    preservado para auditoria.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setSaleToDelete(null)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={deleteSale}
                                >
                                    Excluir
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<Sale>
                        queryKey="sales"
                        endpoint={apiRoutes.sales.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(sale) => sale.uuid}
                        features={{
                            search: true,
                            filters: true,
                            columnVisibility: true,
                            refresh: true,
                            export: true,
                        }}
                        exportConfig={exportConfig}
                        filters={tableFilters}
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
                            gridLines: 'none',
                            rowStyle: 'soft-striped',
                        }}
                        searchPlaceholder="Buscar vendas..."
                        emptyMessage="Nenhuma venda encontrada."
                    />
                </div>
            </div>
        </>
    );
}
