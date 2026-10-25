import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    CircleAlert,
    ListMusic,
    RefreshCw,
    ShoppingBag,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { dashboard } from '@/actions/App/Http/Controllers/AppController';
import ContentHeader from '@/components/content-header';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { index as reconciliationIndex } from '@/routes/financial-reconciliation/imports';
import { index as financialReportsIndex } from '@/routes/financial-reports';
import { index as playlistsIndex } from '@/routes/playlists';
import { index as deliveryTasksIndex } from '@/routes/playlists/delivery-tasks';
import { index as salesIndex } from '@/routes/sales';
import type { OperationalDashboard } from '@/types';

type DashboardProps = { operationalDashboard?: OperationalDashboard | null };
type SummaryItem = { label: string; value: string; attention?: boolean };
type Priority = {
    label: string;
    value: string;
    href: string;
    overdue?: boolean;
};

function currency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value / 100);
}

function OverviewSection({
    title,
    description,
    href,
    icon: Icon,
    items,
}: {
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
    items: SummaryItem[];
}) {
    return (
        <section className="overview-section">
            <div className="mb-5 flex items-center gap-3">
                <Icon
                    className="size-5 text-muted-foreground"
                    strokeWidth={1.6}
                />
                <div className="min-w-0 flex-1">
                    <h2 className="text-lg font-semibold">{title}</h2>
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                </div>
                <Button variant="ghost" size="sm" asChild>
                    <Link href={href} aria-label={'Abrir ' + title}>
                        Abrir
                        <ArrowRight className="size-3.5" />
                    </Link>
                </Button>
            </div>
            <dl className="overview-metrics">
                {items.map((item) => (
                    <div key={item.label} className="min-w-0">
                        <dt className="text-sm leading-6 text-muted-foreground">
                            {item.label}
                        </dt>
                        <dd
                            className={cn(
                                'mt-2 text-[30px] leading-tight font-medium tracking-tight tabular-nums',
                                item.attention && 'text-destructive',
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
            className="grid gap-10 xl:grid-cols-[minmax(0,1fr)_320px]"
            aria-label="Carregando resumo"
            role="status"
        >
            <div className="space-y-8">
                {Array.from({ length: 3 }, (_, i) => (
                    <div key={i} className="space-y-4">
                        <Skeleton className="h-6 w-36" />
                        <Skeleton className="h-4 w-64" />
                        <Skeleton className="h-24 w-full rounded-xl" />
                    </div>
                ))}
            </div>
            <Skeleton className="h-80 rounded-xl" />
        </div>
    );
}

function OperationalSummary() {
    const { operationalDashboard: data } = usePage<DashboardProps>().props;

    if (!data) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border p-6">
                <p className="text-sm text-muted-foreground">
                    Não foi possível carregar o resumo.
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        router.reload({ only: ['operationalDashboard'] })
                    }
                >
                    <RefreshCw className="size-4" />
                    Recarregar
                </Button>
            </div>
        );
    }

    const priorities: Priority[] = [];

    if (data.sales?.awaiting_quotes) {
        priorities.push({
            label: 'Orçamentos aguardando avanço',
            value: String(data.sales.awaiting_quotes),
            href: salesIndex().url,
        });
    }

    if (data.playlists?.pending_deliveries) {
        priorities.push({
            label: 'Entregas de playlist pendentes',
            value: String(data.playlists.pending_deliveries),
            href: deliveryTasksIndex().url,
        });
    }

    if (data.finance?.pending_reconciliations) {
        priorities.push({
            label: 'Conciliações para revisar',
            value: String(data.finance.pending_reconciliations),
            href: reconciliationIndex().url,
        });
    }

    if (data.finance?.overdue_receivables) {
        priorities.push({
            label: 'Recebimentos em atraso',
            value: currency(data.finance.overdue_receivables),
            href: financialReportsIndex().url,
            overdue: true,
        });
    }

    if (data.finance?.overdue_payables) {
        priorities.push({
            label: 'Pagamentos em atraso',
            value: currency(data.finance.overdue_payables),
            href: financialReportsIndex().url,
            overdue: true,
        });
    }

    return (
        <div className="overview-layout">
            <aside
                className="overview-priorities xl:col-start-2 xl:row-start-1"
                aria-labelledby="priorities-title"
            >
                <div className="overview-priorities-header">
                    <CircleAlert className="size-5 opacity-70" />
                    <h2
                        id="priorities-title"
                        className="overview-priorities-title"
                    >
                        Precisa de atenção
                    </h2>
                    <span className="rounded-full bg-white/10 px-2.5 py-1 text-sm font-medium tabular-nums">
                        {priorities.length}
                    </span>
                </div>
                {priorities.length ? (
                    <ul className="overview-priorities-list">
                        {priorities.map((item) => (
                            <li key={item.label}>
                                <Link
                                    href={item.href}
                                    className="group flex items-start gap-3 rounded-md py-7 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <span
                                        className={cn(
                                            'mt-1.5 size-1.5 shrink-0 rounded-full',
                                            item.overdue
                                                ? 'bg-destructive'
                                                : 'bg-warning',
                                        )}
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-base leading-6">
                                            {item.label}
                                        </span>
                                        <span className="mt-3 block text-xl font-medium tabular-nums">
                                            {item.value}
                                        </span>
                                    </span>
                                    <ArrowRight className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="px-5 py-8">
                        <Check className="mb-4 size-6 text-success" />
                        <p className="text-sm font-medium">
                            Tudo em dia por aqui
                        </p>
                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                            As pendências da operação aparecem neste espaço.
                        </p>
                    </div>
                )}
                <div className="border-t px-7 py-5">
                    <p className="text-xs leading-5 text-muted-foreground">
                        Acesse um item para conferir os detalhes e continuar de
                        onde parou.
                    </p>
                </div>
            </aside>
            <div className="min-w-0 xl:col-start-1 xl:row-start-1">
                {data.sales && (
                    <OverviewSection
                        title="Comercial"
                        description="Campanhas, orçamentos e vendas do mês."
                        href={salesIndex().url}
                        icon={ShoppingBag}
                        items={[
                            {
                                label: 'Campanhas ativas',
                                value: String(data.sales.active_campaigns),
                            },
                            {
                                label: 'Orçamentos em espera',
                                value: String(data.sales.awaiting_quotes),
                            },
                            ...(data.sales.month_amount !== undefined
                                ? [
                                      {
                                          label: 'Confirmado no mês',
                                          value: currency(
                                              data.sales.month_amount,
                                          ),
                                      },
                                  ]
                                : []),
                        ]}
                    />
                )}
                {data.playlists && (
                    <OverviewSection
                        title="Playlists"
                        description="Acompanhe as entregas e os espaços ativos."
                        href={playlistsIndex().url}
                        icon={ListMusic}
                        items={[
                            {
                                label: 'Entregas pendentes',
                                value: String(
                                    data.playlists.pending_deliveries,
                                ),
                            },
                            {
                                label: 'Slots ativos',
                                value: String(data.playlists.active_slots),
                            },
                        ]}
                    />
                )}
                {data.finance && (
                    <OverviewSection
                        title="Financeiro"
                        description="Valores em atraso e conciliação."
                        href={financialReportsIndex().url}
                        icon={Wallet}
                        items={[
                            {
                                label: 'A receber em atraso',
                                value: currency(
                                    data.finance.overdue_receivables,
                                ),
                                attention: data.finance.overdue_receivables > 0,
                            },
                            {
                                label: 'A pagar em atraso',
                                value: currency(data.finance.overdue_payables),
                                attention: data.finance.overdue_payables > 0,
                            },
                            {
                                label: 'Conciliações pendentes',
                                value: String(
                                    data.finance.pending_reconciliations,
                                ),
                            },
                        ]}
                    />
                )}
                {!data.sales && !data.playlists && !data.finance && (
                    <p className="py-8 text-sm text-muted-foreground">
                        Os resumos aparecem aqui conforme os módulos disponíveis
                        para sua conta.
                    </p>
                )}
            </div>
        </div>
    );
}

export default function Dashboard() {
    return (
        <>
            <Head title="Visão geral" />
            <ContentHeader>
                <div className="page-heading">
                    <h1 className="overview-title">Visão geral</h1>
                    <p className="page-heading-description">
                        Um olhar sobre o que acontece na sua operação.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        router.reload({ only: ['operationalDashboard'] })
                    }
                >
                    <RefreshCw className="size-3.5" />
                    Atualizar resumo
                </Button>
            </ContentHeader>
            <div className="page-wrapper-lg">
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
    breadcrumbs: [{ title: 'Visão geral', href: dashboard() }],
};
