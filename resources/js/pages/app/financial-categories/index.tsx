import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialCategoryController';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialCategory } from '@/types/app/financial-category';

import { columns, dateFilterColumns, exportConfig, filters } from './config';
import { FinancialCategoryDeleteDialog } from './partials/financial-category-delete-dialog';

export default function FinancialCategoriesIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Categorias financeiras', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [categoryToDelete, setCategoryToDelete] =
        useState<FinancialCategory | null>(null);

    const actions = useMemo<DataTableAction<FinancialCategory>[]>(
        () => [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (category) => show.url(category.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (category) => setCategoryToDelete(category),
            },
        ],
        [],
    );

    function deleteCategory(): void {
        if (!categoryToDelete) {
            return;
        }

        router.delete(destroy.url(categoryToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setCategoryToDelete(null);
                void queryClient.invalidateQueries({
                    queryKey: ['financial-categories'],
                });
            },
        });
    }

    return (
        <>
            <Head title="Categorias financeiras" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Categorias financeiras"
                            description="Organize receitas, despesas e transferências em árvore"
                        />

                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                Nova categoria
                            </Link>
                        </Button>
                    </div>

                    <FinancialCategoryDeleteDialog
                        category={categoryToDelete}
                        onCancel={() => setCategoryToDelete(null)}
                        onConfirm={deleteCategory}
                    />

                    <DataTable<FinancialCategory>
                        queryKey="financial-categories"
                        endpoint={apiRoutes.financialCategories.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(category) => category.uuid}
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
                        searchPlaceholder="Buscar categorias..."
                    />
                </div>
            </div>
        </>
    );
}
