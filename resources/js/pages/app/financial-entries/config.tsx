import type { ColumnDef } from '@tanstack/react-table';

import type {
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { apiRoutes } from '@/lib/api/api-routes';
import type { FinancialEntry } from '@/types/app/financial-entry';

function money(value: number, currency = 'BRL'): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

export const columns: ColumnDef<FinancialEntry>[] = [
    {
        id: 'description',
        accessorKey: 'description',
        header: 'Lançamento',
        meta: { label: 'Lançamento' },
        cell: ({ row }) => (
            <div className="min-w-0">
                <p className="truncate font-medium">
                    {row.original.description}
                </p>
                <p className="truncate text-xs text-muted-foreground">
                    {row.original.origin_label} -{' '}
                    {row.original.origin_display_name}
                </p>
            </div>
        ),
    },
    {
        id: 'direction',
        accessorKey: 'direction',
        header: 'Direção',
        meta: { label: 'Direção' },
        cell: ({ row }) => (
            <Badge
                variant={
                    row.original.direction === 'receivable'
                        ? 'default'
                        : 'secondary'
                }
            >
                {row.original.direction_label}
            </Badge>
        ),
    },
    {
        id: 'counterparty_label',
        accessorKey: 'counterparty_label',
        header: 'Contraparte',
        meta: { label: 'Contraparte' },
        cell: ({ row }) => row.original.counterparty_label ?? 'Sem contraparte',
    },
    {
        id: 'due_date',
        accessorKey: 'due_date',
        header: 'Vencimento',
        meta: { label: 'Vencimento' },
        cell: ({ row }) => (
            <span className="tabular-nums">{row.original.due_date ?? '-'}</span>
        ),
    },
    {
        id: 'status',
        accessorKey: 'status',
        header: 'Status',
        meta: { label: 'Status' },
        cell: ({ row }) => (
            <StatusBadge presenter={row.original.status_presenter} />
        ),
    },
    {
        id: 'net_amount',
        accessorKey: 'net_amount',
        header: 'Líquido',
        meta: { label: 'Líquido' },
        cell: ({ row }) => (
            <span className="tabular-nums">
                {money(row.original.net_amount, row.original.currency)}
            </span>
        ),
    },
    {
        id: 'balance_amount',
        accessorKey: 'balance_amount',
        header: 'Saldo',
        meta: { label: 'Saldo' },
        cell: ({ row }) => (
            <span className="tabular-nums">
                {money(row.original.balance_amount, row.original.currency)}
            </span>
        ),
    },
];

// Categoria e contraparte eram quick filters assíncronos; a DataTable atual não
// renderiza `async-select`, então ficam no painel de filtros como campo de id.
export const filters: DataTableFilterConfig[] = [
    {
        id: 'description',
        label: 'Descrição',
        type: 'text',
        operator: 'contains',
    },
    {
        id: 'direction',
        label: 'Direção',
        type: 'select',
        options: [
            { label: 'A receber', value: 'receivable' },
            { label: 'A pagar', value: 'payable' },
        ],
    },
    {
        id: 'status',
        label: 'Status',
        type: 'select',
        options: [
            { label: 'Em aberto', value: 'open' },
            { label: 'Vencido', value: 'overdue' },
            { label: 'Pago parcial', value: 'partially_paid' },
            { label: 'Pago', value: 'paid' },
            { label: 'Cancelado', value: 'canceled' },
            { label: 'Estornado', value: 'reversed' },
            { label: 'Renegociado', value: 'renegotiated' },
        ],
    },
    {
        id: 'category_id',
        label: 'Categoria',
        type: 'async-select',
        operator: 'eq',
        placeholder: 'Categoria',
    },
    {
        id: 'counterparty',
        label: 'Contraparte',
        type: 'async-select',
        operator: 'eq',
        placeholder: 'Contraparte',
    },
];

export const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'due_date', label: 'Vencimento' },
    { value: 'issue_date', label: 'Emissão' },
    { value: 'competence_date', label: 'Competência' },
    { value: 'created_at', label: 'Criado em' },
];

export const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Finance\\Models\\FinancialEntry',
    columnsEndpoint: apiRoutes.exports.columns,
};
