import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, Plus, RotateCcw, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    edit,
    index,
    restore,
    show,
} from '@/actions/App/Modules/Playlists/Http/Controllers/TopController';
import Header from '@/components/dashboard/header';
import { DataTable } from '@/components/data-table';
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
import { apiRoutes } from '@/lib/api/api-routes';
import type { Top } from '@/types/app/top';

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
    modelClass: 'App\\Modules\\Playlists\\Models\\Top',
    columnsEndpoint: apiRoutes.exports.columns,
};

export default function PlaylistTopIndex({ permissions }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'TOPs', href: index.url() }],
    });

    const [topToDelete, setTopToDelete] = useState<Top | null>(null);

    const columns = useMemo<ColumnDef<Top>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Nome',
                size: 260,
                cell: ({ row }) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium">
                            {row.original.name}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {row.original.slug}
                        </p>
                    </div>
                ),
                meta: { label: 'Nome' },
            },
            {
                id: 'default_slots_quantity',
                accessorKey: 'default_slots_quantity',
                header: 'Vagas padrão',
                cell: ({ row }) => (
                    <span className="tabular-nums">
                        {row.original.default_slots_quantity}
                    </span>
                ),
                meta: { label: 'Vagas padrão' },
            },
            {
                id: 'sort_order',
                accessorKey: 'sort_order',
                header: 'Ordem',
                cell: ({ row }) => (
                    <span className="tabular-nums">
                        {row.original.sort_order}
                    </span>
                ),
                meta: { label: 'Ordem' },
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
        [],
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

    const actions = useMemo<DataTableAction<Top>[]>(() => {
        const tableActions: DataTableAction<Top>[] = [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (top) => show.url(top.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
        ];

        if (permissions.can_update) {
            tableActions.push({
                label: 'Editar',
                icon: <Pencil className="size-4" />,
                variant: 'outline',
                href: (top) => edit.url(top.uuid),
                showLabel: false,
                tooltip: 'Editar',
            });
        }

        if (permissions.can_restore) {
            tableActions.push({
                label: 'Restaurar',
                icon: <RotateCcw className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Restaurar',
                disabled: (top) => !top.deleted_at,
                onClick: (top) => {
                    router.patch(restore.url(top.uuid), undefined, {
                        preserveScroll: true,
                    });
                },
            });
        }

        if (permissions.can_delete) {
            tableActions.push({
                label: 'Arquivar',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Arquivar',
                onClick: (top) => setTopToDelete(top),
            });
        }

        return tableActions;
    }, [
        permissions.can_delete,
        permissions.can_restore,
        permissions.can_update,
    ]);

    function deleteTop(): void {
        if (!topToDelete) {
            return;
        }

        router.delete(destroy.url(topToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => setTopToDelete(null),
        });
    }

    return (
        <>
            <Head title="TOPs" />

            <Header
                title="TOPs"
                subtitle="Catálogo de posições comerciais reutilizáveis em playlists"
            >
                {permissions.can_create ? (
                    <Button asChild>
                        <Link prefetch href={create.url()}>
                            <Plus className="size-4" aria-hidden="true" />
                            Novo TOP
                        </Link>
                    </Button>
                ) : null}
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Dialog
                        open={topToDelete !== null}
                        onOpenChange={(open) => !open && setTopToDelete(null)}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Arquivar TOP</DialogTitle>
                                <DialogDescription>
                                    Esta ação remove o TOP de novos vínculos e
                                    preserva o histórico comercial.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setTopToDelete(null)}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={deleteTop}
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

                    <DataTable<Top>
                        queryKey="playlist-tops"
                        endpoint={apiRoutes.playlistTops.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(top) => top.uuid}
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
                            gridLines: 'none',
                            rowStyle: 'soft-striped',
                        }}
                        searchPlaceholder="Buscar TOPs"
                    />
                </div>
            </div>
        </>
    );
}
