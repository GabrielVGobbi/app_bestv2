import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    edit,
    index,
    show,
} from '@/actions/App/Modules/Companies/Http/Controllers/CompanyController';
import { DataTable } from '@/components/data-table';
import type {
    DataTableAction,
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import Heading from '@/components/heading';
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
import { maskCnpj } from '@/lib/masks';
import type { Company } from '@/types/app/company';

const columns: ColumnDef<Company>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Nome',
        meta: { label: 'Nome' },
    },
    {
        id: 'nickname',
        accessorKey: 'nickname',
        header: 'Apelido',
        meta: { label: 'Apelido' },
    },
    {
        id: 'cnpj',
        accessorKey: 'cnpj',
        header: 'CNPJ',
        cell: ({ row }) => maskCnpj(row.original.cnpj),
        meta: { label: 'CNPJ' },
    },
];

const filters: DataTableFilterConfig[] = [
    {
        id: 'name',
        label: 'Nome',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'nickname',
        label: 'Apelido',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'cnpj',
        label: 'CNPJ',
        type: 'text',
        operator: 'contains',
    },
];

const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];

const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Companies\\Models\\Company',
    columnsEndpoint: apiRoutes.exports.columns,
};

interface Props {
    permissions: {
        can_manage: boolean;
    };
}

export default function CompanyIndex({ permissions }: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Empresas', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [companyToDelete, setCompanyToDelete] = useState<Company | null>(null);

    const actions = useMemo<DataTableAction<Company>[]>(
        () => {
            const tableActions: DataTableAction<Company>[] = [
                {
                    label: 'Visualizar',
                    icon: <Eye className="size-4" />,
                    variant: 'outline',
                    href: (company) => show.url(company.uuid),
                    showLabel: false,
                    tooltip: 'Visualizar',
                },
            ];

            if (!permissions.can_manage) {
                return tableActions;
            }

            tableActions.push({
                label: 'Editar',
                icon: <Pencil className="size-4" />,
                variant: 'outline',
                href: (company) => edit.url(company.uuid),
                showLabel: false,
                tooltip: 'Editar',
            });

            tableActions.push({
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (company) => setCompanyToDelete(company),
            });

            return tableActions;
        },
        [permissions.can_manage],
    );

    function deleteCompany(): void {
        if (!companyToDelete) {
            return;
        }

        router.delete(destroy.url(companyToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setCompanyToDelete(null);
                void queryClient.invalidateQueries({ queryKey: ['companies'] });
            },
        });
    }

    return (
        <>
            <Head title="Empresas" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Empresas"
                            description="Gerencie as empresas internas prestadoras do grupo BestPlay"
                        />
                        {permissions.can_manage ? (
                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus className="size-4" />
                                    Nova Empresa
                                </Link>
                            </Button>
                        ) : null}
                    </div>

                    <Dialog
                        open={companyToDelete !== null}
                        onOpenChange={(open) => !open && setCompanyToDelete(null)}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Excluir empresa</DialogTitle>
                                <DialogDescription>
                                    Esta acao arquiva a empresa e remove ela das consultas operacionais.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    onClick={() => setCompanyToDelete(null)}
                                >
                                    Cancelar
                                </Button>
                                <Button variant="destructive" onClick={deleteCompany}>
                                    Excluir
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <DataTable<Company>
                        queryKey="companies"
                        endpoint={apiRoutes.companies.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(company) => company.uuid}
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
                            label: 'Acoes',
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
                        searchPlaceholder="Buscar empresas..."
                    />
                </div>
            </div>
        </>
    );
}
