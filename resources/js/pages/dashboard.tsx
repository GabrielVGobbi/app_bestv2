import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    ListMusic,
    RefreshCw,
    ShoppingBag,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { dashboard } from '@/actions/App/Http/Controllers/AppController';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
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

type Tone = 'success' | 'warning' | 'destructive' | 'muted';

type SummaryItem = {
    label: string;
    value: string;
    tone?: Tone;
};

type Priority = {
    label: string;
    value: string;
    href: string;
};

const panel =
    'rounded-lg border border-border bg-card shadow-xs dark:shadow-none';
const focus =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background';
const toneText: Record<Tone, string> = {
    success: 'text-success',
    warning: 'text-warning-soft-foreground',
    destructive: 'text-destructive',
    muted: 'text-muted-foreground',
};

function currency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value / 100);
}

function alertTone(count: number, tone: Tone = 'warning'): Tone {
    return count > 0 ? tone : 'muted';
}

function SummaryPanel({
    title,
    href,
    icon: Icon,
    items,
}: {
    title: string;
    href: string;
    icon: LucideIcon;
    items: SummaryItem[];
}) {
    return (
        <section className={cn(panel, 'p-3 xl:col-span-4')}>
            <div className="flex items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <Icon className="size-3.5" aria-hidden="true" />
                    </span>
                    <h2 className="truncate text-2xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {title}
                    </h2>
                </div>
                <Link
                    href={href}
                    className={cn(
                        'inline-flex h-7 items-center gap-1 rounded-sm px-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        focus,
                    )}
                >
                    Abrir
                    <ArrowRight className="size-3.5" aria-hidden="true" />
                </Link>
            </div>

            <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-3">
                {items.map((item) => (
                    <div key={item.label} className="min-w-0">
                        <dt className="text-2xs text-muted-foreground">
                            {item.label}
                        </dt>
                        <dd
                            className={cn(
                                'mt-1 truncate text-xl font-bold tracking-tight tabular-nums',
                                item.tone
                                    ? toneText[item.tone]
                                    : 'text-foreground',
                            )}
                        >
                            {item.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

function DashboardSkeleton() {
    return (
        <div
            className="grid gap-3 xl:grid-cols-12"
            aria-label="Carregando resumo"
        >
            {['Vendas', 'Playlists', 'Financeiro'].map((group) => (
                <div key={group} className={cn(panel, 'p-3 xl:col-span-4')}>
                    <Skeleton className="h-7 w-28" />
                    <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, index) => (
                            <div key={index} className="space-y-2">
                                <Skeleton className="h-3 w-20" />
                                <Skeleton className="h-6 w-14" />
                            </div>
                        ))}
                    </div>
                </div>
            ))}
            <Skeleton className="h-28 rounded-lg xl:col-span-12" />
        </div>
    );
}

function OperationalSummary() {
    const { operationalDashboard } = usePage<DashboardProps>().props;

    if (!operationalDashboard) {
        return (
            <div
                className={cn(
                    panel,
                    'flex items-center justify-between gap-4 p-3',
                )}
            >
                <p className="text-sm text-muted-foreground">
                    Não foi possível carregar o resumo.
                </p>
                <Button
                    variant="ghost"
                    onClick={() =>
                        router.reload({ only: ['operationalDashboard'] })
                    }
                >
                    <RefreshCw className="size-4" aria-hidden="true" />
                    Recarregar
                </Button>
            </div>
        );
    }

    const { sales, playlists, finance } = operationalDashboard;
    const priorities: Priority[] = [];

    if (sales?.awaiting_quotes) {
        priorities.push({
            label: 'Orçamentos aguardando avanço',
            value: String(sales.awaiting_quotes),
            href: salesIndex().url,
        });
    }

    if (playlists?.pending_deliveries) {
        priorities.push({
            label: 'Entregas de playlist pendentes',
            value: String(playlists.pending_deliveries),
            href: deliveryTasksIndex().url,
        });
    }

    if (finance?.pending_reconciliations) {
        priorities.push({
            label: 'Conciliações para revisar',
            value: String(finance.pending_reconciliations),
            href: reconciliationIndex().url,
        });
    }

    return (
        <div className="grid gap-3 xl:grid-cols-12">
            {sales && (
                <SummaryPanel
                    title="Vendas"
                    href={salesIndex().url}
                    icon={ShoppingBag}
                    items={[
                        {
                            label: 'Campanhas ativas',
                            value: String(sales.active_campaigns),
                        },
                        {
                            label: 'Orçamentos em espera',
                            value: String(sales.awaiting_quotes),
                            tone: alertTone(sales.awaiting_quotes),
                        },
                        ...(sales.month_amount !== undefined
                            ? [
                                  {
                                      label: 'Confirmado no mês',
                                      value: currency(sales.month_amount),
                                  },
                              ]
                            : []),
                    ]}
                />
            )}

            {playlists && (
                <SummaryPanel
                    title="Playlists"
                    href={playlistsIndex().url}
                    icon={ListMusic}
                    items={[
                        {
                            label: 'Entregas pendentes',
                            value: String(playlists.pending_deliveries),
                            tone: alertTone(playlists.pending_deliveries),
                        },
                        {
                            label: 'Slots ativos',
                            value: String(playlists.active_slots),
                        },
                    ]}
                />
            )}

            {finance && (
                <SummaryPanel
                    title="Financeiro"
                    href={financialReportsIndex().url}
                    icon={WalletCards}
                    items={[
                        {
                            label: 'A receber em atraso',
                            value: currency(finance.overdue_receivables),
                            tone: alertTone(
                                finance.overdue_receivables,
                                'destructive',
                            ),
                        },
                        {
                            label: 'A pagar em atraso',
                            value: currency(finance.overdue_payables),
                            tone: alertTone(
                                finance.overdue_payables,
                                'destructive',
                            ),
                        },
                        {
                            label: 'Conciliações pendentes',
                            value: String(finance.pending_reconciliations),
                            tone: alertTone(finance.pending_reconciliations),
                        },
                    ]}
                />
            )}

            <section
                className={cn(panel, 'p-3 xl:col-span-12')}
                aria-labelledby="priorities-title"
            >
                <h2
                    id="priorities-title"
                    className="text-2xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Pendências
                </h2>

                {priorities.length > 0 ? (
                    <ul className="mt-2 divide-y divide-separator">
                        {priorities.map((priority) => (
                            <li key={priority.label}>
                                <Link
                                    href={priority.href}
                                    className={cn(
                                        'group -mx-1.5 flex min-h-11 items-center gap-3 rounded-sm px-1.5 text-xs transition-colors hover:bg-accent',
                                        focus,
                                    )}
                                >
                                    <span className="size-1.5 shrink-0 rounded-full bg-warning" />
                                    <span className="min-w-0 flex-1 truncate text-foreground">
                                        {priority.label}
                                    </span>
                                    <span className="font-semibold text-warning-soft-foreground tabular-nums">
                                        {priority.value}
                                    </span>
                                    <ArrowRight
                                        className="size-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                        <span className="flex size-6 items-center justify-center rounded-full bg-success-soft text-success-soft-foreground">
                            <Check className="size-3.5" aria-hidden="true" />
                        </span>
                        Nenhuma pendência agora.
                    </div>
                )}
            </section>
        </div>
    );
}

export default function Dashboard() {
    const { auth } = usePage().props;
    const firstName = auth.user?.name?.split(' ')[0] ?? '';

    return (
        <>
            <Head title="Dashboard" />

            <div className="page-wrapper space-y-5">
                <PageHeader
                    eyebrow={
                        firstName ? (
                            <>
                                Olá,{' '}
                                <span className="font-semibold text-foreground">
                                    {firstName}
                                </span>
                            </>
                        ) : undefined
                    }
                    title="Visão geral"
                    description="Dados atualizados da operação."
                />

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
