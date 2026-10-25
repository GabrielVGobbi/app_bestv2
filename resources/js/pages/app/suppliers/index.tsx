import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Suppliers/Http/Controllers/SupplierController';
import ContentHeader from '@/components/content-header';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { apiRoutes } from '@/lib/api/api-routes';
import type { Supplier } from '@/types/app/supplier';

import { columns, dateFilterColumns, exportConfig, filters } from './config';
import { SupplierDeleteDialog } from './partials/supplier-delete-dialog';

export default function SupplierIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Fornecedores', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [supplierToDelete, setSupplierToDelete] = useState<Supplier | null>(
        null,
    );

    const actions = useMemo<DataTableAction<Supplier>[]>(
        () => [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (supplier) => show.url(supplier.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (supplier) => setSupplierToDelete(supplier),
            },
        ],
        [],
    );

    function deleteSupplier(): void {
        if (!supplierToDelete) {
            return;
        }

        router.delete(destroy.url(supplierToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setSupplierToDelete(null);
                void queryClient.invalidateQueries({ queryKey: ['suppliers'] });
            },
        });
    }

    return (
        <>
            <Head title="Fornecedores" />

            <ContentHeader>
                <Heading
                    title="Fornecedores"
                    description="Cadastre contrapartes de contas a pagar"
                />

                <Button asChild>
                    <Link href={create.url()}>
                        <Plus className="size-4" />
                        Novo fornecedor
                    </Link>
                </Button>
            </ContentHeader>
            <div className="page-wrapper-lg">
                <div className="page-section">
                    <SupplierDeleteDialog
                        supplier={supplierToDelete}
                        onCancel={() => setSupplierToDelete(null)}
                        onConfirm={deleteSupplier}
                    />

                    <DataTable<Supplier>
                        queryKey="suppliers"
                        endpoint={apiRoutes.suppliers.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(supplier) => supplier.uuid}
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
                        searchPlaceholder="Buscar fornecedores..."
                    />
                </div>
            </div>
        </>
    );
}
