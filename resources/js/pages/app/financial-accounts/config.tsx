import type { ColumnDef } from '@tanstack/react-table';

import type {
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialAccount } from '@/types/app/financial-account';

function money(value: number, currency = 'BRL'): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

export const columns: ColumnDef<FinancialAccount>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Conta',
        meta: { label: 'Conta' },
        cell: ({ row }) => (
            <div className="min-w-0">
                <p className="truncate font-medium">{row.original.name}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {row.original.institution_name ?? row.original.type_label}
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
        id: 'account_number',
        accessorKey: 'account_number',
        header: 'Agência / Conta',
        meta: { label: 'Agência / Conta' },
        cell: ({ row }) => {
            const agency = row.original.agency_number ?? 'Sem agência';
            const account = row.original.account_number
                ? `${row.original.account_number}${row.original.account_digit ? `-${row.original.account_digit}` : ''}`
                : 'Sem conta';

            return (
                <span className="text-sm text-muted-foreground">
                    {agency} / {account}
                </span>
            );
        },
    },
    {
        id: 'current_balance_amount',
        accessorKey: 'current_balance_amount',
        header: 'Saldo',
        meta: { label: 'Saldo' },
        cell: ({ row }) => (
            <span className="font-medium">
                {money(
                    row.original.current_balance_amount,
                    row.original.currency,
                )}
            </span>
        ),
    },
    {
        id: 'active',
        accessorKey: 'active',
        header: 'Status',
        meta: { label: 'Status' },
        cell: ({ row }) => (
            <Badge variant={row.original.active ? 'default' : 'secondary'}>
                {row.original.active ? 'Ativa' : 'Inativa'}
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
        id: 'institution_name',
        label: 'Banco',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'type',
        label: 'Tipo',
        type: 'select',
        options: [
            { label: 'Conta bancária', value: 'bank_account' },
            { label: 'Caixa', value: 'cash' },
            { label: 'Carteira', value: 'wallet' },
            { label: 'Gateway de pagamento', value: 'payment_gateway' },
        ],
    },
    {
        id: 'active',
        label: 'Status',
        type: 'boolean',
        options: [
            { label: 'Ativa', value: true },
            { label: 'Inativa', value: false },
        ],
    },
];

export const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'created_at', label: 'Criada em' },
    { value: 'updated_at', label: 'Atualizada em' },
];

export const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Finance\\Models\\FinancialAccount',
    columnsEndpoint: apiRoutes.exports.columns,
};
