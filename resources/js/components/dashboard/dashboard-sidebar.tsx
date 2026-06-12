import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import AppLogo from '@/components/app-logo';
import { NavModuleItems } from '@/components/nav-module-items';
import { NavModuleSwitcher } from '@/components/nav-module-switcher';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useNavModule } from '@/contexts/nav-module-context';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { resolveNavItems, resolveNavModules } from '@/lib/resolve-nav-items';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes/app';
import type { NavItem } from '@/types';

// Tooltip class shared across all sidebar tooltips.
// - delay-200: prevents tooltip from appearing during the 200ms collapse animation.
// - group-hover:delay-500: tooltip appears 500ms after hover (avoids flash on quick moves).
const TOOLTIP_CLS =
    'pointer-events-none absolute left-full z-50 ml-2 translate-x-2 rounded-md bg-foreground px-2 py-1 text-[10px] font-bold whitespace-nowrap text-background opacity-0 shadow-xl transition-[opacity,transform] duration-150 delay-200 group-hover:translate-x-0 group-hover:opacity-100 group-hover:delay-500';

type Props = {
    showLogo?: boolean;
};

// ---------------------------------------------------------------------------
// Module Switcher — lista de módulos (tela inicial)
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Module Items View — itens do módulo ativo com cabeçalho + back
// ---------------------------------------------------------------------------

function ModuleItemsView({
    isCollapsed,
    showBack = true,
}: {
    isCollapsed: boolean;
    showBack?: boolean;
}) {
    const { activeModule, showModuleList } = useNavModule();

    return (
        <div className="space-y-3">
            {/* Itens do módulo — key isola o re-mount por módulo sem arrastar o header */}
            <div key={activeModule.slug} className="animate-in duration-100 fade-in">
                <NavModuleItems variant="sidebar" />
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Flat variant — lista única sem módulos
// ---------------------------------------------------------------------------

function FlatNavItem({
    item,
    isCollapsed,
    depth = 0,
}: {
    item: NavItem;
    isCollapsed: boolean;
    depth?: number;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const hasChildren = !!item.items?.length;
    const anyChildActive = (items: NavItem[]): boolean =>
        items.some(
            (c) =>
                (!!c.href && isCurrentUrl(c.href as string)) ||
                (c.items ? anyChildActive(c.items) : false),
        );
    const childActive = hasChildren && anyChildActive(item.items!);
    const [open, setOpen] = useState(childActive);

    if (item.type === 'section') {
        return (
            <div className={cn('space-y-0.5', !isCollapsed && 'pt-3')}>
                {!isCollapsed && (
                    <div className="px-3 pb-1 text-[10px] leading-none font-bold tracking-widest text-sidebar-foreground/45 uppercase">
                        {item.title}
                    </div>
                )}
                {item.items?.map((child) => (
                    <FlatNavItem
                        key={child.title}
                        item={child}
                        isCollapsed={isCollapsed}
                        depth={depth}
                    />
                ))}
            </div>
        );
    }

    if (!item.href && !item.items?.length) {
        return null;
    }

    const sharedClass = cn(
        'group relative flex w-full items-center rounded-full px-3 py-2 text-[13px] font-medium transition-colors',
        isCollapsed ? 'justify-center p-2' : 'gap-2.5',
        depth > 0 && !isCollapsed && 'pl-4',
    );

    if (hasChildren) {
        return (
            <div>
                <button
                    onClick={() => setOpen((v) => !v)}
                    className={cn(
                        sharedClass,
                        childActive
                            ? 'text-sidebar-accent-foreground'
                            : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                    )}
                >
                    {item.icon && <item.icon size={16} className="shrink-0" />}
                    <span
                        className={cn(
                            'overflow-hidden whitespace-nowrap transition-[opacity,max-width] duration-200 ease-out',
                            isCollapsed
                                ? 'max-w-0 opacity-0'
                                : 'max-w-40 opacity-100',
                        )}
                    >
                        {item.title}
                    </span>
                    {!isCollapsed && (
                        <ChevronRight
                            size={12}
                            className={cn(
                                'ml-auto shrink-0 transition-transform duration-200',
                                open && 'rotate-90',
                            )}
                        />
                    )}
                </button>
                {open && !isCollapsed && (
                    <div className="mt-0.5 ml-2 space-y-0.5 border-l border-sidebar-border pl-3">
                        {item.items!.map((child) => (
                            <FlatNavItem
                                key={child.title}
                                item={child}
                                isCollapsed={isCollapsed}
                                depth={depth + 1}
                            />
                        ))}
                    </div>
                )}
            </div>
        );
    }

    const isActive = isCurrentUrl(item.href as string);
    const isExternal =
        typeof item.href === 'string' && item.href.startsWith('http');

    const inner = (
        <>
            {item.icon && <item.icon size={16} className="shrink-0" />}
            <span
                className={cn(
                    'overflow-hidden whitespace-nowrap transition-[opacity,max-width] duration-200 ease-out',
                    isCollapsed ? 'max-w-0 opacity-0' : 'max-w-40 opacity-100',
                )}
            >
                {item.title}
            </span>
            {/* Tooltip colapsado */}
            <div className={cn(TOOLTIP_CLS, !isCollapsed && 'hidden')}>
                {item.title}
            </div>
        </>
    );

    const cls = cn(
        sharedClass,
        isActive
            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
            : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
    );

    if (isExternal) {
        return (
            <a
                href={item.href as string}
                target="_blank"
                rel="noopener noreferrer"
                className={cls}
            >
                {inner}
            </a>
        );
    }

    return (
        <Link href={item.href ?? '#'} prefetch className={cls}>
            {inner}
        </Link>
    );
}

function FlatSidebarContent({ isCollapsed }: { isCollapsed: boolean }) {
    const { nav } = usePage().props;
    const modules = useMemo(() => resolveNavModules(nav.modules), [nav.modules]);

    return (
        <div className="space-y-4">
            {modules.map((m) => {
                // Módulo sem items + com href → link direto sem label de seção
                if (m.items.length === 0 && m.href) {
                    return (
                        <FlatNavItem
                            key={m.slug}
                            item={{
                                title: m.label,
                                href: m.href,
                                icon: m.icon ?? undefined,
                            }}
                            isCollapsed={isCollapsed}
                        />
                    );
                }

                if (m.items.length === 0) {
                    return null;
                }

                return (
                    <div key={m.slug} className="space-y-0.5">
                        {!isCollapsed && (
                            <p className="mb-1 px-3 text-[10px] font-bold tracking-widest text-sidebar-foreground/55 uppercase">
                                {m.label}
                            </p>
                        )}
                        {m.items.map((item) => (
                            <FlatNavItem
                                key={item.title}
                                item={item}
                                isCollapsed={isCollapsed}
                            />
                        ))}
                    </div>
                );
            })}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Modules sidebar content — requer NavModuleProvider na árvore
// ---------------------------------------------------------------------------

function ModulesSidebarContent({ isCollapsed }: { isCollapsed: boolean }) {
    const { sidebarView } = useNavModule();
    const { nav } = usePage().props;

    if (nav.modulesMenuPosition === 'header') {
        return <ModuleItemsView isCollapsed={isCollapsed} showBack={false} />;
    }

    return sidebarView === 'modules' ? (
        <div
            key="modules"
            className="animate-in duration-150 fade-in slide-in-from-bottom-1"
        >
            <NavModuleSwitcher isCollapsed={isCollapsed} />
        </div>
    ) : (
        <ModuleItemsView isCollapsed={isCollapsed} />
    );
}

// ---------------------------------------------------------------------------
// DashboardSidebar
// ---------------------------------------------------------------------------

export function DashboardSidebar({ showLogo = false }: Props) {
    const { auth, nav } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const getInitials = useInitials();

    // Collapse state lives here so toggling doesn't re-render the parent layout
    // (DashboardHeader and page children remain untouched on every toggle).
    const [isCollapsed, setIsCollapsed] = useState(() => {
        try {
            return localStorage.getItem('sidebar:collapsed') === 'true';
        } catch {
            return false;
        }
    });

    const onToggle = useCallback(() => {
        setIsCollapsed((prev) => {
            const next = !prev;
            try {
                localStorage.setItem('sidebar:collapsed', String(next));
            } catch {}
            return next;
        });
    }, []);

    // Memoize resolved items — nav props are stable within a page and only
    // change on Inertia navigations, so useMemo avoids re-computing every render.
    const globalItems = useMemo(() => resolveNavItems(nav.global), [nav.global]);
    const footerItems = useMemo(() => resolveNavItems(nav.footer), [nav.footer]);

    return (
        <aside
            className={cn(
                'relative hidden shrink-0 border-r border-sidebar-border bg-sidebar backdrop-blur-md select-none md:flex md:flex-col',
                'transition-[width] duration-200 ease-out',
                isCollapsed ? 'w-16' : 'w-60',
            )}
        >
            {/* Toggle */}
            <button
                onClick={onToggle}
                className="absolute top-[93%] -right-3 z-10 flex h-6 w-6 items-center justify-center rounded-full border border-sidebar-border bg-sidebar text-sidebar-foreground shadow-md transition-colors hover:bg-sidebar-accent"
            >
                <ChevronLeft
                    size={15}
                    className={cn(
                        'transition-transform duration-200 ease-out',
                        isCollapsed && 'rotate-180',
                    )}
                />
            </button>

            <nav className="flex h-full flex-col px-3 py-5" id="nav-sidebar">
                {showLogo && (
                    <Link
                        href={dashboard()}
                        prefetch
                        className={cn(
                            'mb-5 flex shrink-0 items-center rounded-full px-2 py-1.5 text-sidebar-foreground transition-colors hover:bg-sidebar-accent',
                            isCollapsed && 'justify-center',
                        )}
                    >
                        <AppLogo showText={!isCollapsed} />
                    </Link>
                )}

                {/* Conteúdo principal — scrollável */}
                <div className="min-h-0 flex-1 space-y-2 overflow-x-hidden overflow-y-auto">
                    {/* Itens globais — sempre visíveis acima dos módulos */}
                    {globalItems.length > 0 && (
                        <div className="space-y-0.5">
                            {globalItems.map((item) => (
                                <Link
                                    key={item.title}
                                    href={item.href as string}
                                    prefetch
                                    className={cn(
                                        'group relative flex w-full items-center rounded-full px-3 py-2 text-[13px] font-medium transition-colors',
                                        isCollapsed
                                            ? 'justify-center p-2'
                                            : 'gap-2.5',
                                        isCurrentUrl(item.href as string)
                                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                                            : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                    )}
                                >
                                    {item.icon && (
                                        <item.icon
                                            size={16}
                                            className="shrink-0"
                                        />
                                    )}
                                    <span
                                        className={cn(
                                            'overflow-hidden whitespace-nowrap transition-[opacity,max-width] duration-200 ease-out',
                                            isCollapsed
                                                ? 'max-w-0 opacity-0'
                                                : 'max-w-40 opacity-100',
                                        )}
                                    >
                                        {item.title}
                                    </span>
                                    {/* Tooltip colapsado */}
                                    <div className={cn(TOOLTIP_CLS, !isCollapsed && 'hidden')}>
                                        {item.title}
                                    </div>
                                </Link>
                            ))}
                            <div className="mx-3 border-t border-sidebar-border" />
                        </div>
                    )}

                    {nav.style === 'modules' ? (
                        <ModulesSidebarContent isCollapsed={isCollapsed} />
                    ) : (
                        <FlatSidebarContent isCollapsed={isCollapsed} />
                    )}
                </div>

                {/* Rodapé fixo — footer links + usuário */}
                <div className="shrink-0 space-y-1">
                    {/* Links externos */}
                    {footerItems.length > 0 && !isCollapsed && (
                        <div className="space-y-0.5">
                            <div className="mx-3 border-t border-sidebar-border" />
                            <div className="pt-2">
                                {footerItems.map((item) => (
                                    <a
                                        key={item.title}
                                        href={toUrl(item.href)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex w-full items-center gap-2.5 rounded-full px-3 py-2 text-[13px] font-medium text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                                    >
                                        {item.icon && (
                                            <item.icon
                                                size={15}
                                                className="shrink-0"
                                            />
                                        )}
                                        <span className="truncate">
                                            {item.title}
                                        </span>
                                    </a>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* User section */}
                    <div className="group relative">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    className={cn(
                                        'flex w-full items-center rounded-full px-2 py-2 text-[13px] font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent',
                                        isCollapsed
                                            ? 'justify-center'
                                            : 'gap-2.5',
                                    )}
                                >
                                    <Avatar className="size-7 shrink-0 overflow-hidden rounded-full">
                                        <AvatarImage
                                            src={auth.user?.avatar}
                                            alt={auth.user?.name}
                                        />
                                        <AvatarFallback className="rounded-full bg-runey-orange text-[10px] font-bold text-white">
                                            {getInitials(auth.user?.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div
                                        className={cn(
                                            'overflow-hidden text-left transition-[opacity,max-width] duration-200 ease-out',
                                            isCollapsed
                                                ? 'max-w-0 opacity-0'
                                                : 'max-w-40 opacity-100',
                                        )}
                                    >
                                        <p className="truncate text-[13px] font-semibold text-sidebar-foreground">
                                            {auth.user?.name}
                                        </p>
                                        <p className="truncate text-[11px] text-sidebar-foreground/60">
                                            {auth.user?.email}
                                        </p>
                                    </div>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="w-56"
                                align={isCollapsed ? 'center' : 'start'}
                                side="top"
                            >
                                {auth.user && (
                                    <UserMenuContent user={auth.user} />
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {/* Tooltip usuário colapsado */}
                        <div className={cn(TOOLTIP_CLS, !isCollapsed && 'hidden')}>
                            {auth.user?.name}
                        </div>
                    </div>
                </div>
            </nav>
        </aside>
    );
}
