import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronDown,
    PanelLeftClose,
    PanelLeftOpen,
    Settings2,
} from 'lucide-react';
import { useId, useState } from 'react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useNavModule } from '@/contexts/nav-module-context';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { resolveNavItems } from '@/lib/resolve-nav-items';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes/app';
import { index as financialAgenda } from '@/routes/financial-entries';
import { index as payableAgenda } from '@/routes/financial-installments/payable';
import { index as receivableAgenda } from '@/routes/financial-installments/receivable';
import { edit as profile } from '@/routes/profile';
import type { NavItem, NavModule } from '@/types';

type Props = {
    isCollapsed: boolean;
    onToggle: () => void;
    mobileOpen?: boolean;
    onMobileClose?: () => void;
};

function firstDestination(items: NavItem[]): NavItem['href'] | undefined {
    for (const item of items) {
        if (item.href && item.href !== '#') {
            return item.href;
        }

        const child = firstDestination(item.items ?? []);

        if (child) {
            return child;
        }
    }
}

function moduleDestination(module: NavModule): NavItem['href'] | undefined {
    return module.defaultHref ?? module.href ?? firstDestination(module.items);
}

function moduleNavigationItems(module: NavModule): NavItem[] {
    if (module.items.length > 0) {
        return module.items;
    }

    const href = moduleDestination(module);

    return href ? [{ title: module.label, href, icon: module.icon }] : [];
}

function NavigationItem({
    item,
    activeHref,
    nested = false,
}: {
    item: NavItem;
    activeHref?: string;
    nested?: boolean;
}) {
    const [expanded, setExpanded] = useState(true);
    const Icon = item.icon;
    const subtreeId = useId();
    const label =
        item.title === 'Lançamentos Financeiros' ? 'Lançamentos' : item.title;
    const itemClassName = cn(
        'workspace-nav-item',
        nested && 'workspace-nav-subitem',
    );

    if (item.items?.length) {
        const isSection = item.type === 'section';

        return (
            <div
                className={
                    isSection ? 'workspace-nav-group' : 'workspace-nav-branch'
                }
            >
                {isSection ? (
                    <p className="workspace-nav-label">{label}</p>
                ) : (
                    <div className="relative">
                        {item.href && item.href !== '#' ? (
                            <Link
                                href={item.href}
                                prefetch
                                className={cn(itemClassName, 'pr-8')}
                                aria-current={
                                    toUrl(item.href) === activeHref
                                        ? 'page'
                                        : undefined
                                }
                            >
                                {Icon && !nested && (
                                    <Icon className="size-4 shrink-0" />
                                )}
                                <span
                                    className="min-w-0 flex-1 truncate"
                                    title={item.title}
                                >
                                    {label}
                                </span>
                            </Link>
                        ) : (
                            <span className={cn(itemClassName, 'pr-8')}>
                                {Icon && !nested && (
                                    <Icon className="size-4 shrink-0" />
                                )}
                                <span>{label}</span>
                            </span>
                        )}
                        <button
                            type="button"
                            className="absolute top-1/2 right-1 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-secondary focus-visible:outline-2 focus-visible:outline-ring"
                            aria-label={
                                (expanded ? 'Recolher ' : 'Expandir ') +
                                item.title
                            }
                            aria-expanded={expanded}
                            aria-controls={subtreeId}
                            onClick={() => setExpanded(!expanded)}
                        >
                            <ChevronDown
                                className={cn(
                                    'size-3.5 transition-transform',
                                    !expanded && '-rotate-90',
                                )}
                            />
                        </button>
                    </div>
                )}
                {(isSection || expanded) && (
                    <ul
                        id={subtreeId}
                        className={
                            isSection ? 'space-y-0.5' : 'workspace-nav-sublist'
                        }
                    >
                        {item.items.map((child) => (
                            <li key={child.title}>
                                <NavigationItem
                                    item={child}
                                    activeHref={activeHref}
                                    nested={!isSection || nested}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        );
    }

    if (!item.href || item.href === '#') {
        return null;
    }

    const href = toUrl(item.href);
    const isExternal = /^https?:\/\//.test(href);
    const active = !isExternal && href === activeHref;
    const content = (
        <>
            {Icon && !nested && (
                <Icon
                    className={cn(
                        'size-4 shrink-0',
                        item.tone === 'inflow' && 'text-inflow',
                        item.tone === 'outflow' && 'text-outflow',
                    )}
                />
            )}
            <span className="min-w-0 flex-1 truncate" title={item.title}>
                {label}
            </span>
            {isExternal && (
                <ArrowUpRight className="size-3.5 text-muted-foreground" />
            )}
        </>
    );

    return isExternal ? (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className={itemClassName}
        >
            {content}
        </a>
    ) : (
        <Link
            href={item.href}
            prefetch
            className={itemClassName}
            aria-current={active ? 'page' : undefined}
        >
            {content}
        </Link>
    );
}

function ModuleRail({
    isCollapsed,
    onExpand,
}: {
    isCollapsed: boolean;
    onExpand: () => void;
}) {
    const { modules, activeModule, selectModule } = useNavModule();

    return (
        <nav aria-label="Módulos" className="workspace-module-rail">
            <Link
                href={dashboard()}
                prefetch
                className="mb-3 flex size-10 items-center justify-center text-white"
                aria-label="BestPlay — visão geral"
            >
                <AppLogo showText={false} />
            </Link>
            <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto">
                {modules.map((module) => {
                    const destination = moduleDestination(module);
                    const Icon = module.icon;
                    const active = activeModule?.slug === module.slug;
                    const content = (
                        <>
                            {Icon && (
                                <Icon className="size-5" strokeWidth={1.5} />
                            )}
                            <span className="sr-only">{module.label}</span>
                        </>
                    );

                    return (
                        <Tooltip key={module.slug}>
                            <TooltipTrigger asChild>
                                {destination ? (
                                    <Link
                                        href={destination}
                                        prefetch
                                        className="workspace-module-button"
                                        data-active={active}
                                        aria-label={module.label}
                                        aria-current={
                                            active ? 'true' : undefined
                                        }
                                    >
                                        {content}
                                    </Link>
                                ) : (
                                    <button
                                        type="button"
                                        className="workspace-module-button"
                                        data-active={active}
                                        aria-label={module.label}
                                        onClick={() => {
                                            selectModule(module.slug);

                                            if (isCollapsed) {
                                                onExpand();
                                            }
                                        }}
                                    >
                                        {content}
                                    </button>
                                )}
                            </TooltipTrigger>
                            <TooltipContent side="right" sideOffset={14}>
                                {module.label}
                            </TooltipContent>
                        </Tooltip>
                    );
                })}
            </div>
            <div className="mt-5 flex flex-col gap-3 border-t border-white/15 pt-4">
                {isCollapsed && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                className="workspace-module-button"
                                onClick={onExpand}
                                aria-label="Expandir menu"
                            >
                                <PanelLeftOpen className="size-5" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="right">
                            Expandir menu
                        </TooltipContent>
                    </Tooltip>
                )}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Link
                            href={profile()}
                            className="workspace-module-button"
                            aria-label="Configurações"
                        >
                            <Settings2 className="size-5" />
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent side="right">Configurações</TooltipContent>
                </Tooltip>
            </div>
        </nav>
    );
}

function findActiveHref(
    items: NavItem[],
    currentPath: string,
): string | undefined {
    const candidates: string[] = [];

    for (const item of items) {
        const href = toUrl(item.href);

        if (href.startsWith('/')) {
            const path = href.split('?')[0];

            if (currentPath === path || currentPath.startsWith(path + '/')) {
                candidates.push(href);
            }
        }

        const child = findActiveHref(item.items ?? [], currentPath);

        if (child) {
            candidates.push(child);
        }
    }

    return candidates.sort((a, b) => b.length - a.length)[0];
}

function ContextNavigation({
    onCollapse,
    mobile = false,
}: {
    onCollapse: () => void;
    mobile?: boolean;
}) {
    const page = usePage();
    const { nav, auth } = page.props;
    const { activeModule, modules } = useNavModule();
    const globalItems = resolveNavItems(nav.global);
    const { currentUrl } = useCurrentUrl();
    const navigationItems =
        nav.style === 'flat'
            ? modules.flatMap(moduleNavigationItems)
            : activeModule
              ? moduleNavigationItems(activeModule)
              : [];
    const direction = new URLSearchParams(page.url.split('?')[1]).get(
        'direction',
    );
    const activePath =
        currentUrl === financialAgenda.url()
            ? direction === 'receivable'
                ? receivableAgenda.url()
                : direction === 'payable'
                  ? payableAgenda.url()
                  : currentUrl
            : currentUrl;
    const activeHref = findActiveHref(
        [...globalItems, ...navigationItems],
        activePath,
    );

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex h-16 shrink-0 items-center justify-between gap-2 px-4">
                <Link
                    href={dashboard()}
                    className="flex items-center gap-2 font-sans text-lg font-semibold tracking-tight"
                    aria-label="BestPlay"
                >
                    BestPlay
                    <span className="size-1.5 rounded-full bg-brand-pink" />
                </Link>
                {!mobile && (
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={onCollapse}
                        aria-label="Recolher menu"
                    >
                        <PanelLeftClose className="size-4" />
                    </Button>
                )}
            </div>
            <nav
                aria-label={activeModule?.label ?? 'Navegação'}
                className="min-h-0 flex-1 overflow-y-auto px-3 pb-4"
            >
                <p className="px-3 pt-1 pb-2 text-xs font-medium text-muted-foreground">
                    {nav.style === 'flat'
                        ? 'Navegação'
                        : (activeModule?.label ?? 'Seu espaço')}
                </p>
                <div className="space-y-1">
                    {globalItems.map((item) => (
                        <NavigationItem
                            key={item.title}
                            item={item}
                            activeHref={activeHref}
                        />
                    ))}
                    {nav.style === 'flat'
                        ? modules.map((module) => (
                              <div
                                  key={module.slug}
                                  className="workspace-nav-group"
                              >
                                  <p className="workspace-nav-label">
                                      {module.label}
                                  </p>
                                  {moduleNavigationItems(module).map((item) => (
                                      <NavigationItem
                                          key={item.title}
                                          item={item}
                                          activeHref={activeHref}
                                      />
                                  ))}
                              </div>
                          ))
                        : navigationItems.map((item) => (
                              <NavigationItem
                                  key={item.title}
                                  item={item}
                                  activeHref={activeHref}
                              />
                          ))}
                </div>
            </nav>
            <div className="mx-5 flex items-center gap-2 border-t py-4 text-xs text-muted-foreground">
                <span className="size-1.5 shrink-0 rounded-full bg-brand-pink" />
                <span className="truncate">{auth.user?.name}</span>
                <span className="ml-auto shrink-0">BestPlay</span>
            </div>
        </div>
    );
}

export function DashboardSidebar({
    isCollapsed,
    onToggle,
    mobileOpen = false,
    onMobileClose,
}: Props) {
    return (
        <>
            <aside className="hidden shrink-0 md:flex">
                <ModuleRail isCollapsed={isCollapsed} onExpand={onToggle} />
                {!isCollapsed && (
                    <div className="flex w-[228px] flex-col">
                        <ContextNavigation onCollapse={onToggle} />
                    </div>
                )}
            </aside>
            <Sheet
                open={mobileOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        onMobileClose?.();
                    }
                }}
            >
                <SheetContent
                    side="left"
                    className="w-[min(360px,calc(100vw-24px))] flex-row gap-0 border-0 bg-sidebar p-0"
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        document
                            .getElementById('mobile-navigation-trigger')
                            ?.focus();
                    }}
                >
                    <SheetTitle className="sr-only">
                        Navegação BestPlay
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        Escolha um módulo e acesse suas páginas.
                    </SheetDescription>
                    <div
                        className="flex min-h-0 w-full"
                        onClickCapture={(event) => {
                            if (
                                (event.target as HTMLElement).closest('a[href]')
                            ) {
                                onMobileClose?.();
                            }
                        }}
                    >
                        <ModuleRail isCollapsed={false} onExpand={onToggle} />
                        <ContextNavigation mobile onCollapse={onToggle} />
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
