import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialAccountController';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialAccount } from '@/types/app/financial-account';

import { columns, dateFilterColumns, exportConfig, filters } from './config';
import { FinancialAccountDeleteDialog } from './partials/financial-account-delete-dialog';

export default function FinancialAccountsIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Contas financeiras', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [financialAccountToDelete, setFinancialAccountToDelete] =
        useState<FinancialAccount | null>(null);

    const actions = useMemo<DataTableAction<FinancialAccount>[]>(
        () => [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (financialAccount) => show.url(financialAccount.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (financialAccount) =>
                    setFinancialAccountToDelete(financialAccount),
            },
        ],
        [],
    );

    function deleteFinancialAccount(): void {
        if (!financialAccountToDelete) {
            return;
        }

        router.delete(destroy.url(financialAccountToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setFinancialAccountToDelete(null);
                void queryClient.invalidateQueries({
                    queryKey: ['financial-accounts'],
                });
            },
        });
    }

    return (
        <>
            <Head title="Contas financeiras" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Contas financeiras"
                            description="Cadastre bancos, caixas, carteiras e gateways usados em baixas e conciliação"
                        />

                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                Nova conta
                            </Link>
                        </Button>
                    </div>

                    <FinancialAccountDeleteDialog
                        financialAccount={financialAccountToDelete}
                        onCancel={() => setFinancialAccountToDelete(null)}
                        onConfirm={deleteFinancialAccount}
                    />

                    <DataTable<FinancialAccount>
                        queryKey="financial-accounts"
                        endpoint={apiRoutes.financialAccounts.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(financialAccount) => financialAccount.uuid}
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
                        searchPlaceholder="Buscar contas..."
                    />
                </div>
            </div>
        </>
    );
}
