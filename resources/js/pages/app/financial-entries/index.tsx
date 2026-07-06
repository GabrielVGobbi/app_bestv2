import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';
import { useMemo } from 'react';

import {
    create,
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialEntry } from '@/types/app/financial-entry';

import {
    columns,
    dateFilterColumns,
    exportConfig,
    filters,
    quickFilters,
} from './config';

export default function FinancialEntriesIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Lançamentos Financeiros', href: index.url() }],
    });

    const actions = useMemo<DataTableAction<FinancialEntry>[]>(
        () => [
            {
                label: 'Visualizar',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                href: (entry) => show.url(entry.uuid),
                showLabel: false,
                tooltip: 'Visualizar',
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Lançamentos Financeiros" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Heading
                            title="Lançamentos Financeiros"
                        />

                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                Novo lancamento
                            </Link>
                        </Button>
                    </div>

                    <DataTable<FinancialEntry>
                        queryKey="financial-entries"
                        endpoint={apiRoutes.financialEntries.index}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(entry) => entry.uuid}
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
                        searchPlaceholder="Buscar lançamentos..."
                    />
                </div>
            </div>
        </>
    );
}
