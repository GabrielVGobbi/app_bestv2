import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    Eye,
    Plus,
    ReceiptText,
    TrendingDown,
    TrendingUp,
    WalletCards,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ComponentType, ReactNode } from 'react';

import {
    create,
    index,
    show,
} from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import ContentHeader from '@/components/content-header';
import { DataTable } from '@/components/data-table';
import type { DataTableAction } from '@/components/data-table';
import Heading from '@/components/heading';
import { MoneyAmount } from '@/components/money-amount';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { apiRoutes } from '@/lib/api/api-routes';
import { cn } from '@/lib/utils';
import type {
    FinancialAgendaDirection,
    FinancialDirection,
    FinancialInstallment,
} from '@/types/app/financial-entry';

import { SettlementDialog } from './components/settlement-dialog';
import {
    columns,
    dateFilterColumns,
    exportConfig,
    filters,
    quickFilters,
} from './config';

interface AgendaPeriod {
    month: string;
    starts_at: string;
    ends_at: string;
}

interface AgendaSummary {
    receivable: { amount: number; count: number };
    payable: { amount: number; count: number };
    projected_balance: number;
    overdue_count: number;
}

interface Props {
    direction: FinancialAgendaDirection;
    period: AgendaPeriod;
    summary: AgendaSummary;
}

const pendingStatusFilters = ['open', 'overdue', 'partially_paid'];
const initialAgendaFilters = { status: pendingStatusFilters };

const directionTabs: Array<{
    label: string;
    value: FinancialAgendaDirection;
}> = [
    { label: 'Todas', value: 'all' },
    { label: 'A receber', value: 'receivable' },
    { label: 'A pagar', value: 'payable' },
];

function shiftMonth(month: string, offset: number): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + offset, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function currentMonth(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function monthLabel(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const label = new Intl.DateTimeFormat('pt-BR', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, monthNumber - 1, 1));

    return label.charAt(0).toUpperCase() + label.slice(1);
}

function agendaUrl(direction: FinancialAgendaDirection, month: string): string {
    return index.url({ query: { direction, month } });
}

function SummaryCard({
    label,
    helper,
    icon: Icon,
    iconClassName,
    children,
}: {
    label: string;
    helper: string;
    icon: ComponentType<{ className?: string }>;
    iconClassName?: string;
    children: ReactNode;
}) {
    return (
        <Card className="gap-0 overflow-hidden rounded-none border-0 py-0">
            <CardContent className="flex items-start justify-between gap-3 px-4 py-0 sm:px-5">
                <div className="min-w-0">
                    <p className="text-xs font-medium text-muted-foreground">
                        {label}
                    </p>
                    <div className="mt-2 text-lg font-medium tracking-tight sm:text-xl">
                        {children}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {helper}
                    </p>
                </div>
                <span
                    className={cn(
                        'hidden size-8 shrink-0 items-center justify-center text-muted-foreground sm:flex',
                        iconClassName,
                    )}
                >
                    <Icon className="size-4" />
                </span>
            </CardContent>
        </Card>
    );
}

export default function FinancialEntriesIndex({
    direction,
    period,
    summary,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Agenda financeira',
                href: agendaUrl(direction, period.month),
            },
        ],
    });

    const [settlementInstallment, setSettlementInstallment] =
        useState<FinancialInstallment | null>(null);

    const actions = useMemo<DataTableAction<FinancialInstallment>[]>(
        () => [
            {
                label: 'Baixar',
                icon: <ReceiptText className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Registrar baixa',
                disabled: (installment) => !installment.permissions.can_settle,
                onClick: setSettlementInstallment,
            },
            {
                label: 'Ver lançamento',
                icon: <Eye className="size-4" />,
                variant: 'outline',
                showLabel: false,
                tooltip: 'Abrir lançamento',
                href: (installment) =>
                    installment.entry ? show.url(installment.entry.uuid) : '#',
            },
        ],
        [],
    );

    const projectedDirection: FinancialDirection =
        summary.projected_balance >= 0 ? 'receivable' : 'payable';

    return (
        <>
            <Head title="Agenda financeira" />

            <ContentHeader>
                <Heading
                    title="Agenda financeira"
                    description="Parcelas organizadas por vencimento para acompanhar o caixa do mês."
                />

                <Button asChild>
                    <Link href={create.url()}>
                        <Plus className="size-4" />
                        Novo lançamento
                    </Link>
                </Button>
            </ContentHeader>
            <div className="page-wrapper-xl">
                <div className="page-section">
                    <div className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between">
                        <Tabs value={direction}>
                            <TabsList
                                variant="line"
                                aria-label="Direção das parcelas"
                            >
                                {directionTabs.map((tab) => (
                                    <TabsTrigger
                                        key={tab.value}
                                        value={tab.value}
                                        asChild
                                    >
                                        <Link
                                            href={agendaUrl(
                                                tab.value,
                                                period.month,
                                            )}
                                            preserveScroll
                                            preserveState
                                        >
                                            {tab.label}
                                        </Link>
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </Tabs>

                        <div className="flex items-center gap-1 self-stretch sm:self-auto">
                            <Button variant="ghost" size="icon" asChild>
                                <Link
                                    href={agendaUrl(
                                        direction,
                                        shiftMonth(period.month, -1),
                                    )}
                                    preserveScroll
                                    preserveState
                                    aria-label="Mês anterior"
                                >
                                    <ChevronLeft className="size-4" />
                                </Link>
                            </Button>

                            <div className="flex min-w-44 flex-1 items-center justify-center gap-2 px-2 sm:flex-none">
                                <CalendarDays className="size-4 text-muted-foreground" />
                                <span className="text-sm font-semibold">
                                    {monthLabel(period.month)}
                                </span>
                            </div>

                            <Button variant="ghost" size="icon" asChild>
                                <Link
                                    href={agendaUrl(
                                        direction,
                                        shiftMonth(period.month, 1),
                                    )}
                                    preserveScroll
                                    preserveState
                                    aria-label="Próximo mês"
                                >
                                    <ChevronRight className="size-4" />
                                </Link>
                            </Button>

                            {period.month !== currentMonth() ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={agendaUrl(
                                            direction,
                                            currentMonth(),
                                        )}
                                        preserveScroll
                                        preserveState
                                    >
                                        Mês atual
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-y-6 border-b pb-7 xl:grid-cols-4">
                        <SummaryCard
                            label="A receber"
                            helper={`${summary.receivable.count} ${summary.receivable.count === 1 ? 'parcela pendente' : 'parcelas pendentes'}`}
                            icon={TrendingUp}
                            iconClassName="bg-inflow-soft text-inflow-soft-foreground"
                        >
                            <MoneyAmount
                                value={summary.receivable.amount}
                                direction="receivable"
                            />
                        </SummaryCard>

                        <SummaryCard
                            label="A pagar"
                            helper={`${summary.payable.count} ${summary.payable.count === 1 ? 'parcela pendente' : 'parcelas pendentes'}`}
                            icon={TrendingDown}
                            iconClassName="bg-outflow-soft text-outflow-soft-foreground"
                        >
                            <MoneyAmount
                                value={summary.payable.amount}
                                direction="payable"
                            />
                        </SummaryCard>

                        <SummaryCard
                            label="Saldo projetado"
                            helper="Recebimentos menos pagamentos"
                            icon={WalletCards}
                        >
                            <MoneyAmount
                                value={Math.abs(summary.projected_balance)}
                                direction={projectedDirection}
                                signed
                            />
                        </SummaryCard>

                        <SummaryCard
                            label="Vencidas no mês"
                            helper="Parcelas pendentes até hoje"
                            icon={CircleAlert}
                            iconClassName={
                                summary.overdue_count > 0
                                    ? 'bg-destructive/10 text-destructive'
                                    : undefined
                            }
                        >
                            <span
                                className={cn(
                                    'font-tabular',
                                    summary.overdue_count > 0 &&
                                        'text-destructive',
                                )}
                            >
                                {summary.overdue_count}
                            </span>
                        </SummaryCard>
                    </div>

                    <DataTable<FinancialInstallment>
                        queryKey={`financial-agenda-${direction}-${period.month}`}
                        endpoint={apiRoutes.financialInstallments.index(
                            direction,
                            period.month,
                        )}
                        mode="infinite-scroll"
                        columns={columns}
                        keyExtractor={(installment) => installment.uuid}
                        features={{
                            search: true,
                            filters: true,
                            columnVisibility: true,
                            refresh: true,
                            export: true,
                        }}
                        initialFilters={initialAgendaFilters}
                        persistState={{
                            key: 'datatable-state:financial-agenda',
                            filters: true,
                            search: true,
                            sorting: true,
                            perPage: true,
                            columnVisibility: true,
                        }}
                        exportConfig={{
                            ...exportConfig,
                            fixedFilters: {
                                due_date: {
                                    operator: 'between',
                                    value: {
                                        from: period.starts_at,
                                        to: period.ends_at,
                                    },
                                },
                                ...(direction === 'all'
                                    ? {}
                                    : { export_direction: direction }),
                            },
                        }}
                        filters={filters}
                        quickFilters={quickFilters}
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
                            gridLines: 'horizontal',
                            rowStyle: 'plain',
                        }}
                        searchPlaceholder="Buscar parcelas..."
                        emptyMessage="Nenhuma parcela encontrada neste mês."
                    />
                </div>
            </div>

            <SettlementDialog
                companyId={settlementInstallment?.company_id ?? 0}
                currency={settlementInstallment?.currency ?? 'BRL'}
                installment={settlementInstallment}
                open={settlementInstallment !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSettlementInstallment(null);
                    }
                }}
            />
        </>
    );
}
