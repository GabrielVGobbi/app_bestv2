import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    CheckCircle2,
    Disc3,
    Layers3,
    Music2,
    Sparkles,
} from 'lucide-react';
import type { ElementType } from 'react';

import { dashboard } from '@/actions/App/Http/Controllers/AppController';
import { resolveNavItems, resolveNavModules } from '@/lib/resolve-nav-items';
import type { NavItem, NavModule } from '@/types';

type DashboardModule = NavModule & {
    href: string;
};

function findFirstLink(items: NavItem[]): NavItem | null {
    for (const item of items) {
        if (item.href && item.href !== '#') {
            return item;
        }

        if (item.items?.length) {
            const child = findFirstLink(item.items);

            if (child) {
                return child;
            }
        }
    }

    return null;
}

function flattenLinks(items: NavItem[]): NavItem[] {
    return items.flatMap((item) => {
        const children = item.items?.length ? flattenLinks(item.items) : [];
        const current = item.href && item.href !== '#' ? [item] : [];

        return [...current, ...children];
    });
}

function resolveDashboardModules(modules: NavModule[]): DashboardModule[] {
    return modules.flatMap((module) => {
        const firstLink = findFirstLink(module.items);
        const href = module.defaultHref ?? module.href ?? firstLink?.href;

        if (typeof href !== 'string' || href === '#') {
            return [];
        }

        return [{ ...module, href }];
    });
}

function EqualizerMark() {
    return (
        <div className="flex h-16 items-end gap-1.5" aria-hidden="true">
            <span className="h-6 w-2 animate-pulse rounded-full bg-white/45" />
            <span className="h-11 w-2 animate-pulse rounded-full bg-white/70 [animation-delay:120ms]" />
            <span className="h-16 w-2 animate-pulse rounded-full bg-white [animation-delay:240ms]" />
            <span className="h-9 w-2 animate-pulse rounded-full bg-white/70 [animation-delay:360ms]" />
            <span className="h-13 w-2 animate-pulse rounded-full bg-white/50 [animation-delay:480ms]" />
        </div>
    );
}

function ModuleCard({ module }: { module: DashboardModule }) {
    const Icon = module.icon ?? Disc3;
    const visibleLinks = flattenLinks(module.items).length;

    return (
        <Link
            href={module.href}
            prefetch
            className="group relative overflow-hidden rounded-xl border border-border/80 bg-card p-5 shadow-[var(--shadow-app-panel)] transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-1 hover:border-primary/25 hover:shadow-[var(--shadow-app-floating)] focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:outline-none"
        >
            <div className="absolute -top-16 -right-12 size-32 rounded-full bg-primary/6 blur-2xl transition-colors group-hover:bg-primary/12" />

            <div className="relative flex items-start justify-between gap-4">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-primary/15 bg-brand-surface text-primary shadow-sm">
                    <Icon className="size-5" />
                </span>
                <ArrowUpRight className="size-4 text-muted-foreground transition-[color,transform] group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary" />
            </div>

            <div className="relative mt-7">
                <h2 className="text-base font-semibold tracking-tight text-foreground">
                    {module.label}
                </h2>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    {visibleLinks > 0
                        ? `${visibleLinks} ${visibleLinks === 1 ? 'área disponível' : 'áreas disponíveis'}`
                        : 'Acesso direto ao módulo'}
                </p>
            </div>
        </Link>
    );
}

function StatusItem({
    icon: Icon,
    label,
    value,
}: {
    icon: ElementType;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border border-border/70 bg-background/70 px-3 py-3">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-brand-surface text-primary">
                <Icon className="size-4" />
            </span>
            <div className="min-w-0">
                <p className="text-[11px] font-medium text-muted-foreground">
                    {label}
                </p>
                <p className="truncate text-sm font-semibold text-foreground">
                    {value}
                </p>
            </div>
        </div>
    );
}

export default function Dashboard() {
    const { auth, nav } = usePage().props;
    const modules = resolveDashboardModules(resolveNavModules(nav.modules));
    const globalLinks = flattenLinks(resolveNavItems(nav.global));
    const moduleLinks = modules.flatMap((module) => flattenLinks(module.items));
    const quickLinks = [...globalLinks, ...moduleLinks]
        .filter(
            (item, index, links) =>
                links.findIndex((candidate) => candidate.href === item.href) ===
                index,
        )
        .slice(0, 6);
    const firstName = auth.user?.name?.split(' ')[0] ?? 'time';

    return (
        <>
            <Head title="Dashboard" />

            <div className="page-wrapper space-y-6 pb-8">
                <section className="relative overflow-hidden rounded-2xl bg-sidebar px-6 py-7 text-sidebar-foreground shadow-[var(--shadow-app-floating)] md:px-8 md:py-9">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_18%_10%,color-mix(in_oklab,var(--primary)_32%,transparent),transparent_32rem)]" />
                    <div className="absolute top-0 right-0 h-full w-2/5 [background-image:repeating-linear-gradient(115deg,transparent_0_14px,white_14px_15px)] opacity-15" />

                    <div className="relative grid items-center gap-8 lg:grid-cols-[minmax(0,1fr)_auto]">
                        <div className="max-w-2xl">
                            <div className="mb-5 flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] text-white/60 uppercase">
                                <Music2 className="size-4 text-primary" />
                                Central de operações
                            </div>
                            <h1 className="text-2xl font-semibold tracking-[-0.035em] text-balance text-white md:text-3xl">
                                Olá, {firstName}. Tudo em um só ritmo.
                            </h1>
                            <p className="mt-3 max-w-xl text-sm leading-6 text-white/65 md:text-[15px]">
                                Acesse vendas, playlists, cadastros e rotinas da
                                BestPlay a partir de uma visão única e
                                organizada.
                            </p>
                        </div>

                        <div className="hidden items-center gap-6 border-l border-white/10 pl-8 md:flex">
                            <EqualizerMark />
                            <div>
                                <p className="text-3xl font-semibold text-white tabular-nums">
                                    {modules.length}
                                </p>
                                <p className="mt-1 text-xs text-white/55">
                                    módulos disponíveis
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <section>
                        <div className="mb-4 flex items-end justify-between gap-4">
                            <div>
                                <p className="text-[11px] font-semibold tracking-[0.14em] text-primary uppercase">
                                    Navegação
                                </p>
                                <h2 className="mt-1 text-xl font-semibold tracking-tight text-foreground">
                                    Módulos do sistema
                                </h2>
                            </div>
                            <span className="hidden text-xs text-muted-foreground sm:block">
                                Selecione uma área para começar
                            </span>
                        </div>

                        {modules.length > 0 ? (
                            <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                                {modules.map((module) => (
                                    <ModuleCard
                                        key={module.slug}
                                        module={module}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed border-border bg-card/60 px-6 py-12 text-center">
                                <Layers3 className="mx-auto size-7 text-muted-foreground" />
                                <p className="mt-3 text-sm font-medium text-foreground">
                                    Nenhum módulo disponível
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Solicite ao administrador a liberação dos
                                    acessos.
                                </p>
                            </div>
                        )}
                    </section>

                    <aside className="space-y-4">
                        <section className="rounded-xl border border-border/80 bg-card p-5 shadow-[var(--shadow-app-panel)]">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-[11px] font-semibold tracking-[0.12em] text-primary uppercase">
                                        Acesso rápido
                                    </p>
                                    <h2 className="mt-1 text-base font-semibold text-foreground">
                                        Rotas principais
                                    </h2>
                                </div>
                                <Sparkles className="size-4 text-primary" />
                            </div>

                            <div className="mt-4 space-y-1">
                                {quickLinks.map((item) => {
                                    const Icon = item.icon ?? Disc3;

                                    return (
                                        <Link
                                            key={`${item.title}-${String(item.href)}`}
                                            href={item.href}
                                            prefetch
                                            className="group flex items-center gap-3 rounded-lg px-2.5 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-brand-surface hover:text-foreground"
                                        >
                                            <Icon className="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                                            <span className="min-w-0 flex-1 truncate font-medium">
                                                {item.title}
                                            </span>
                                            <ArrowUpRight className="size-3.5 shrink-0 opacity-0 transition-opacity group-hover:opacity-100" />
                                        </Link>
                                    );
                                })}
                            </div>
                        </section>

                        <section className="rounded-xl border border-border/80 bg-card p-4 shadow-[var(--shadow-app-panel)]">
                            <div className="mb-3 flex items-center gap-2 px-1">
                                <CheckCircle2 className="size-4 text-success" />
                                <h2 className="text-sm font-semibold text-foreground">
                                    Ambiente pronto
                                </h2>
                            </div>
                            <div className="space-y-2">
                                <StatusItem
                                    icon={Layers3}
                                    label="Módulos liberados"
                                    value={String(modules.length)}
                                />
                                <StatusItem
                                    icon={Music2}
                                    label="Workspace"
                                    value="BestPlay"
                                />
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
