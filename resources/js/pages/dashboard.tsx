import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    Check,
    CircleDot,
    FolderOpen,
    Layers,
    LayoutGrid,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { dashboard } from '@/actions/App/Http/Controllers/AppController';
import { PageHeader } from '@/components/page-header';
import { TabItem, TabPanel, Tabs, TabsList } from '@/components/ui/fluid/tabs';
import { resolveNavModules } from '@/lib/resolve-nav-items';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

function links(
    items: NavItem[],
    section = '',
): Array<NavItem & { section: string }> {
    return items.flatMap((item) =>
        item.items?.length
            ? links(item.items, item.title)
            : item.href && item.href !== '#'
              ? [{ ...item, section }]
              : [],
    );
}

const panel =
    'rounded-lg border border-border bg-card shadow-xs dark:shadow-none';
const focus =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background';
const toneChip = {
    slate: 'bg-muted text-muted-foreground',
    amber: 'bg-warning-soft text-warning-soft-foreground',
    emerald: 'bg-success-soft text-success-soft-foreground',
} as const;

function Metric({
    label,
    value,
    caption,
    icon: Icon,
    tone = 'slate',
}: {
    label: string;
    value: string | number;
    caption: string;
    icon: LucideIcon;
    tone?: 'slate' | 'amber' | 'emerald';
}) {
    return (
        <section className={cn(panel, 'relative min-h-40 p-3')}>
            <div className="flex items-start justify-between gap-2">
                <h2 className="text-2xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {label}
                </h2>
                <span
                    className={cn(
                        'flex size-7 shrink-0 items-center justify-center rounded-full',
                        toneChip[tone],
                    )}
                >
                    <Icon size={14} />
                </span>
            </div>
            <p className="mt-3 text-xl font-bold tracking-tight text-foreground tabular-nums">
                {value}
            </p>
            <p className="mt-1 text-2xs leading-relaxed text-muted-foreground">
                {caption}
            </p>
        </section>
    );
}

export default function Dashboard() {
    const { auth, nav } = usePage().props;
    const modules = resolveNavModules(nav.modules);
    const [selected, setSelected] = useState('all');
    const [showAllResources, setShowAllResources] = useState(false);
    const visibleModules =
        selected === 'all'
            ? modules
            : modules.filter((module) => module.slug === selected);
    const resources = visibleModules.flatMap((module) =>
        links(module.items).map((item) => ({ ...item, module: module.label })),
    );
    const allResources = modules.flatMap((module) => links(module.items));
    const sections = [
        ...new Set(allResources.map((item) => item.section).filter(Boolean)),
    ];
    const firstName = auth.user?.name?.split(' ')[0] ?? '';

    return (
        <>
            <Head title="Dashboard" />
            <Tabs
                value={selected}
                onValueChange={setSelected}
                className="page-wrapper space-y-5"
            >
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <PageHeader
                        eyebrow={
                            <>
                                Olá,{' '}
                                <span className="font-semibold text-foreground">
                                    {firstName}
                                </span>
                            </>
                        }
                        title="Visão geral"
                        className="min-w-0"
                    />
                    {/* Fluid Tabs: o segmento ativo desliza; o painel abaixo é o conteúdo filtrado. */}
                    <TabsList
                        aria-label="Filtrar recursos por módulo"
                        className="max-w-full self-start overflow-x-auto lg:self-auto"
                    >
                        {[
                            { slug: 'all', label: 'Todos os módulos' },
                            ...modules,
                        ].map((module) => (
                            <TabItem
                                key={module.slug}
                                value={module.slug}
                                label={module.label}
                            />
                        ))}
                    </TabsList>
                </div>

                <TabPanel value={selected} className="space-y-4">
                    <div className="grid gap-3 xl:grid-cols-12">
                        <section className={cn(panel, 'p-3 xl:col-span-6')}>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-2xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Recursos disponíveis
                                    </h2>
                                    <p className="mt-2 flex items-baseline gap-3 text-2xl font-bold text-foreground tabular-nums">
                                        {resources.length}
                                        <span className="text-xs font-medium text-muted-foreground">
                                            {selected === 'all'
                                                ? 'em todos os módulos'
                                                : visibleModules[0]?.label}
                                        </span>
                                    </p>
                                </div>
                                <span className="flex size-9 items-center justify-center rounded-full bg-primary-soft text-primary-soft-foreground">
                                    <LayoutGrid size={18} />
                                </span>
                            </div>
                            <div
                                className="mt-5 flex h-2 overflow-hidden rounded-full bg-muted"
                                aria-hidden="true"
                            >
                                {modules.map((module, index) => (
                                    <span
                                        key={module.slug}
                                        style={{
                                            flex:
                                                links(module.items).length || 1,
                                        }}
                                        className={
                                            [
                                                'bg-chart-1',
                                                'bg-chart-2',
                                                'bg-chart-3',
                                            ][index % 3]
                                        }
                                    />
                                ))}
                            </div>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {modules.map((module) => (
                                    <button
                                        type="button"
                                        key={module.slug}
                                        onClick={() => setSelected(module.slug)}
                                        className={cn(
                                            'flex min-w-0 flex-1 items-center justify-between gap-2 rounded-md border border-border px-2.5 py-2 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground',
                                            focus,
                                        )}
                                    >
                                        <span className="truncate">
                                            {module.label}
                                        </span>
                                        <span className="rounded-full bg-muted px-2 py-0.5 font-mono text-2xs tabular-nums">
                                            {links(module.items).length}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </section>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:col-span-6">
                            <Metric
                                label="Módulos"
                                value={modules.length}
                                caption="Disponíveis na navegação"
                                icon={Layers}
                                tone="amber"
                            />
                            <Metric
                                label="Recursos"
                                value={allResources.length}
                                caption="Páginas e ferramentas"
                                icon={LayoutGrid}
                            />
                            <Metric
                                label="Seções"
                                value={sections.length}
                                caption="Grupos de navegação"
                                icon={FolderOpen}
                            />
                            <Metric
                                label="Sua conta"
                                value={
                                    auth.user?.email_verified_at
                                        ? 'Verificada'
                                        : 'Ativa'
                                }
                                caption="Sessão autenticada"
                                icon={ShieldCheck}
                                tone="emerald"
                            />
                        </div>
                    </div>

                    {allResources.length > 0 && (
                        <nav
                            aria-label="Acesso rápido"
                            className={cn(
                                panel,
                                'grid gap-2 p-3 sm:grid-cols-3 xl:grid-cols-6',
                            )}
                        >
                            {allResources.slice(0, 6).map((item) => {
                                const Icon = item.icon ?? LayoutGrid;

                                return (
                                    <Link
                                        key={`${item.section}-${item.title}`}
                                        href={item.href}
                                        className={cn(
                                            'flex min-w-0 items-center gap-3 rounded-lg border border-transparent bg-muted px-3 py-2.5 text-sm font-semibold text-foreground hover:bg-accent',
                                            focus,
                                        )}
                                    >
                                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-secondary text-muted-foreground">
                                            <Icon size={14} />
                                        </span>
                                        <span className="truncate">
                                            {item.title}
                                        </span>
                                    </Link>
                                );
                            })}
                        </nav>
                    )}

                    <div className="grid items-start gap-3 xl:grid-cols-12">
                        <section
                            className={cn(panel, 'min-w-0 p-3 xl:col-span-7')}
                        >
                            <div className="mb-3 flex items-center gap-2">
                                <span className="flex size-7 items-center justify-center rounded-full border border-border text-muted-foreground">
                                    <LayoutGrid size={13} />
                                </span>
                                <h2 className="text-xs font-semibold text-foreground">
                                    Recursos da plataforma
                                </h2>
                                {resources.length > 6 && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowAllResources(
                                                !showAllResources,
                                            )
                                        }
                                        className={cn(
                                            'ml-auto inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground',
                                            focus,
                                        )}
                                    >
                                        {showAllResources
                                            ? 'Ver menos'
                                            : 'Ver todos'}
                                        <ArrowRight size={12} />
                                    </button>
                                )}
                            </div>
                            <div className="grid grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] gap-3 px-2 py-2 text-2xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <span>Recurso</span>
                                <span>Módulo</span>
                                <span>Acesso</span>
                            </div>
                            <div className="space-y-1">
                                {resources
                                    .slice(0, showAllResources ? undefined : 6)
                                    .map((item) => (
                                        <Link
                                            key={`${item.module}-${item.section}-${item.title}`}
                                            href={item.href}
                                            className={cn(
                                                'grid grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] items-center gap-3 border-b border-separator px-2 py-3 text-xs last:border-b-0 hover:bg-accent',
                                                focus,
                                            )}
                                        >
                                            <span className="flex min-w-0 items-center gap-2 font-semibold text-foreground">
                                                <span className="size-2 shrink-0 rounded-full bg-success" />
                                                <span className="truncate">
                                                    {item.title}
                                                </span>
                                            </span>
                                            <span className="truncate text-muted-foreground">
                                                {item.section || item.module}
                                            </span>
                                            <span className="flex items-center gap-2 text-muted-foreground">
                                                <span className="hidden sm:inline">
                                                    Disponível
                                                </span>
                                                <ArrowUpRight size={12} />
                                            </span>
                                        </Link>
                                    ))}
                            </div>
                            {resources.length === 0 && (
                                <p className="py-10 text-center text-xs text-muted-foreground">
                                    Nenhum recurso disponível neste módulo.
                                </p>
                            )}
                        </section>
                        <div className="space-y-3 xl:col-span-5">
                            <section className={cn(panel, 'p-3')}>
                                <div className="mb-3 flex items-center gap-2">
                                    <span className="flex size-7 items-center justify-center rounded-full border border-border text-muted-foreground">
                                        <Layers size={13} />
                                    </span>
                                    <h2 className="text-xs font-semibold text-foreground">
                                        Módulos da plataforma
                                    </h2>
                                </div>
                                <div className="space-y-1">
                                    {modules.map((module) => {
                                        const Icon = module.icon ?? Layers;

                                        return (
                                            <button
                                                type="button"
                                                key={module.slug}
                                                onClick={() =>
                                                    setSelected(module.slug)
                                                }
                                                className={cn(
                                                    'flex w-full items-center gap-3 border-b border-separator px-2 py-3 text-left last:border-b-0 hover:bg-accent',
                                                    focus,
                                                )}
                                            >
                                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                                    <Icon size={14} />
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-xs font-semibold text-foreground">
                                                        {module.label}
                                                    </span>
                                                    <span className="text-2xs text-muted-foreground">
                                                        {
                                                            links(module.items)
                                                                .length
                                                        }{' '}
                                                        recursos disponíveis
                                                    </span>
                                                </span>
                                                <ArrowRight
                                                    size={12}
                                                    className="text-muted-foreground"
                                                />
                                            </button>
                                        );
                                    })}
                                </div>
                            </section>
                            <section className={cn(panel, 'p-3')}>
                                <div className="mb-3 flex items-center gap-2">
                                    <span className="flex size-7 items-center justify-center rounded-full border border-border text-muted-foreground">
                                        <UserRound size={13} />
                                    </span>
                                    <h2 className="text-xs font-semibold text-foreground">
                                        Sua conta
                                    </h2>
                                </div>
                                <div className="space-y-1">
                                    {[
                                        {
                                            label: auth.user?.name ?? '',
                                            caption: 'Nome da conta',
                                            icon: UserRound,
                                        },
                                        {
                                            label: auth.user?.email ?? '',
                                            caption: 'E-mail',
                                            icon: CircleDot,
                                        },
                                        {
                                            label: auth.user?.email_verified_at
                                                ? 'E-mail verificado'
                                                : 'E-mail não verificado',
                                            caption: 'Verificação da conta',
                                            icon: ShieldCheck,
                                        },
                                    ].map((detail) => (
                                        <div
                                            key={detail.caption}
                                            className="flex items-center gap-3 border-b border-separator px-2 py-3 last:border-b-0"
                                        >
                                            <detail.icon
                                                size={14}
                                                className="shrink-0 text-success"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-semibold text-foreground">
                                                    {detail.label}
                                                </p>
                                                <p className="text-2xs text-muted-foreground">
                                                    {detail.caption}
                                                </p>
                                            </div>
                                            <Check
                                                size={14}
                                                className="shrink-0 text-muted-foreground"
                                            />
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>
                    </div>
                </TabPanel>
            </Tabs>
        </>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] };
