import type { ColumnDef } from '@tanstack/react-table';

import type {
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialCategory } from '@/types/app/financial-category';

const typeLabels: Record<FinancialCategory['type'], string> = {
    income: 'Receita',
    expense: 'Despesa',
    transfer: 'Transferência',
};

export const columns: ColumnDef<FinancialCategory>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Categoria',
        meta: { label: 'Categoria' },
        cell: ({ row }) => (
            <div className="flex min-w-0 items-center gap-2">
                <span
                    className="size-2.5 shrink-0 rounded-full"
                    style={{ backgroundColor: row.original.color ?? '#64748b' }}
                />
                <div className="min-w-0">
                    <p className="truncate font-medium">{row.original.name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {row.original.full_path}
                    </p>
                </div>
            </div>
        ),
    },
    {
        id: 'code',
        accessorKey: 'code',
        header: 'Código',
        meta: { label: 'Código' },
    },
    {
        id: 'type',
        accessorKey: 'type',
        header: 'Tipo',
        meta: { label: 'Tipo' },
        cell: ({ row }) => typeLabels[row.original.type],
    },
    {
        id: 'is_active',
        accessorKey: 'is_active',
        header: 'Status',
        meta: { label: 'Status' },
        cell: ({ row }) => (
            <Badge variant={row.original.is_active ? 'default' : 'secondary'}>
                {row.original.is_active ? 'Ativa' : 'Inativa'}
            </Badge>
        ),
    },
];

export const filters: DataTableFilterConfig[] = [
    {
        id: 'name',
        label: 'Nome',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'code',
        label: 'Código',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'type',
        label: 'Tipo',
        type: 'select',
        options: [
            { label: 'Receita', value: 'income' },
            { label: 'Despesa', value: 'expense' },
            { label: 'Transferência', value: 'transfer' },
        ],
    },
    {
        id: 'is_active',
        label: 'Status',
        type: 'boolean',
        options: [
            { label: 'Ativa', value: true },
            { label: 'Inativa', value: false },
        ],
    },
];

export const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];

export const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Finance\\Models\\FinancialCategory',
    columnsEndpoint: apiRoutes.exports.columns,
};
