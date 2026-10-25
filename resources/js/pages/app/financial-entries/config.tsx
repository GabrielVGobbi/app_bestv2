import type { ColumnDef } from '@tanstack/react-table';

import type {
    DataTableDateColumn,
    DataTableExportConfig,
    DataTableFilterConfig,
} from '@/components/data-table';
import { DirectionBadge } from '@/components/direction-badge';
import { MoneyAmount } from '@/components/money-amount';
import { StatusBadge } from '@/components/status-badge';
import { apiRoutes } from '@/lib/api/api-routes';
import { cn, formatDateOnly } from '@/lib/utils';
import type { FinancialInstallment } from '@/types/app/financial-entry';

export const columns: ColumnDef<FinancialInstallment>[] = [
    {
        id: 'counterparty_label',
        accessorKey: 'counterparty_label',
        header: 'Contraparte',
        size: 190,
        minSize: 150,
        meta: { label: 'Contraparte', truncate: true },
        cell: ({ row }) => row.original.counterparty_label ?? 'Sem contraparte',
    },
    {
        id: 'description',
        accessorKey: 'description',
        header: 'Título / parcela',
        size: 320,
        minSize: 230,
        meta: { label: 'Título / parcela' },
        cell: ({ row }) => {
            const entry = row.original.entry;

            return (
                <div className="min-w-0">
                    <p className="truncate font-medium">
                        {entry?.description ??
                            row.original.description ??
                            'Lançamento'}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        Parcela {row.original.position_label}
                        {entry ? ` · ${entry.origin_label}` : ''}
                    </p>
                </div>
            );
        },
    },
    {
        id: 'direction',
        accessorKey: 'direction',
        header: 'Direção',
        size: 126,
        minSize: 116,
        meta: { label: 'Direção', align: 'center' },
        cell: ({ row }) =>
            row.original.direction ? (
                <div className="flex justify-center">
                    <DirectionBadge
                        direction={row.original.direction}
                        label={row.original.direction_label}
                    />
                </div>
            ) : (
                '-'
            ),
    },
    {
        id: 'due_date',
        accessorKey: 'due_date',
        header: 'Vencimento',
        size: 120,
        minSize: 112,
        meta: { label: 'Vencimento', align: 'center' },
        cell: ({ row }) => (
            <span
                className={cn(
                    'tabular-nums',
                    row.original.is_overdue && 'font-semibold text-destructive',
                )}
            >
                {formatDateOnly(row.original.due_date)}
            </span>
        ),
    },
    {
        id: 'competence_date',
        accessorKey: 'competence_date',
        header: 'Competência',
        size: 120,
        minSize: 112,
        meta: { label: 'Competência', align: 'center' },
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {formatDateOnly(row.original.competence_date)}
            </span>
        ),
    },
    {
        id: 'status',
        accessorKey: 'status',
        header: 'Status',
        size: 132,
        minSize: 120,
        meta: { label: 'Status', align: 'center' },
        cell: ({ row }) => (
            <div className="flex justify-center">
                <StatusBadge presenter={row.original.status_presenter} />
            </div>
        ),
    },
    {
        id: 'original_amount',
        accessorKey: 'original_amount',
        header: 'Valor',
        size: 136,
        minSize: 120,
        meta: { label: 'Valor da parcela', align: 'right', type: 'number' },
        cell: ({ row }) => (
            <MoneyAmount
                value={row.original.original_amount}
                currency={row.original.currency}
            />
        ),
    },
    {
        id: 'balance_amount',
        accessorKey: 'balance_amount',
        header: 'Saldo',
        size: 136,
        minSize: 120,
        meta: {
            label: 'Saldo',
            align: 'right',
            type: 'number',
            sticky: 'right',
        },
        cell: ({ row }) => (
            <MoneyAmount
                value={row.original.balance_amount}
                currency={row.original.currency}
                direction={row.original.direction ?? 'neutral'}
                overdue={row.original.is_overdue}
                signed
            />
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
];

export const dateFilterColumns: DataTableDateColumn[] = [
    { value: 'competence_date', label: 'Competência' },
    { value: 'created_at', label: 'Criado em' },
];

export const exportConfig: DataTableExportConfig = {
    modelClass: 'App\\Modules\\Finance\\Models\\FinancialInstallment',
    columnsEndpoint: apiRoutes.exports.columns,
};
