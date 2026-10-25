import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    AlertTriangle,
    ChartNoAxesColumn,
    Clock,
    Landmark,
    ReceiptText,
    WalletCards,
} from 'lucide-react';

import { show } from '@/actions/App/Modules/Finance/Http/Controllers/FinancialEntryController';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { StatusPresenter } from '@/types/status';

interface DashboardCard {
    label: string;
    amount: number;
}

interface AccountBalance {
    id: number;
    uuid: string;
    name: string;
    type_label: string;
    current_balance_amount: number;
    currency: string;
    active: boolean;
}

interface DashboardPayload {
    cards: Record<string, DashboardCard>;
    account_balances?: AccountBalance[];
}

interface InstallmentsScheduleItem {
    id: number;
    uuid: string;
    installment_number: number;
    status: string;
    status_presenter: StatusPresenter;
    due_date: string | null;
    balance_amount: number;
    currency: string;
    direction_label: string;
    counterparty_label: string | null;
    entry: {
        uuid: string;
        description: string;
    } | null;
    category: {
        full_path: string;
    } | null;
}

interface InstallmentsSchedulePayload {
    summary: {
        count: number;
        open_amount: number;
        truncated: boolean;
        limit: number;
    };
    items: InstallmentsScheduleItem[];
}

interface Props {
    dashboard: DashboardPayload;
    installmentsSchedule: InstallmentsSchedulePayload;
}

const cardOrder = [
    'open_receivables',
    'overdue_receivables',
    'open_payables',
    'overdue_payables',
    'monthly_settlements',
    'pending_reconciliation',
] as const;

const cardIcons = {
    open_receivables: WalletCards,
    overdue_receivables: AlertTriangle,
    open_payables: ReceiptText,
    overdue_payables: Clock,
    monthly_settlements: ChartNoAxesColumn,
    pending_reconciliation: ReceiptText,
};

const reportRows = [
    {
        title: 'Recebíveis em aberto',
        description: 'Títulos a receber ainda com saldo operacional.',
    },
    {
        title: 'Pagáveis em aberto',
        description: 'Títulos a pagar ainda com saldo operacional.',
    },
    {
        title: 'Pagamentos e recebimentos realizados',
        description: 'Baixas postadas no período selecionado.',
    },
    {
        title: 'Fluxo previsto e realizado',
        description: 'Comparativo por competência, vencimento e liquidação.',
    },
];

function money(value: number, currency = 'BRL'): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency,
    }).format(value / 100);
}

function date(value: string | null): string {
    if (!value) {
        return 'Sem data';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function cardValue(key: string, card: DashboardCard): string {
    if (key === 'pending_reconciliation') {
        return new Intl.NumberFormat('pt-BR').format(card.amount);
    }

    return money(card.amount);
}

export default function FinancialReportsIndex({
    dashboard,
    installmentsSchedule,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Relatórios financeiros', href: '/financial-reports' },
        ],
    });

    return (
        <>
            <Head title="Relatórios financeiros" />

            <div className="page-wrapper-lg">
                <div className="flex h-full flex-1 flex-col gap-6 p-4">
                    <Heading
                        title="Relatórios financeiros"
                        description="Visão consolidada de saldos, baixas e pendências de conciliação."
                    />

                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {cardOrder.map((key) => {
                            const card = dashboard.cards[key];
                            const Icon = cardIcons[key];

                            if (!card) {
                                return null;
                            }

                            return (
                                <Card key={key} className="gap-4 py-5">
                                    <CardHeader className="flex-row items-center justify-between gap-3">
                                        <CardTitle>{card.label}</CardTitle>
                                        <span className="flex size-8 items-center justify-center rounded-md border bg-muted/30 text-muted-foreground">
                                            <Icon className="size-4" />
                                        </span>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-2xl font-semibold tracking-normal">
                                            {cardValue(key, card)}
                                        </p>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <CardTitle>Saldos por conta</CardTitle>
                            <Landmark className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            {dashboard.account_balances?.length ? (
                                <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                    {dashboard.account_balances.map(
                                        (account) => (
                                            <div
                                                key={account.uuid}
                                                className="rounded-md border px-3 py-2"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">
                                                        {account.name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {account.type_label}
                                                    </p>
                                                </div>
                                                <p className="mt-3 text-base font-semibold">
                                                    {money(
                                                        account.current_balance_amount,
                                                        account.currency,
                                                    )}
                                                </p>
                                            </div>
                                        ),
                                    )}
                                </div>
                            ) : (
                                <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    Nenhuma conta financeira ativa cadastrada.
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>Agenda de parcelas</CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Próximas parcelas abertas por vencimento.
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-sm text-muted-foreground">
                                    Saldo aberto
                                </p>
                                <p className="text-lg font-semibold">
                                    {money(
                                        installmentsSchedule.summary
                                            .open_amount,
                                    )}
                                </p>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-hidden rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-3 py-3">
                                                Vencimento
                                            </th>
                                            <th className="px-3 py-3">
                                                Parcela
                                            </th>
                                            <th className="px-3 py-3">
                                                Lançamento
                                            </th>
                                            <th className="px-3 py-3">
                                                Status
                                            </th>
                                            <th className="px-3 py-3 text-right">
                                                Saldo
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {installmentsSchedule.items.length ===
                                        0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="px-3 py-8 text-center text-muted-foreground"
                                                >
                                                    Nenhuma parcela aberta no
                                                    recorte atual.
                                                </td>
                                            </tr>
                                        ) : (
                                            installmentsSchedule.items.map(
                                                (item) => (
                                                    <tr key={item.uuid}>
                                                        <td className="px-3 py-3">
                                                            {date(
                                                                item.due_date,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="font-medium">
                                                                #
                                                                {
                                                                    item.installment_number
                                                                }
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    item.direction_label
                                                                }
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            {item.entry ? (
                                                                <Link
                                                                    href={show.url(
                                                                        item
                                                                            .entry
                                                                            .uuid,
                                                                    )}
                                                                    className="font-medium text-primary underline-offset-4 hover:underline"
                                                                >
                                                                    {
                                                                        item
                                                                            .entry
                                                                            .description
                                                                    }
                                                                </Link>
                                                            ) : (
                                                                <span className="font-medium">
                                                                    Sem
                                                                    lançamento
                                                                </span>
                                                            )}
                                                            <div className="text-xs text-muted-foreground">
                                                                {item.category
                                                                    ?.full_path ??
                                                                    item.counterparty_label ??
                                                                    'Sem categoria'}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <StatusBadge
                                                                presenter={
                                                                    item.status_presenter
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-3 py-3 text-right font-medium">
                                                            {money(
                                                                item.balance_amount,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {installmentsSchedule.summary.truncated ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Mostrando as primeiras{' '}
                                    {installmentsSchedule.summary.limit}{' '}
                                    parcelas por vencimento.
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Relatórios iniciais</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-hidden rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-3 py-3">
                                                Relatório
                                            </th>
                                            <th className="px-3 py-3">
                                                Escopo
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {reportRows.map((row) => (
                                            <tr key={row.title}>
                                                <td className="px-3 py-3 font-medium">
                                                    {row.title}
                                                </td>
                                                <td className="px-3 py-3 text-muted-foreground">
                                                    {row.description}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
