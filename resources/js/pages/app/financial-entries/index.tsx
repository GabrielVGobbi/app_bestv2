import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';
import { useMemo } from 'react';

import {
    create,
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import Header from '@/components/dashboard/header';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialEntry } from '@/types/app/financial-entry';

import { columns, dateFilterColumns, exportConfig, filters } from './config';

export default function FinancialEntriesIndex() {
    setLayoutProps({
        breadcrumbs: [{ title: 'Lançamentos financeiros', href: index.url() }],
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
            <Head title="Lançamentos financeiros" />

            <Header
                title="Lançamentos financeiros"
                subtitle="Títulos a receber e a pagar, parcelas e baixas"
            >
                <Button asChild>
                    <Link href={create.url()}>
                        <Plus className="size-4" aria-hidden="true" />
                        Novo lançamento
                    </Link>
                </Button>
            </Header>

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
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
                        searchPlaceholder="Buscar lançamentos"
                    />
                </div>
            </div>
        </>
    );
}
