import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { MouseEvent } from 'react';
import { NavModuleItems } from '@/components/nav-module-items';
import { NavModuleSwitcher } from '@/components/nav-module-switcher';
import { useNavModule } from '@/contexts/nav-module-context';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { resolveNavItems, resolveNavModules } from '@/lib/resolve-nav-items';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

// Tooltip class shared across all sidebar tooltips.
// - delay-200: prevents tooltip from appearing during the 200ms collapse animation.
// - group-hover:delay-500: tooltip appears 500ms after hover (avoids flash on quick moves).
const TOOLTIP_CLS =
    'pointer-events-none absolute left-full z-50 ml-2 translate-x-2 rounded-md bg-foreground px-2 py-1 text-[10px] font-semibold whitespace-nowrap text-background opacity-0 shadow-xl transition-[opacity,transform] duration-150 delay-200 group-hover:translate-x-0 group-hover:opacity-100 group-hover:delay-500';

const SECTION_LABEL_CLS =
    'px-3 pb-2 text-xs font-normal text-sidebar-foreground/45';

const NAV_ITEM_BASE =
    'group relative flex w-full items-center rounded-lg px-3 py-2 text-[13px] font-medium transition-colors';

const navItemState = (isActive: boolean) =>
    isActive
        ? 'bg-sidebar-accent text-sidebar-accent-foreground before:absolute before:inset-y-1.5 before:left-0 before:w-[3px] before:rounded-full before:bg-primary'
        : 'text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground';

type Props = {
    isCollapsed: boolean;
    onToggle: () => void;
    mobileOpen?: boolean;
    onMobileClose?: () => void;
};

// ---------------------------------------------------------------------------
// Module Switcher — lista de módulos (tela inicial)
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Module Items View — itens do módulo ativo com cabeçalho + back
// ---------------------------------------------------------------------------

function ModuleItemsView() {
    const { activeModule } = useNavModule();

    return (
        <div className="space-y-3">
            {/* Itens do módulo — key isola o re-mount por módulo sem arrastar o header */}
            <div
                key={activeModule.slug}
                className="animate-in duration-100 fade-in"
            >
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
                    <div className={SECTION_LABEL_CLS}>{item.title}</div>
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
        NAV_ITEM_BASE,
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
                            : 'text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground',
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

    const cls = cn(sharedClass, navItemState(isActive));

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
    const modules = useMemo(
        () => resolveNavModules(nav.modules),
        [nav.modules],
    );

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
                            <p className={cn(SECTION_LABEL_CLS, 'mb-1')}>
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
        return <ModuleItemsView />;
    }

    return sidebarView === 'modules' ? (
        <div
            key="modules"
            className="animate-in duration-150 fade-in slide-in-from-bottom-1"
        >
            <NavModuleSwitcher isCollapsed={isCollapsed} />
        </div>
    ) : (
        <ModuleItemsView />
    );
}

// ---------------------------------------------------------------------------
// DashboardSidebar
// ---------------------------------------------------------------------------

export function DashboardSidebar({
    isCollapsed,
    onToggle,
    mobileOpen = false,
    onMobileClose,
}: Props) {
    const { nav } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();

    // Memoize resolved items — nav props are stable within a page and only
    // change on Inertia navigations, so useMemo avoids re-computing every render.
    const globalItems = useMemo(
        () => resolveNavItems(nav.global),
        [nav.global],
    );

    useEffect(() => {
        if (!mobileOpen) {
            return;
        }

        const previousOverflow = document.body.style.overflow;
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onMobileClose?.();
                document.getElementById('mobile-navigation-trigger')?.focus();
            }
        };

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [mobileOpen, onMobileClose]);
    const closeMobileOnNavClick = (event: MouseEvent<HTMLElement>) => {
        if (!mobileOpen) {
            return;
        }

        if ((event.target as HTMLElement).closest('a[href]')) {
            onMobileClose?.();
        }
    };

    return (
        <>
            <div
                className={cn(
                    'fixed inset-0 z-40 bg-black/50 backdrop-blur-sm transition-opacity duration-300 md:hidden',
                    mobileOpen
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0',
                )}
                onClick={onMobileClose}
            />

            <aside
                className={cn(
                    'fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-sidebar-border bg-sidebar shadow-lg select-none',
                    'transition-transform duration-300 ease-out',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                    'md:static md:z-auto md:shrink-0 md:translate-x-0 md:transition-[width] md:duration-200',
                    isCollapsed ? 'md:w-16' : 'md:w-[250px]',
                )}
            >
                {/* Toggle */}
                <button
                    type="button"
                    onClick={onToggle}
                    className="absolute top-1/2 -right-3 z-10 hidden h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full border border-sidebar-border bg-sidebar text-sidebar-foreground shadow-md transition-[background,color,transform] hover:bg-sidebar-accent hover:text-sidebar-accent-foreground md:flex"
                    aria-label={isCollapsed ? 'Expandir menu' : 'Recolher menu'}
                >
                    <ChevronLeft
                        size={15}
                        className={cn(
                            'transition-transform duration-200 ease-out',
                            isCollapsed && 'rotate-180',
                        )}
                    />
                </button>

                <nav
                    className="flex h-full flex-col px-3 py-5"
                    id="nav-sidebar"
                    onClickCapture={closeMobileOnNavClick}
                >
                    {/* Conteúdo principal — scrollável */}
                    <div className="min-h-0 sidebar-scrollbar flex-1 space-y-2 overflow-x-hidden overflow-y-auto pr-1">
                        {/* Itens globais — sempre visíveis acima dos módulos */}
                        {globalItems.length > 0 && (
                            <div className="space-y-0.5">
                                {globalItems.map((item) => (
                                    <Link
                                        key={item.title}
                                        href={item.href as string}
                                        prefetch
                                        className={cn(
                                            NAV_ITEM_BASE,
                                            isCollapsed
                                                ? 'justify-center p-2'
                                                : 'gap-2.5',
                                            navItemState(
                                                isCurrentUrl(
                                                    item.href as string,
                                                ),
                                            ),
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
                                        <div
                                            className={cn(
                                                TOOLTIP_CLS,
                                                !isCollapsed && 'hidden',
                                            )}
                                        >
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

                </nav>
            </aside>
        </>
    );
}
