import type { ColumnDef } from '@tanstack/react-table';

import type {
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { apiRoutes } from '@/lib/api/api-routes';
import type { PaymentMethod } from '@/types/app/payment-method';

export const columns: ColumnDef<PaymentMethod>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Método',
        meta: { label: 'Método' },
        cell: ({ row }) => (
            <div className="min-w-0">
                <p className="truncate font-medium">{row.original.name}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {row.original.code}
                </p>
            </div>
        ),
    },
    {
        id: 'type',
        accessorKey: 'type',
        header: 'Tipo',
        meta: { label: 'Tipo' },
        cell: ({ row }) => row.original.type_label,
    },
    {
        id: 'requires_reference',
        accessorKey: 'requires_reference',
        header: 'Referência',
        meta: { label: 'Referência' },
        cell: ({ row }) => (
            <Badge
                variant={
                    row.original.requires_reference ? 'default' : 'secondary'
                }
            >
                {row.original.requires_reference ? 'Obrigatória' : 'Opcional'}
            </Badge>
        ),
    },
    {
        id: 'is_active',
        accessorKey: 'is_active',
        header: 'Status',
        meta: { label: 'Status' },
        cell: ({ row }) => (
            <Badge variant={row.original.is_active ? 'default' : 'secondary'}>
                {row.original.is_active ? 'Ativo' : 'Inativo'}
            </Badge>
        ),
    },
    {
        id: 'sort_order',
        accessorKey: 'sort_order',
        header: 'Ordem',
        meta: { label: 'Ordem' },
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
            { label: 'Dinheiro', value: 'cash' },
            { label: 'Pix', value: 'pix' },
            { label: 'Transferência bancária', value: 'bank_transfer' },
            { label: 'Boleto', value: 'boleto' },
            { label: 'Cartão de crédito', value: 'credit_card' },
            { label: 'Cartão de débito', value: 'debit_card' },
            { label: 'Outro', value: 'other' },
        ],
    },
    {
        id: 'requires_reference',
        label: 'Referência',
        type: 'boolean',
        options: [
            { label: 'Obrigatória', value: true },
            { label: 'Opcional', value: false },
        ],
    },
    {
        id: 'is_active',
        label: 'Status',
        type: 'boolean',
        options: [
            { label: 'Ativo', value: true },
            { label: 'Inativo', value: false },
        ],
    },
];

export const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criado em' },
    { value: 'updated_at', label: 'Atualizado em' },
];

export const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Finance\\Models\\PaymentMethod',
    columnsEndpoint: apiRoutes.exports.columns,
};
