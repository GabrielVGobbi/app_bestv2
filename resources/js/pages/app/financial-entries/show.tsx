import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ReceiptText,
    RotateCcw,
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
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn, formatDate, formatDateOnly } from '@/lib/utils';
import { ActivityTimeline } from '@/modules/timeline';
import { FileAttachments } from '@/modules/uploads';
import type {
    FinancialEntry,
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
    { label: 'Todas as datas', value: 'all' },
    { label: 'Vencidas', value: 'overdue' },
    { label: 'Hoje', value: 'today' },
    { label: 'Próximos 7 dias', value: 'next7' },
    { label: 'Próximos 30 dias', value: 'next30' },
    { label: 'Sem data', value: 'without_due_date' },
];

const EMPTY_INSTALLMENTS: FinancialInstallment[] = [];

function movementLabel(movement: FinancialMovement): string {
    return movement.direction === 'inflow' ? 'Entrada' : 'Saída';
}

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

function PageSection({
    title,
    actions,
    children,
}: {
    title: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-sm font-semibold tracking-tight">
                    {title}
                </h2>
                {actions}
            </div>
            {children}
        </section>
    );
}

function MetaItem({
    label,
    value,
    alert = false,
    children,
}: {
    label: string;
    value?: string | null;
    alert?: boolean;
    children?: ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-[13px] text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'mt-0.5 text-sm font-medium',
                    alert && 'text-destructive',
                )}
            >
                {children ?? (value || '-')}
            </dd>
        </div>
    );
}

function AmountRow({
    label,
    children,
    emphasis = false,
}: {
    label: string;
    children: ReactNode;
    emphasis?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex items-baseline justify-between gap-8',
                emphasis && 'border-t border-border/70 pt-2',
            )}
        >
            <span
                className={cn(
                    'text-sm',
                    emphasis
                        ? 'font-medium text-foreground'
                        : 'text-muted-foreground',
                )}
            >
                {label}
            </span>
            {children}
        </div>
    );
}

function FilterPills<T extends string>({
    options,
    value,
    onChange,
    ariaLabel,
}: {
    options: Array<{ label: string; value: T }>;
    value: T;
    onChange: (value: T) => void;
    ariaLabel: string;
}) {
    return (
        <div
            role="group"
            aria-label={ariaLabel}
            className="flex flex-wrap gap-1"
        >
            {options.map((option) => {
                const isActive = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={isActive}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'h-7 rounded-md px-2.5 text-xs font-medium transition-colors',
                            isActive
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:bg-secondary hover:text-foreground',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

function OriginValue({ entry }: { entry: FinancialEntry }) {
    if (entry.origin_url) {
        return (
            <Link
                href={entry.origin_url}
                className="hover:text-foreground hover:underline"
            >
                {entry.origin_display_name}
            </Link>
        );
    }

    return entry.origin_display_name;
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
                backHref={index.url()}
                className="border-border/60 bg-canvas/90"
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
                                Cancelar
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

            <div className="page-wrapper-lg space-y-8">
                <section className="flex flex-col gap-6 border-b border-border/70 pb-6 lg:flex-row lg:items-end lg:justify-between">
                    <div className="min-w-0 space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <DirectionBadge
                                direction={entry.direction}
                                label={entry.direction_label}
                            />
                            <StatusBadge
                                presenter={entry.status_presenter}
                            />
                        </div>

                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                            <MetaItem
                                label="Contraparte"
                                value={
                                    entry.counterparty_label ??
                                    'Sem contraparte'
                                }
                            />
                            <MetaItem label="Origem">
                                <OriginValue entry={entry} />
                            </MetaItem>
                            <MetaItem
                                label="Empresa"
                                value={entry.company_name}
                            />
                            <MetaItem
                                label="Vencimento"
                                alert={entry.status === 'overdue'}
                                value={formatDateOnly(entry.due_date)}
                            />
                            <MetaItem
                                label="Competência"
                                value={formatDateOnly(entry.competence_date)}
                            />
                            <MetaItem
                                label="Categoria"
                                value={entry.category?.full_path ?? null}
                            />
                        </dl>
                    </div>

                    <div className="w-full shrink-0 space-y-3 lg:w-64">
                        <div className="lg:text-right">
                            <p className="text-[13px] text-muted-foreground">
                                Saldo
                            </p>
                            <MoneyAmount
                                value={entry.balance_amount}
                                currency={entry.currency}
                                direction={entry.direction}
                                overdue={entry.status === 'overdue'}
                                signed
                                className="text-[1.75rem] leading-none font-semibold tracking-tight"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <AmountRow label="Bruto">
                                <MoneyAmount
                                    value={entry.gross_amount}
                                    currency={entry.currency}
                                    direction={entry.direction}
                                />
                            </AmountRow>
                            {entry.addition_amount > 0 ? (
                                <AmountRow label="Acréscimos">
                                    <MoneyAmount
                                        value={entry.addition_amount}
                                        currency={entry.currency}
                                    />
                                </AmountRow>
                            ) : null}
                            {entry.deduction_amount > 0 ? (
                                <AmountRow label="Deduções">
                                    <MoneyAmount
                                        value={entry.deduction_amount}
                                        currency={entry.currency}
                                    />
                                </AmountRow>
                            ) : null}
                            <AmountRow label="Líquido">
                                <MoneyAmount
                                    value={entry.net_amount}
                                    currency={entry.currency}
                                    direction={entry.direction}
                                    signed
                                />
                            </AmountRow>
                            <AmountRow label="Liquidado">
                                <MoneyAmount
                                    value={entry.settled_amount}
                                    currency={entry.currency}
                                />
                            </AmountRow>
                        </div>
                    </div>
                </section>

                {components.length > 0 ? (
                    <PageSection title="Composição">
                        <ul className="divide-y border-y">
                            {components.map((component) => (
                                <li
                                    key={component.uuid}
                                    className="flex items-center justify-between gap-4 py-2.5"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {component.label ??
                                                component.code_label}
                                        </p>
                                        <p className="text-[13px] text-muted-foreground">
                                            {component.operation_label}
                                        </p>
                                    </div>
                                    <MoneyAmount
                                        value={component.amount}
                                        currency={entry.currency}
                                        direction={
                                            component.operation === 'subtract'
                                                ? 'outflow'
                                                : 'inflow'
                                        }
                                        signed
                                        className="text-sm font-semibold"
                                    />
                                </li>
                            ))}
                        </ul>
                    </PageSection>
                ) : null}

                <PageSection
                    title="Parcelas"
                    actions={
                        <p className="text-[13px] text-muted-foreground">
                            {filteredInstallments.length} de{' '}
                            {installments.length}
                        </p>
                    }
                >
                    {installments.length > 0 ? (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <FilterPills
                                ariaLabel="Filtrar parcelas por status"
                                options={installmentStatusFilters}
                                value={installmentStatusFilter}
                                onChange={setInstallmentStatusFilter}
                            />
                            <div className="flex items-center gap-2">
                                <Select
                                    value={installmentDateFilter}
                                    onValueChange={(value) =>
                                        setInstallmentDateFilter(
                                            value as InstallmentDateFilter,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        size="sm"
                                        aria-label="Filtrar parcelas por data"
                                        className="min-w-40"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent align="end">
                                        {installmentDateFilters.map(
                                            (filter) => (
                                                <SelectItem
                                                    key={filter.value}
                                                    value={filter.value}
                                                >
                                                    {filter.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                {hasInstallmentFilters ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            setInstallmentStatusFilter('all');
                                            setInstallmentDateFilter('all');
                                        }}
                                    >
                                        Limpar
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    ) : null}

                    {installments.length === 0 ? (
                        <p className="py-6 text-sm text-muted-foreground">
                            Nenhuma parcela registrada.
                        </p>
                    ) : filteredInstallments.length === 0 ? (
                        <p className="py-6 text-sm text-muted-foreground">
                            Nenhuma parcela encontrada para os filtros
                            selecionados.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[860px] text-sm">
                                <thead className="border-b text-left text-[13px] text-muted-foreground">
                                    <tr>
                                        <th className="py-2 pr-3 font-medium">
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
                                        <th className="py-2 pl-3 text-right font-medium">
                                            <span className="sr-only">
                                                Ações
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {filteredInstallments.map(
                                        (installment) => (
                                            <tr
                                                key={installment.uuid}
                                                className={
                                                    installment.is_overdue
                                                        ? 'bg-destructive-soft/35'
                                                        : undefined
                                                }
                                            >
                                                <td className="py-2.5 pr-3 font-medium">
                                                    #
                                                    {
                                                        installment.installment_number
                                                    }
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    <StatusBadge
                                                        presenter={
                                                            installment.status_presenter
                                                        }
                                                    />
                                                </td>
                                                <td
                                                    className={cn(
                                                        'px-3 py-2.5',
                                                        installment.is_overdue &&
                                                            'font-medium text-destructive',
                                                    )}
                                                >
                                                    {formatDateOnly(
                                                        installment.due_date,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2.5 text-muted-foreground">
                                                    {formatDateOnly(
                                                        installment.competence_date,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2.5 text-right font-medium">
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
                                                <td className="px-3 py-2.5 text-right">
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
                                                <td className="px-3 py-2.5 text-right font-semibold">
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
                                                <td className="py-2.5 pl-3">
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
                </PageSection>

                <PageSection title="Baixas">
                    {settlements.length === 0 ? (
                        <p className="py-6 text-sm text-muted-foreground">
                            Nenhuma baixa registrada.
                        </p>
                    ) : (
                        <ul className="divide-y border-y">
                            {settlements.map((settlement) => {
                                const allocations =
                                    settlement.allocations ?? [];
                                const movements = settlement.movements ?? [];
                                const settlementComponents =
                                    settlement.components ?? [];

                                return (
                                    <li
                                        key={settlement.uuid}
                                        className="space-y-3 py-4"
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
                                                        {formatDate(
                                                            settlement.settled_at,
                                                        )}
                                                    </span>
                                                </div>
                                                <p className="text-[13px] text-muted-foreground">
                                                    {settlement.payment_method
                                                        ?.name ?? 'Sem método'}
                                                    {' · '}
                                                    {settlement
                                                        .financial_account
                                                        ?.display_name ??
                                                        'Conta não informada'}
                                                </p>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <div className="text-right">
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
                                                        className="text-sm font-semibold"
                                                    />
                                                    <p className="text-[13px] text-muted-foreground">
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

                                        {settlementComponents.length > 0 ? (
                                            <div className="space-y-1.5">
                                                {settlementComponents.map(
                                                    (component) => (
                                                        <div
                                                            key={
                                                                component.uuid
                                                            }
                                                            className="flex items-center justify-between gap-3 text-sm"
                                                        >
                                                            <span className="text-muted-foreground">
                                                                {component.label ??
                                                                    component.code_label}
                                                                {component.nature ===
                                                                'informational'
                                                                    ? ' (informativo)'
                                                                    : ''}
                                                            </span>
                                                            <span className="font-medium">
                                                                {component.nature ===
                                                                'deduction'
                                                                    ? '− '
                                                                    : component.nature ===
                                                                        'addition'
                                                                      ? '+ '
                                                                      : ''}
                                                                <MoneyAmount
                                                                    value={
                                                                        component.amount
                                                                    }
                                                                    currency={
                                                                        entry.currency
                                                                    }
                                                                />
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        ) : null}

                                        {allocations.length > 0 ? (
                                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-muted-foreground">
                                                {allocations.map(
                                                    (allocation) => (
                                                        <span
                                                            key={allocation.id}
                                                        >
                                                            Parcela #
                                                            {
                                                                allocation.installment_id
                                                            }{' '}
                                                            <MoneyAmount
                                                                value={
                                                                    allocation.amount
                                                                }
                                                                currency={
                                                                    entry.currency
                                                                }
                                                                className="text-muted-foreground"
                                                            />
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        ) : null}

                                        {movements.length > 0 ? (
                                            <div className="space-y-1.5">
                                                {movements.map((movement) => (
                                                    <div
                                                        key={movement.uuid}
                                                        className="flex items-center justify-between gap-3 text-sm"
                                                    >
                                                        <div className="flex items-center gap-2 text-muted-foreground">
                                                            <WalletCards className="size-4" />
                                                            <span>
                                                                {movementLabel(
                                                                    movement,
                                                                )}
                                                                {' · '}
                                                                {movement
                                                                    .financial_account
                                                                    ?.display_name ??
                                                                    'Conta não informada'}
                                                            </span>
                                                        </div>
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
                                                            className="font-medium"
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </PageSection>

                <div className="grid gap-6 lg:grid-cols-2">
                    <FileAttachments
                        modelType="financial-entry"
                        modelId={entry.uuid}
                        defaultTag="document"
                        title="Anexos"
                        description={null}
                        variant="toggle"
                        readOnly={!entry.permissions.can_manage_attachments}
                        listMaxHeight={280}
                    />

                    <ActivityTimeline
                        modelType="financial-entry"
                        modelId={entry.uuid}
                        title="Atividade"
                        allowAttachments
                        pageSize={20}
                    />
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
