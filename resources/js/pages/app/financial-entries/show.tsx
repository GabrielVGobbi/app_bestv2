import { Head, setLayoutProps } from '@inertiajs/react';
import {
    CalendarDays,
    CircleDollarSign,
    Filter,
    ReceiptText,
    RotateCcw,
    Table2,
    WalletCards,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import {
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import { ContentHeader } from '@/components/content-header';
import { DirectionBadge } from '@/components/direction-badge';
import { MoneyAmount } from '@/components/money-amount';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ActivityTimeline } from '@/modules/timeline';
import { FileAttachments } from '@/modules/uploads';
import type {
    FinancialEntryResource,
    FinancialInstallment,
    FinancialMovement,
    FinancialSettlement,
} from '@/types/app/financial-entry';

import { EntryCancelDialog } from './components/entry-cancel-dialog';
import { SettlementDialog } from './components/settlement-dialog';
import { SettlementReversalDialog } from './components/settlement-reversal-dialog';

interface Props {
    entryData: FinancialEntryResource;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function movementLabel(movement: FinancialMovement): string {
    return movement.direction === 'inflow' ? 'Entrada' : 'Saída';
}

function Info({
    label,
    value,
    children,
}: {
    label: string;
    value?: string | null;
    children?: ReactNode;
}) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            {children ?? <p className="text-sm">{value || '-'}</p>}
        </div>
    );
}

type InstallmentStatusFilter =
    | 'all'
    | 'settleable'
    | 'open'
    | 'overdue'
    | 'partially_paid'
    | 'paid';

type InstallmentDateFilter =
    | 'all'
    | 'overdue'
    | 'today'
    | 'next7'
    | 'next30'
    | 'without_due_date';

const installmentStatusFilters: Array<{
    label: string;
    value: InstallmentStatusFilter;
}> = [
    { label: 'Todas', value: 'all' },
    { label: 'A baixar', value: 'settleable' },
    { label: 'Em aberto', value: 'open' },
    { label: 'Vencidas', value: 'overdue' },
    { label: 'Parciais', value: 'partially_paid' },
    { label: 'Pagas', value: 'paid' },
];

const installmentDateFilters: Array<{
    label: string;
    value: InstallmentDateFilter;
}> = [
    { label: 'Todas datas', value: 'all' },
    { label: 'Vencidas', value: 'overdue' },
    { label: 'Hoje', value: 'today' },
    { label: '7 dias', value: 'next7' },
    { label: '30 dias', value: 'next30' },
    { label: 'Sem data', value: 'without_due_date' },
];

const EMPTY_INSTALLMENTS: FinancialInstallment[] = [];

function parseLocalDate(value: string | null): Date | null {
    if (!value) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Date(year, month - 1, day);
}

function todayStart(): Date {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function addDays(date: Date, days: number): Date {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);

    return copy;
}

function isSameDay(left: Date, right: Date): boolean {
    return (
        left.getFullYear() === right.getFullYear() &&
        left.getMonth() === right.getMonth() &&
        left.getDate() === right.getDate()
    );
}

function matchesInstallmentStatusFilter(
    installment: FinancialInstallment,
    filter: InstallmentStatusFilter,
): boolean {
    if (filter === 'all') {
        return true;
    }

    if (filter === 'settleable') {
        return installment.permissions.can_settle;
    }

    return installment.status === filter;
}

function matchesInstallmentDateFilter(
    installment: FinancialInstallment,
    filter: InstallmentDateFilter,
): boolean {
    if (filter === 'all') {
        return true;
    }

    const dueDate = parseLocalDate(installment.due_date);

    if (filter === 'without_due_date') {
        return dueDate === null;
    }

    if (!dueDate) {
        return false;
    }

    const today = todayStart();

    if (filter === 'overdue') {
        return (
            installment.is_overdue ||
            (dueDate < today && installment.balance_amount > 0)
        );
    }

    if (filter === 'today') {
        return isSameDay(dueDate, today);
    }

    const limit = filter === 'next7' ? addDays(today, 7) : addDays(today, 30);

    return dueDate >= today && dueDate <= limit;
}

export default function ShowFinancialEntry({ entryData }: Props) {
    const entry = entryData.data;
    const components = entry.amount_components ?? [];
    const installments = entry.installments ?? EMPTY_INSTALLMENTS;
    const settlements = entry.settlements ?? [];
    const firstOpenInstallment = installments.find(
        (installment) => installment.permissions.can_settle,
    );
    const [settlementInstallment, setSettlementInstallment] =
        useState<FinancialInstallment | null>(null);
    const [reversalSettlement, setReversalSettlement] =
        useState<FinancialSettlement | null>(null);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [installmentStatusFilter, setInstallmentStatusFilter] =
        useState<InstallmentStatusFilter>('all');
    const [installmentDateFilter, setInstallmentDateFilter] =
        useState<InstallmentDateFilter>('all');

    const filteredInstallments = useMemo(
        () =>
            installments.filter(
                (installment) =>
                    matchesInstallmentStatusFilter(
                        installment,
                        installmentStatusFilter,
                    ) &&
                    matchesInstallmentDateFilter(
                        installment,
                        installmentDateFilter,
                    ),
            ),
        [installments, installmentDateFilter, installmentStatusFilter],
    );

    const hasInstallmentFilters =
        installmentStatusFilter !== 'all' || installmentDateFilter !== 'all';

    setLayoutProps({
        breadcrumbs: [
            { title: 'Lançamentos Financeiros', href: index.url() },
            { title: entry.description, href: show.url(entry.uuid) },
        ],
    });

    return (
        <>
            <Head title={entry.description} />

            <ContentHeader
                title={entry.description}
                description={`${entry.direction_label} - ${entry.origin_label}`}
                backHref={index.url()}
                actions={
                    <>
                        {entry.permissions.can_cancel ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setCancelDialogOpen(true)}
                            >
                                <X className="size-4" />
                                Cancelar lançamento
                            </Button>
                        ) : null}
                        {entry.permissions.can_settle &&
                        firstOpenInstallment ? (
                            <Button
                                type="button"
                                onClick={() =>
                                    setSettlementInstallment(
                                        firstOpenInstallment,
                                    )
                                }
                            >
                                <ReceiptText className="size-4" />
                                Registrar baixa
                            </Button>
                        ) : null}
                    </>
                }
            />

            <div className="page-wrapper-lg">
                <div className="grid gap-4 p-4 lg:grid-cols-[1fr_22rem]">
                    <div className="space-y-4">
                        <Card>
                            <CardHeader className="flex-row items-center justify-between gap-3">
                                <CardTitle>Resumo</CardTitle>
                                <StatusBadge
                                    presenter={entry.status_presenter}
                                />
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-3">
                                <Info label="Direção">
                                    <DirectionBadge
                                        direction={entry.direction}
                                        label={entry.direction_label}
                                    />
                                </Info>
                                <Info
                                    label="Contraparte"
                                    value={
                                        entry.counterparty_label ??
                                        'Sem contraparte'
                                    }
                                />
                                <Info
                                    label="Origem"
                                    value={entry.origin_display_name}
                                />
                                <Info label="Vencimento">
                                    <p
                                        className={
                                            entry.status === 'overdue'
                                                ? 'text-sm font-medium text-destructive'
                                                : 'text-sm'
                                        }
                                    >
                                        {entry.due_date || '-'}
                                    </p>
                                </Info>
                                <Info
                                    label="Categoria"
                                    value={entry.category?.full_path ?? null}
                                />
                                <Info
                                    label="Empresa"
                                    value={`#${entry.company_id}`}
                                />
                                <Info label="Líquido">
                                    <MoneyAmount
                                        value={entry.net_amount}
                                        currency={entry.currency}
                                        direction={entry.direction}
                                        overdue={entry.status === 'overdue'}
                                        signed
                                        className="text-sm font-semibold"
                                    />
                                </Info>
                                <Info label="Saldo">
                                    <MoneyAmount
                                        value={entry.balance_amount}
                                        currency={entry.currency}
                                        direction={entry.direction}
                                        overdue={entry.status === 'overdue'}
                                        signed
                                        className="text-sm font-semibold"
                                    />
                                </Info>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Componentes de valor</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {components.length === 0 ? (
                                    <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        Nenhum componente registrado.
                                    </div>
                                ) : (
                                    components.map((component) => (
                                        <div
                                            key={component.uuid}
                                            className="flex items-center justify-between rounded-md border p-3"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {component.label ??
                                                        component.code_label}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {component.operation_label}
                                                </p>
                                            </div>
                                            <span className="text-sm font-semibold">
                                                <MoneyAmount
                                                    value={component.amount}
                                                    currency={entry.currency}
                                                    direction={
                                                        component.operation ===
                                                        'subtract'
                                                            ? 'outflow'
                                                            : 'inflow'
                                                    }
                                                    signed
                                                />
                                            </span>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-4">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-2">
                                        <Table2 className="size-4 text-muted-foreground" />
                                        <CardTitle>Parcelas</CardTitle>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {filteredInstallments.length} de{' '}
                                            {installments.length}
                                        </Badge>
                                        {hasInstallmentFilters ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => {
                                                    setInstallmentStatusFilter(
                                                        'all',
                                                    );
                                                    setInstallmentDateFilter(
                                                        'all',
                                                    );
                                                }}
                                            >
                                                <X className="size-4" />
                                                Limpar
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>

                                {installments.length > 0 ? (
                                    <div className="space-y-3 rounded-md border bg-muted/20 p-3">
                                        <div className="flex flex-col gap-2">
                                            <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                <Filter className="size-3.5" />
                                                Status
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {installmentStatusFilters.map(
                                                    (filter) => (
                                                        <Button
                                                            key={filter.value}
                                                            type="button"
                                                            size="sm"
                                                            variant={
                                                                installmentStatusFilter ===
                                                                filter.value
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                            aria-pressed={
                                                                installmentStatusFilter ===
                                                                filter.value
                                                            }
                                                            onClick={() =>
                                                                setInstallmentStatusFilter(
                                                                    filter.value,
                                                                )
                                                            }
                                                        >
                                                            {filter.label}
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-2">
                                            <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                <CalendarDays className="size-3.5" />
                                                Datas rápidas
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {installmentDateFilters.map(
                                                    (filter) => (
                                                        <Button
                                                            key={filter.value}
                                                            type="button"
                                                            size="sm"
                                                            variant={
                                                                installmentDateFilter ===
                                                                filter.value
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                            aria-pressed={
                                                                installmentDateFilter ===
                                                                filter.value
                                                            }
                                                            onClick={() =>
                                                                setInstallmentDateFilter(
                                                                    filter.value,
                                                                )
                                                            }
                                                        >
                                                            {filter.label}
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ) : null}
                            </CardHeader>
                            <CardContent>
                                {installments.length === 0 ? (
                                    <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        Nenhuma parcela registrada.
                                    </div>
                                ) : filteredInstallments.length === 0 ? (
                                    <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        Nenhuma parcela encontrada para os
                                        filtros selecionados.
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto rounded-md border">
                                        <table className="w-full min-w-[860px] text-sm">
                                            <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                                <tr>
                                                    <th className="px-3 py-2 font-medium">
                                                        Parcela
                                                    </th>
                                                    <th className="px-3 py-2 font-medium">
                                                        Status
                                                    </th>
                                                    <th className="px-3 py-2 font-medium">
                                                        Vencimento
                                                    </th>
                                                    <th className="px-3 py-2 font-medium">
                                                        Competência
                                                    </th>
                                                    <th className="px-3 py-2 text-right font-medium">
                                                        Original
                                                    </th>
                                                    <th className="px-3 py-2 text-right font-medium">
                                                        Liquidado
                                                    </th>
                                                    <th className="px-3 py-2 text-right font-medium">
                                                        Saldo
                                                    </th>
                                                    <th className="px-3 py-2 text-right font-medium">
                                                        Ações
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {filteredInstallments.map(
                                                    (installment) => (
                                                        <tr
                                                            key={
                                                                installment.uuid
                                                            }
                                                            className={
                                                                installment.is_overdue
                                                                    ? 'bg-destructive-soft/40 odd:bg-destructive-soft/55'
                                                                    : 'bg-card odd:bg-muted/20'
                                                            }
                                                        >
                                                            <td className="px-3 py-3 font-medium">
                                                                #
                                                                {
                                                                    installment.installment_number
                                                                }
                                                            </td>
                                                            <td className="px-3 py-3">
                                                                <StatusBadge
                                                                    presenter={
                                                                        installment.status_presenter
                                                                    }
                                                                />
                                                            </td>
                                                            <td
                                                                className={
                                                                    installment.is_overdue
                                                                        ? 'px-3 py-3 font-medium text-destructive'
                                                                        : 'px-3 py-3'
                                                                }
                                                            >
                                                                {installment.due_date ??
                                                                    '-'}
                                                            </td>
                                                            <td className="px-3 py-3">
                                                                {installment.competence_date ??
                                                                    '-'}
                                                            </td>
                                                            <td className="px-3 py-3 text-right font-medium">
                                                                <MoneyAmount
                                                                    value={
                                                                        installment.original_amount
                                                                    }
                                                                    currency={
                                                                        installment.currency
                                                                    }
                                                                    direction={
                                                                        entry.direction
                                                                    }
                                                                />
                                                            </td>
                                                            <td className="px-3 py-3 text-right text-muted-foreground">
                                                                <MoneyAmount
                                                                    value={
                                                                        installment.settled_amount
                                                                    }
                                                                    currency={
                                                                        installment.currency
                                                                    }
                                                                    className="text-muted-foreground"
                                                                />
                                                            </td>
                                                            <td className="px-3 py-3 text-right font-semibold">
                                                                <MoneyAmount
                                                                    value={
                                                                        installment.balance_amount
                                                                    }
                                                                    currency={
                                                                        installment.currency
                                                                    }
                                                                    direction={
                                                                        entry.direction
                                                                    }
                                                                    overdue={
                                                                        installment.is_overdue
                                                                    }
                                                                    signed
                                                                />
                                                            </td>
                                                            <td className="px-3 py-3">
                                                                <div className="flex justify-end">
                                                                    {installment
                                                                        .permissions
                                                                        .can_settle ? (
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant="outline"
                                                                            onClick={() =>
                                                                                setSettlementInstallment(
                                                                                    installment,
                                                                                )
                                                                            }
                                                                        >
                                                                            <ReceiptText className="size-4" />
                                                                            Baixar
                                                                        </Button>
                                                                    ) : (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            -
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Baixas</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {settlements.length === 0 ? (
                                    <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        Nenhuma baixa registrada.
                                    </div>
                                ) : (
                                    settlements.map((settlement) => {
                                        const allocations =
                                            settlement.allocations ?? [];
                                        const movements =
                                            settlement.movements ?? [];

                                        return (
                                            <div
                                                key={settlement.uuid}
                                                className="space-y-3 rounded-md border p-3"
                                            >
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                    <div className="space-y-1">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <StatusBadge
                                                                presenter={
                                                                    settlement.status_presenter
                                                                }
                                                            />
                                                            <span className="text-sm font-medium">
                                                                {formatDateTime(
                                                                    settlement.settled_at,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {settlement
                                                                .payment_method
                                                                ?.name ??
                                                                'Sem método'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {settlement
                                                                .financial_account
                                                                ?.display_name ??
                                                                'Conta não informada'}
                                                        </p>
                                                    </div>

                                                    <div className="flex items-center gap-2">
                                                        <div className="text-right">
                                                            <p className="text-sm font-semibold">
                                                                <MoneyAmount
                                                                    value={
                                                                        settlement.net_amount
                                                                    }
                                                                    currency={
                                                                        entry.currency
                                                                    }
                                                                    direction={
                                                                        settlement.direction
                                                                    }
                                                                    signed
                                                                />
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                Bruto{' '}
                                                                <MoneyAmount
                                                                    value={
                                                                        settlement.gross_amount
                                                                    }
                                                                    currency={
                                                                        entry.currency
                                                                    }
                                                                    className="text-muted-foreground"
                                                                />
                                                            </p>
                                                        </div>
                                                        {settlement.permissions
                                                            .can_reverse ? (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setReversalSettlement(
                                                                        settlement,
                                                                    )
                                                                }
                                                            >
                                                                <RotateCcw className="size-4" />
                                                                Estornar
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                </div>

                                                {allocations.length > 0 ? (
                                                    <div className="grid gap-2 sm:grid-cols-2">
                                                        {allocations.map(
                                                            (allocation) => (
                                                                <div
                                                                    key={
                                                                        allocation.id
                                                                    }
                                                                    className="rounded-md bg-muted/40 px-3 py-2 text-sm"
                                                                >
                                                                    <p className="font-medium">
                                                                        Parcela
                                                                        #
                                                                        {
                                                                            allocation.installment_id
                                                                        }
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Valor{' '}
                                                                        <MoneyAmount
                                                                            value={
                                                                                allocation.amount
                                                                            }
                                                                            currency={
                                                                                entry.currency
                                                                            }
                                                                            className="text-muted-foreground"
                                                                        />
                                                                    </p>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : null}

                                                {movements.length > 0 ? (
                                                    <div className="space-y-2 border-t pt-3">
                                                        {movements.map(
                                                            (movement) => (
                                                                <div
                                                                    key={
                                                                        movement.uuid
                                                                    }
                                                                    className="flex items-center justify-between gap-3 text-sm"
                                                                >
                                                                    <div className="flex items-center gap-2 text-muted-foreground">
                                                                        <WalletCards className="size-4" />
                                                                        <span className="flex flex-col">
                                                                            <span>
                                                                                {movementLabel(
                                                                                    movement,
                                                                                )}
                                                                            </span>
                                                                            <span className="text-xs">
                                                                                {movement
                                                                                    .financial_account
                                                                                    ?.display_name ??
                                                                                    'Conta não informada'}
                                                                            </span>
                                                                        </span>
                                                                    </div>
                                                                    <span className="font-medium">
                                                                        <MoneyAmount
                                                                            value={
                                                                                movement.amount
                                                                            }
                                                                            currency={
                                                                                entry.currency
                                                                            }
                                                                            direction={
                                                                                movement.direction
                                                                            }
                                                                            signed
                                                                        />
                                                                    </span>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : null}
                                            </div>
                                        );
                                    })
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Valores</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted-foreground">
                                        Bruto
                                    </span>
                                    <span className="font-medium">
                                        <MoneyAmount
                                            value={entry.gross_amount}
                                            currency={entry.currency}
                                            direction={entry.direction}
                                        />
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted-foreground">
                                        Líquido
                                    </span>
                                    <span className="font-medium">
                                        <MoneyAmount
                                            value={entry.net_amount}
                                            currency={entry.currency}
                                            direction={entry.direction}
                                            signed
                                        />
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted-foreground">
                                        Liquidado
                                    </span>
                                    <span className="font-medium">
                                        <MoneyAmount
                                            value={entry.settled_amount}
                                            currency={entry.currency}
                                        />
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 rounded-md bg-muted px-3 py-2">
                                    <span className="text-sm font-medium">
                                        Saldo
                                    </span>
                                    <span className="font-semibold">
                                        <MoneyAmount
                                            value={entry.balance_amount}
                                            currency={entry.currency}
                                            direction={entry.direction}
                                            overdue={entry.status === 'overdue'}
                                            signed
                                        />
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Contexto</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-start gap-3">
                                    <CalendarDays className="mt-0.5 size-4 text-muted-foreground" />
                                    <span>
                                        Competência:{' '}
                                        {entry.competence_date ?? '-'}
                                    </span>
                                </div>
                                <div className="flex items-start gap-3">
                                    <CircleDollarSign className="mt-0.5 size-4 text-muted-foreground" />
                                    <Badge variant="outline">
                                        {entry.currency}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <FileAttachments
                            modelType="financial-entry"
                            modelId={entry.uuid}
                            defaultTag="document"
                            title="Anexos"
                            description="Documentos, contratos e comprovantes do lançamento."
                            readOnly={!entry.permissions.can_manage_attachments}
                            listMaxHeight={320}
                        />

                        <ActivityTimeline
                            modelType="financial-entry"
                            modelId={entry.uuid}
                            title="Timeline"
                            allowAttachments
                            pageSize={20}
                        />
                    </aside>
                </div>
            </div>

            <SettlementDialog
                companyId={entry.company_id}
                currency={entry.currency}
                installment={settlementInstallment}
                open={settlementInstallment !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSettlementInstallment(null);
                    }
                }}
            />
            <SettlementReversalDialog
                settlement={reversalSettlement}
                onOpenChange={(open) => {
                    if (!open) {
                        setReversalSettlement(null);
                    }
                }}
            />
            <EntryCancelDialog
                entry={entry}
                open={cancelDialogOpen}
                onOpenChange={setCancelDialogOpen}
            />
        </>
    );
}
