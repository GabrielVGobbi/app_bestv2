import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Check, RefreshCw } from 'lucide-react';
import type { ReactNode } from 'react';
import { dashboard } from '@/actions/App/Http/Controllers/AppController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
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
    tone?: 'inflow' | 'outflow' | 'warning' | 'destructive' | 'muted';
};

type Priority = {
    label: string;
    value: string;
    href: string;
    tone?: 'warning' | 'destructive';
};

function currency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value / 100);
}

function OpenLink({ href }: { href: string }) {
    return (
        <Link
            href={href}
            className="group inline-flex h-7 items-center gap-1.5 rounded-full pr-1 pl-3 text-[11px] font-medium tracking-wide text-muted-foreground transition-[color,background-color,transform] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted hover:text-foreground active:scale-[0.98]"
        >
            Abrir
            <span className="flex size-5 items-center justify-center rounded-full bg-foreground/[0.06] transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:translate-x-0.5">
                <ArrowRight className="size-3" />
            </span>
        </Link>
    );
}

function SummaryGroup({
    title,
    href,
    delayClass,
    children,
}: {
    title: string;
    href: string;
    delayClass?: string;
    children: ReactNode;
}) {
    return (
        <section
            className={cn(
                'animate-in fade-in slide-in-from-bottom-2 duration-500 ease-[cubic-bezier(0.32,0.72,0,1)]',
                delayClass,
            )}
        >
            <Card className="gap-4 py-5">
                <CardHeader className="flex-row items-center justify-between gap-3 px-6">
                    <CardTitle className="text-[13px] tracking-[0.08em] uppercase">
                        {title}
                    </CardTitle>
                    <OpenLink href={href} />
                </CardHeader>
                <CardContent>{children}</CardContent>
            </Card>
        </section>
    );
}

function SummaryValues({ items }: { items: SummaryItem[] }) {
    const columns =
        items.length > 2 ? 'sm:grid-cols-3' : 'sm:grid-cols-2';

    return (
        <dl className={cn('grid gap-6 sm:gap-0', columns)}>
            {items.map((item) => (
                <div
                    key={item.label}
                    className="min-w-0 sm:border-l sm:border-border/70 sm:px-6 first:border-l-0 first:pl-0"
                >
                    <dt className="text-[11px] leading-none font-medium tracking-[0.14em] text-muted-foreground uppercase">
                        {item.label}
                    </dt>
                    <dd
                        className={cn(
                            'mt-3 text-[1.75rem] leading-none font-semibold tracking-[-0.04em] tabular-nums',
                            item.tone === 'inflow' && 'text-inflow',
                            item.tone === 'outflow' && 'text-outflow',
                            item.tone === 'warning' &&
                                'text-warning-soft-foreground',
                            item.tone === 'destructive' && 'text-destructive',
                            item.tone === 'muted' && 'text-muted-foreground',
                            !item.tone && 'text-foreground',
                        )}
                    >
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function DashboardSkeleton() {
    return (
        <div className="space-y-4" aria-label="Carregando resumo">
            {['Vendas', 'Playlists', 'Financeiro'].map((group) => (
                <Card key={group} className="gap-4 py-5">
                    <CardHeader className="flex-row items-center justify-between px-6">
                        <Skeleton className="h-3 w-20" />
                        <Skeleton className="h-7 w-16 rounded-full" />
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 sm:grid-cols-3 sm:gap-0">
                            {Array.from({ length: 3 }).map((_, index) => (
                                <div
                                    key={index}
                                    className="space-y-3 sm:border-l sm:border-border/70 sm:px-6 first:border-l-0 first:pl-0"
                                >
                                    <Skeleton className="h-3 w-28" />
                                    <Skeleton className="h-8 w-16" />
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function OperationalSummary() {
    const { operationalDashboard } = usePage<DashboardProps>().props;

    if (!operationalDashboard) {
        return (
            <Card className="flex-row items-center justify-between gap-4 px-6 py-5">
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
            </Card>
        );
    }

    const priorities: Priority[] = [];

    if (operationalDashboard.sales?.awaiting_quotes) {
        priorities.push({
            label: 'Orçamentos aguardando avanço',
            value: String(operationalDashboard.sales.awaiting_quotes),
            href: salesIndex().url,
            tone: 'warning',
        });
    }

    if (operationalDashboard.playlists?.pending_deliveries) {
        priorities.push({
            label: 'Entregas de playlist pendentes',
            value: String(operationalDashboard.playlists.pending_deliveries),
            href: deliveryTasksIndex().url,
            tone: 'warning',
        });
    }

    if (operationalDashboard.finance?.pending_reconciliations) {
        priorities.push({
            label: 'Conciliações para revisar',
            value: String(operationalDashboard.finance.pending_reconciliations),
            href: reconciliationIndex().url,
            tone: 'warning',
        });
    }

    return (
        <>
            <div className="space-y-4">
                {operationalDashboard.sales && (
                    <SummaryGroup
                        title="Vendas"
                        href={salesIndex().url}
                        delayClass="delay-75"
                    >
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
                                    tone:
                                        operationalDashboard.sales
                                            .awaiting_quotes > 0
                                            ? 'warning'
                                            : 'muted',
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
                    <SummaryGroup
                        title="Playlists"
                        href={playlistsIndex().url}
                        delayClass="delay-150"
                    >
                        <SummaryValues
                            items={[
                                {
                                    label: 'Entregas pendentes',
                                    value: String(
                                        operationalDashboard.playlists
                                            .pending_deliveries,
                                    ),
                                    tone:
                                        operationalDashboard.playlists
                                            .pending_deliveries > 0
                                            ? 'warning'
                                            : 'muted',
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
                        delayClass="delay-200"
                    >
                        <SummaryValues
                            items={[
                                {
                                    label: 'A receber em atraso',
                                    value: currency(
                                        operationalDashboard.finance
                                            .overdue_receivables,
                                    ),
                                    tone:
                                        operationalDashboard.finance
                                            .overdue_receivables > 0
                                            ? 'destructive'
                                            : 'muted',
                                },
                                {
                                    label: 'A pagar em atraso',
                                    value: currency(
                                        operationalDashboard.finance
                                            .overdue_payables,
                                    ),
                                    tone:
                                        operationalDashboard.finance
                                            .overdue_payables > 0
                                            ? 'destructive'
                                            : 'muted',
                                },
                                {
                                    label: 'Conciliações pendentes',
                                    value: String(
                                        operationalDashboard.finance
                                            .pending_reconciliations,
                                    ),
                                    tone:
                                        operationalDashboard.finance
                                            .pending_reconciliations > 0
                                            ? 'warning'
                                            : 'muted',
                                },
                            ]}
                        />
                    </SummaryGroup>
                )}
            </div>

            <section
                className="animate-in fade-in slide-in-from-bottom-2 mt-8 duration-500 delay-300 ease-[cubic-bezier(0.32,0.72,0,1)]"
                aria-labelledby="priorities-title"
            >
                <Card className="gap-4 py-5">
                    <CardHeader className="px-6">
                        <CardTitle
                            id="priorities-title"
                            className="text-[13px] tracking-[0.08em] uppercase"
                        >
                            Pendências
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {priorities.length > 0 ? (
                            <ul className="divide-y divide-border/70">
                                {priorities.map((priority) => (
                                    <li key={priority.label}>
                                        <Link
                                            href={priority.href}
                                            className="group flex min-h-12 items-center gap-4 py-3 text-sm first:pt-0 last:pb-0"
                                        >
                                            <span className="min-w-0 flex-1 text-foreground">
                                                {priority.label}
                                            </span>
                                            <span
                                                className={cn(
                                                    'font-semibold tabular-nums',
                                                    priority.tone ===
                                                        'warning' &&
                                                        'text-warning-soft-foreground',
                                                    priority.tone ===
                                                        'destructive' &&
                                                        'text-destructive',
                                                    !priority.tone &&
                                                        'text-foreground',
                                                )}
                                            >
                                                {priority.value}
                                            </span>
                                            <span className="flex size-6 items-center justify-center rounded-full bg-foreground/[0.06] transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:translate-x-0.5">
                                                <ArrowRight className="size-3 text-muted-foreground" />
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                <span className="flex size-7 items-center justify-center rounded-full bg-primary/8 text-primary">
                                    <Check className="size-3.5" />
                                </span>
                                Nenhuma pendência agora.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </section>
        </>
    );
}

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto w-full max-w-[52rem] px-5 pt-12 pb-20 sm:px-8 md:pt-16">
                <header className="animate-in fade-in slide-in-from-bottom-1 mb-10 duration-500 ease-[cubic-bezier(0.32,0.72,0,1)]">
                    <p className="text-[10px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                        Operação
                    </p>
                    <h1 className="font-brand mt-3 text-[2.35rem] leading-[1.05] font-semibold tracking-[-0.04em] text-balance text-foreground">
                        Visão geral
                    </h1>
                    <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
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
