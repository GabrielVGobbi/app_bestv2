import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    create,
    destroy,
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/PaymentMethodController';
import ContentHeader from '@/components/content-header';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { apiRoutes } from '@/lib/api/api-routes';
import type { PaymentMethod } from '@/types/app/payment-method';

import { columns, dateFilterColumns, exportConfig, filters } from './config';
import { PaymentMethodDeleteDialog } from './partials/payment-method-delete-dialog';

export default function PaymentMethodsIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Metodos de pagamento', href: index.url() }],
    });

    const queryClient = useQueryClient();
    const [paymentMethodToDelete, setPaymentMethodToDelete] =
        useState<PaymentMethod | null>(null);

    const actions = useMemo<DataTableAction<PaymentMethod>[]>(
        () => [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (paymentMethod) => show.url(paymentMethod.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
            {
                label: 'Excluir',
                icon: <Trash2 className="size-4" />,
                variant: 'destructive',
                showLabel: false,
                tooltip: 'Excluir',
                onClick: (paymentMethod) =>
                    setPaymentMethodToDelete(paymentMethod),
            },
        ],
        [],
    );

    function deletePaymentMethod(): void {
        if (!paymentMethodToDelete) {
            return;
        }

        router.delete(destroy.url(paymentMethodToDelete.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setPaymentMethodToDelete(null);
                void queryClient.invalidateQueries({
                    queryKey: ['payment-methods'],
                });
            },
        });
    }

    return (
        <>
            <Head title="Metodos de pagamento" />

            <ContentHeader>
                <Heading
                    title="Metodos de pagamento"
                    description="Configure os meios aceitos em baixas futuras"
                />

                <Button asChild>
                    <Link href={create.url()}>
                        <Plus className="size-4" />
                        Novo metodo
                    </Link>
                </Button>
            </ContentHeader>
            <div className="page-wrapper-lg">
                <div className="page-section">
                    <PaymentMethodDeleteDialog
                        paymentMethod={paymentMethodToDelete}
                        onCancel={() => setPaymentMethodToDelete(null)}
                        onConfirm={deletePaymentMethod}
                    />

                    <DataTable<PaymentMethod>
                        queryKey="payment-methods"
                        endpoint={apiRoutes.paymentMethods.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(paymentMethod) => paymentMethod.uuid}
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
                        searchPlaceholder="Buscar metodos..."
                    />
                </div>
            </div>
        </>
    );
}
