import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Check, RefreshCw } from 'lucide-react';
import type { ReactNode } from 'react';
import { dashboard } from '@/actions/App/Http/Controllers/AppController';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { index as reconciliationIndex } from '@/routes/financial-reconciliation/imports';
import { index as financialReportsIndex } from '@/routes/financial-reports';
import { index as playlistsIndex } from '@/routes/playlists';
import { index as deliveryTasksIndex } from '@/routes/playlists/delivery-tasks';
import { index as salesIndex } from '@/routes/sales';
import type { OperationalDashboard } from '@/types';

type DashboardProps = {
    operationalDashboard?: OperationalDashboard | null;
};

type SummaryItem = {
    label: string;
    value: string;
};

type Priority = {
    label: string;
    value: string;
    href: string;
};

function currency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value / 100);
}

function SummaryGroup({
    title,
    href,
    children,
}: {
    title: string;
    href: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-5 border-t border-border py-6 last:border-b md:grid-cols-[9rem_1fr] md:gap-10">
            <div>
                <h2 className="text-sm font-semibold text-foreground">
                    {title}
                </h2>
                <Link
                    href={href}
                    className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    Abrir <ArrowRight className="size-3" />
                </Link>
            </div>
            {children}
        </section>
    );
}

function SummaryValues({ items }: { items: SummaryItem[] }) {
    return (
        <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((item) => (
                <div key={item.label} className="min-w-0">
                    <dt className="text-sm text-muted-foreground">
                        {item.label}
                    </dt>
                    <dd className="mt-1 text-xl font-semibold text-foreground tabular-nums">
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function DashboardSkeleton() {
    return (
        <div aria-label="Carregando resumo">
            {['Vendas', 'Playlists', 'Financeiro'].map((group) => (
                <div
                    key={group}
                    className="grid gap-5 border-t border-border py-6 last:border-b md:grid-cols-[9rem_1fr] md:gap-10"
                >
                    <div className="space-y-2">
                        <Skeleton className="h-4 w-20" />
                        <Skeleton className="h-3 w-10" />
                    </div>
                    <div className="grid gap-8 sm:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, index) => (
                            <div key={index} className="space-y-2">
                                <Skeleton className="h-4 w-28" />
                                <Skeleton className="h-6 w-16" />
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function OperationalSummary() {
    const { operationalDashboard } = usePage<DashboardProps>().props;

    if (!operationalDashboard) {
        return (
            <div className="flex items-center justify-between gap-4 border-y border-border py-5">
                <p className="text-sm text-muted-foreground">
                    Não foi possível carregar o resumo.
                </p>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        router.reload({ only: ['operationalDashboard'] })
                    }
                >
                    <RefreshCw className="size-4" /> Recarregar
                </Button>
            </div>
        );
    }

    const priorities: Priority[] = [];

    if (operationalDashboard.sales?.awaiting_quotes) {
        priorities.push({
            label: 'Orçamentos aguardando avanço',
            value: String(operationalDashboard.sales.awaiting_quotes),
            href: salesIndex().url,
        });
    }

    if (operationalDashboard.playlists?.pending_deliveries) {
        priorities.push({
            label: 'Entregas de playlist pendentes',
            value: String(operationalDashboard.playlists.pending_deliveries),
            href: deliveryTasksIndex().url,
        });
    }

    if (operationalDashboard.finance?.pending_reconciliations) {
        priorities.push({
            label: 'Conciliações para revisar',
            value: String(operationalDashboard.finance.pending_reconciliations),
            href: reconciliationIndex().url,
        });
    }

    return (
        <>
            <div>
                {operationalDashboard.sales && (
                    <SummaryGroup title="Vendas" href={salesIndex().url}>
                        <SummaryValues
                            items={[
                                {
                                    label: 'Campanhas ativas',
                                    value: String(
                                        operationalDashboard.sales
                                            .active_campaigns,
                                    ),
                                },
                                {
                                    label: 'Orçamentos em espera',
                                    value: String(
                                        operationalDashboard.sales
                                            .awaiting_quotes,
                                    ),
                                },
                                ...(operationalDashboard.sales.month_amount !==
                                undefined
                                    ? [
                                          {
                                              label: 'Confirmado no mês',
                                              value: currency(
                                                  operationalDashboard.sales
                                                      .month_amount,
                                              ),
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    </SummaryGroup>
                )}

                {operationalDashboard.playlists && (
                    <SummaryGroup title="Playlists" href={playlistsIndex().url}>
                        <SummaryValues
                            items={[
                                {
                                    label: 'Entregas pendentes',
                                    value: String(
                                        operationalDashboard.playlists
                                            .pending_deliveries,
                                    ),
                                },
                                {
                                    label: 'Slots ativos',
                                    value: String(
                                        operationalDashboard.playlists
                                            .active_slots,
                                    ),
                                },
                            ]}
                        />
                    </SummaryGroup>
                )}

                {operationalDashboard.finance && (
                    <SummaryGroup
                        title="Financeiro"
                        href={financialReportsIndex().url}
                    >
                        <SummaryValues
                            items={[
                                {
                                    label: 'A receber em atraso',
                                    value: currency(
                                        operationalDashboard.finance
                                            .overdue_receivables,
                                    ),
                                },
                                {
                                    label: 'A pagar em atraso',
                                    value: currency(
                                        operationalDashboard.finance
                                            .overdue_payables,
                                    ),
                                },
                                {
                                    label: 'Conciliações pendentes',
                                    value: String(
                                        operationalDashboard.finance
                                            .pending_reconciliations,
                                    ),
                                },
                            ]}
                        />
                    </SummaryGroup>
                )}
            </div>

            <section className="mt-14" aria-labelledby="priorities-title">
                <h2 id="priorities-title" className="text-xl font-semibold">
                    Pendências
                </h2>

                {priorities.length > 0 ? (
                    <ul className="mt-4 divide-y divide-border border-y border-border">
                        {priorities.map((priority) => (
                            <li key={priority.label}>
                                <Link
                                    href={priority.href}
                                    className="group flex min-h-14 items-center gap-4 py-3 text-sm"
                                >
                                    <span className="min-w-0 flex-1 text-foreground">
                                        {priority.label}
                                    </span>
                                    <span className="font-medium text-foreground tabular-nums">
                                        {priority.value}
                                    </span>
                                    <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="mt-4 flex items-center gap-3 border-y border-border py-5 text-sm text-muted-foreground">
                        <Check className="size-4 text-primary" />
                        Nenhuma pendência agora.
                    </div>
                )}
            </section>
        </>
    );
}

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto w-full max-w-[55rem] px-5 pt-16 pb-24 sm:px-8 md:pt-20">
                <header className="mb-12">
                    <h1 className="text-[2.125rem] leading-tight font-semibold text-balance text-foreground">
                        Visão geral
                    </h1>
                    <p className="mt-2 text-base text-muted-foreground">
                        Dados atualizados da operação.
                    </p>
                </header>

                <Deferred
                    data="operationalDashboard"
                    fallback={<DashboardSkeleton />}
                >
                    <OperationalSummary />
                </Deferred>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
