import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, ChevronsUpDown, Circle } from 'lucide-react';
import { m } from 'motion/react';
import { createContext, useContext, useEffect, useState } from 'react';
import type {
    ComponentProps,
    CSSProperties,
    MouseEvent,
    ReactElement,
    ReactNode,
} from 'react';
import AppLogo from '@/components/app-logo';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownContent,
    DropdownMenu,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import { SIDEBAR_MENU_POPUP } from '@/components/ui/fluid/lib/sidebar-menu-grid';
import { spring } from '@/components/ui/fluid/lib/springs';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/fluid/sidebar';
import { Tooltip } from '@/components/ui/fluid/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useNavModule } from '@/contexts/nav-module-context';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import {
    resetSidebarPreferences,
    setSidebarGroupOpen,
    useSidebarGroupOpen,
} from '@/hooks/use-sidebar-preferences';
import { resolveNavItems, resolveNavModules } from '@/lib/resolve-nav-items';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes/app';
import type { NavItem } from '@/types';

type Props = {
    /** Exibe a logo no topo do sidebar (variante sidebar-logo). */
    showLogo?: boolean;
    className?: string;
};

function isExternal(item: NavItem): boolean {
    return toUrl(item.href).startsWith('http');
}

function hasActiveDescendant(
    items: NavItem[],
    isActive: (href: NavItem['href']) => boolean,
): boolean {
    return items.some(
        (child) =>
            isActive(child.href) ||
            hasActiveDescendant(child.items ?? [], isActive),
    );
}

/** Links de um grupo, achatados: o Fluid tem um único nível de submenu. */
function flattenLinks(items: NavItem[]): NavItem[] {
    return items.flatMap((item) =>
        item.items?.length ? flattenLinks(item.items) : [item],
    );
}

/** Sidebar recolhida no desktop: faixa só de ícones (no drawer mobile, nunca). */
const CompactContext = createContext(false);

// Linha da faixa: ícone centralizado; o rótulo continua para leitores de tela.
const RAIL_ROW = 'justify-center px-0 [&>span]:sr-only';
const RAIL_GUTTER = {
    '--row-gutter': '0px',
    '--row-gutter-hover': '0px',
} as CSSProperties;
const RAIL_SEPARATOR = 'mx-auto my-2 h-px w-5 shrink-0 bg-sidebar-border';

// Linha de 36px (8 + 20 + 8), recuo de 12px e 10px entre ícone e texto.
const ROW = 'h-9 gap-2.5 pl-3';

function useRowProps() {
    return useContext(CompactContext)
        ? { className: cn(ROW, RAIL_ROW), style: RAIL_GUTTER }
        : { className: ROW };
}

/** Grupo no ritmo do shell: 12px nas laterais; o intervalo entre grupos vem do nav. */
function NavGroup({
    className,
    ...props
}: ComponentProps<typeof SidebarGroup>) {
    return <SidebarGroup className={cn('px-3 py-0', className)} {...props} />;
}

/** Na faixa de ícones, o nome da linha aparece num tooltip à direita. */
function RailTooltip({
    title,
    children,
}: {
    title: string;
    children: ReactElement;
}) {
    return useContext(CompactContext) ? (
        <Tooltip content={title} side="right" sideOffset={10}>
            {children}
        </Tooltip>
    ) : (
        children
    );
}

function NavLinkButton({ item }: { item: NavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const compact = useContext(CompactContext);
    const rowProps = useRowProps();
    const external = isExternal(item);

    return (
        <RailTooltip title={item.title}>
            <SidebarMenuButton
                icon={item.icon ?? (compact ? Circle : undefined)}
                isActive={!external && isCurrentOrParentUrl(item.href)}
                {...rowProps}
                render={
                    external ? (
                        <a
                            href={toUrl(item.href)}
                            target="_blank"
                            rel="noopener noreferrer"
                        />
                    ) : (
                        <Link href={item.href} prefetch />
                    )
                }
            >
                {item.title}
            </SidebarMenuButton>
        </RailTooltip>
    );
}

/** Chevron do Fluid: gira 90° aberto e só aparece no hover da própria linha. */
function RowChevron({ open }: { open: boolean }) {
    return (
        <span className="-mr-0.5 ml-auto flex size-6 shrink-0 items-center justify-center">
            <m.span
                className="inline-flex"
                animate={{ rotate: open ? 90 : 0 }}
                transition={spring.fast}
            >
                <ChevronRight
                    size={16}
                    strokeWidth={1.5}
                    aria-hidden="true"
                    className={
                        open
                            ? 'text-muted-foreground opacity-0 transition-opacity duration-80 group-focus-within/parent-row:opacity-100 group-hover/parent-row:opacity-100'
                            : 'text-muted-foreground'
                    }
                />
            </m.span>
        </span>
    );
}

function NavParentRow({ item }: { item: NavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { url } = usePage();
    const children = flattenLinks(item.items ?? []);
    const childActive = hasActiveDescendant(children, isCurrentOrParentUrl);
    // Só o link mais específico acende: o Índice é prefixo das páginas irmãs.
    const activeChild = children
        .filter(
            (child) => !isExternal(child) && isCurrentOrParentUrl(child.href),
        )
        .sort((a, b) => toUrl(b.href).length - toUrl(a.href).length)[0];
    const [expanded, setExpanded] = useState({ url, open: childActive });
    // Uma navegação reabre o grupo que contém a rota ativa.
    const open = expanded.url === url ? expanded.open : childActive;

    // Na faixa, o grupo vira um separador seguido dos links (sem submenu).
    if (useContext(CompactContext)) {
        return (
            <>
                <li
                    role="separator"
                    aria-label={item.title}
                    className={RAIL_SEPARATOR}
                />
                {children.map((child) => (
                    <SidebarMenuItem key={child.title}>
                        <NavLinkButton item={child} />
                    </SidebarMenuItem>
                ))}
            </>
        );
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                className={cn(ROW, 'group/parent-row')}
                icon={item.icon ?? undefined}
                aria-expanded={open}
                onClick={() => setExpanded({ url, open: !open })}
                style={
                    {
                        '--row-gutter': 'var(--row-gutter-hover)',
                    } as CSSProperties
                }
            >
                {item.title}
                <RowChevron open={open} />
            </SidebarMenuButton>
            <SidebarMenuSub open={open} className="gap-0.5 pt-0.5">
                {children.map((child) => (
                    <SidebarMenuSubItem key={child.title}>
                        <SidebarMenuSubButton
                            icon={child.icon ?? undefined}
                            isActive={child === activeChild}
                            render={
                                isExternal(child) ? (
                                    <a
                                        href={toUrl(child.href)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    />
                                ) : (
                                    <Link href={child.href} prefetch />
                                )
                            }
                        >
                            {child.title}
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                ))}
            </SidebarMenuSub>
        </SidebarMenuItem>
    );
}

function NavRows({ items }: { items: NavItem[] }) {
    return (
        <SidebarMenu className="gap-0.5">
            {items.map((item) =>
                item.items?.length ? (
                    <NavParentRow key={item.title} item={item} />
                ) : (
                    <SidebarMenuItem key={item.title}>
                        <NavLinkButton item={item} />
                    </SidebarMenuItem>
                ),
            )}
        </SidebarMenu>
    );
}

/** Sequências de links viram uma lista; seções, um grupo com rótulo. */
function chunkBySection(items: NavItem[]): (NavItem | NavItem[])[] {
    return items.reduce<(NavItem | NavItem[])[]>((chunks, item) => {
        const last = chunks[chunks.length - 1];

        if (item.type === 'section') {
            chunks.push(item);
        } else if (Array.isArray(last)) {
            last.push(item);
        } else {
            chunks.push([item]);
        }

        return chunks;
    }, []);
}

/**
 * Itens de um módulo. No nível raiz cada seção é um grupo recolhível; dentro
 * de um módulo (modo flat) as seções ficam como rótulos estáticos.
 */
function NavSections({
    items,
    nested = false,
}: {
    items: NavItem[];
    nested?: boolean;
}) {
    const compact = useContext(CompactContext);

    return chunkBySection(items).map((chunk) => {
        if (Array.isArray(chunk)) {
            return nested ? (
                <NavRows key={chunk[0].title} items={chunk} />
            ) : (
                <NavGroup key={chunk[0].title}>
                    <NavRows items={chunk} />
                </NavGroup>
            );
        }

        // shortcut: seções aninhadas viram linhas da seção pai; o config atual não aninha.
        const rows = (chunk.items ?? []).flatMap((child) =>
            child.type === 'section' ? (child.items ?? []) : [child],
        );

        // Faixa de ícones: a seção vira separador (no flat, o separador já é do módulo).
        if (compact) {
            return nested ? (
                <NavRows key={chunk.title} items={rows} />
            ) : (
                <NavGroup key={chunk.title}>
                    <div
                        role="separator"
                        aria-label={chunk.title}
                        className={RAIL_SEPARATOR}
                    />
                    <NavRows items={rows} />
                </NavGroup>
            );
        }

        return nested ? (
            <NavGroup key={chunk.title} className="p-0">
                <SidebarGroupLabel>{chunk.title}</SidebarGroupLabel>
                <NavRows items={rows} />
            </NavGroup>
        ) : (
            <CollapsibleSection key={chunk.title} title={chunk.title}>
                <NavRows items={rows} />
            </CollapsibleSection>
        );
    });
}

/** Seção recolhível pelo título; aberta ou fechada fica salva entre recarregamentos. */
function CollapsibleSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const open = useSidebarGroupOpen(title);

    return (
        <NavGroup
            collapsible
            open={open}
            onOpenChange={(next) => setSidebarGroupOpen(title, next)}
        >
            <SidebarGroupLabel>{title}</SidebarGroupLabel>
            {children}
        </NavGroup>
    );
}

// ---------------------------------------------------------------------------
// Modo módulos: requer NavModuleProvider na árvore
// ---------------------------------------------------------------------------

function ModuleSwitcher() {
    const { modules, activeModule, sidebarView, selectModule } = useNavModule();
    const { isCurrentUrl } = useCurrentUrl();
    const compact = useContext(CompactContext);
    const rowProps = useRowProps();

    return (
        <NavGroup>
            {!compact && <SidebarGroupLabel>Módulos</SidebarGroupLabel>}
            <SidebarMenu className="gap-0.5">
                {modules.map((mod) => {
                    // Módulo sem items + com href → link direto (não drill-down)
                    const isLinkModule = mod.items.length === 0 && !!mod.href;
                    const icon = mod.icon ?? (compact ? Circle : undefined);

                    return (
                        <SidebarMenuItem key={mod.slug}>
                            <RailTooltip title={mod.label}>
                                {isLinkModule ? (
                                    <SidebarMenuButton
                                        icon={icon}
                                        isActive={isCurrentUrl(
                                            mod.href as string,
                                        )}
                                        {...rowProps}
                                        render={
                                            <Link
                                                href={mod.href as string}
                                                prefetch
                                            />
                                        }
                                    >
                                        {mod.label}
                                    </SidebarMenuButton>
                                ) : (
                                    <SidebarMenuButton
                                        icon={icon}
                                        isActive={
                                            sidebarView === 'items' &&
                                            mod.slug === activeModule.slug
                                        }
                                        {...rowProps}
                                        onClick={() => selectModule(mod.slug)}
                                    >
                                        {mod.label}
                                    </SidebarMenuButton>
                                )}
                            </RailTooltip>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </NavGroup>
    );
}

function ModulesSidebarContent() {
    const { nav } = usePage().props;
    const { activeModule, sidebarView, showModuleList } = useNavModule();
    const rowProps = useRowProps();
    const items = activeModule?.items ?? [];

    // Com módulos no header, a sidebar mostra direto os itens do módulo ativo.
    if (nav.modulesMenuPosition === 'header') {
        return <NavSections items={items} />;
    }

    if (sidebarView === 'modules') {
        return <ModuleSwitcher />;
    }

    return (
        <>
            <NavGroup>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <RailTooltip title="Voltar aos módulos">
                            <SidebarMenuButton
                                icon={ArrowLeft}
                                aria-label={`Voltar aos módulos (${activeModule.label})`}
                                {...rowProps}
                                onClick={showModuleList}
                            >
                                {activeModule.label}
                            </SidebarMenuButton>
                        </RailTooltip>
                    </SidebarMenuItem>
                </SidebarMenu>
            </NavGroup>
            <NavSections key={activeModule.slug} items={items} />
        </>
    );
}

// ---------------------------------------------------------------------------
// Modo flat: todos os módulos como grupos recolhíveis
// ---------------------------------------------------------------------------

function FlatSidebarContent() {
    const { nav } = usePage().props;
    const compact = useContext(CompactContext);

    return resolveNavModules(nav.modules).map((module) => {
        // Módulo sem items + com href → link direto sem rótulo de grupo
        if (module.items.length === 0 && module.href) {
            return (
                <NavGroup key={module.slug}>
                    <NavRows
                        items={[
                            {
                                title: module.label,
                                href: module.href,
                                icon: module.icon ?? undefined,
                            },
                        ]}
                    />
                </NavGroup>
            );
        }

        if (module.items.length === 0) {
            return null;
        }

        return compact ? (
            <NavGroup key={module.slug}>
                <div
                    role="separator"
                    aria-label={module.label}
                    className={RAIL_SEPARATOR}
                />
                <NavSections items={module.items} nested />
            </NavGroup>
        ) : (
            <NavGroup key={module.slug} collapsible>
                <SidebarGroupLabel>{module.label}</SidebarGroupLabel>
                <NavSections items={module.items} nested />
            </NavGroup>
        );
    });
}

function UserFooter() {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const compact = useContext(CompactContext);

    return (
        <SidebarMenu aria-label="Conta">
            <SidebarMenuItem>
                <DropdownMenu>
                    <RailTooltip title={auth.user?.name ?? 'Conta'}>
                        <DropdownTrigger
                            render={
                                <SidebarMenuButton
                                    aria-label="Abrir menu do usuário"
                                    // Faixa: só o avatar, centralizado.
                                    className={cn(
                                        ROW,
                                        compact &&
                                            'justify-center px-0 [&>span]:justify-center',
                                    )}
                                    style={compact ? RAIL_GUTTER : undefined}
                                >
                                    <Avatar className="-mr-0.5 -ml-0.5 size-5 shrink-0 overflow-hidden rounded-full">
                                        <AvatarImage
                                            src={auth.user?.avatar}
                                            alt=""
                                        />
                                        <AvatarFallback className="rounded-full bg-primary-soft text-2xs leading-3 font-semibold text-primary-soft-foreground">
                                            {getInitials(auth.user?.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span
                                        className={
                                            compact
                                                ? 'sr-only'
                                                : 'min-w-0 truncate'
                                        }
                                    >
                                        {auth.user?.name}
                                    </span>
                                    {!compact && (
                                        <span className="-mr-0.5 ml-auto flex size-6 shrink-0 items-center justify-center">
                                            <ChevronsUpDown
                                                size={16}
                                                strokeWidth={1.5}
                                                aria-hidden="true"
                                                className="text-muted-foreground"
                                            />
                                        </span>
                                    )}
                                </SidebarMenuButton>
                            }
                        />
                    </RailTooltip>
                    {/* Grade do Fluid: o menu alinha os ícones ao eixo da linha. */}
                    <DropdownContent
                        className={SIDEBAR_MENU_POPUP}
                        side="top"
                        align="start"
                        sideOffset={6}
                    >
                        {auth.user && (
                            <UserMenuContent
                                user={auth.user}
                                onResetSidebar={resetSidebarPreferences}
                            />
                        )}
                    </DropdownContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

// ---------------------------------------------------------------------------
// DashboardSidebar: Fluid Functionalism nas cores do shell (.app-sidebar);
// recolhida, vira a faixa de ícones de 80px.
// ---------------------------------------------------------------------------

export function DashboardSidebar({ showLogo = false, className }: Props) {
    const { nav } = usePage().props;
    const { isMobile, setOpenMobile, open } = useSidebar();
    const compact = !open && !isMobile;
    const globalItems = resolveNavItems(nav.global);
    const footerItems = resolveNavItems(nav.footer);

    useEffect(
        () => router.on('navigate', () => setOpenMobile(false)),
        [setOpenMobile],
    );

    useEffect(() => {
        const desktop = window.matchMedia('(min-width: 768px)');
        const closeOnDesktop = () => {
            if (desktop.matches) {
                setOpenMobile(false);
            }
        };

        desktop.addEventListener('change', closeOnDesktop);

        return () => desktop.removeEventListener('change', closeOnDesktop);
    }, [setOpenMobile]);

    const closeDrawerOnLink = (event: MouseEvent<HTMLElement>) => {
        if (isMobile && (event.target as HTMLElement).closest('a[href]')) {
            setOpenMobile(false);
        }
    };

    return (
        <Sidebar
            variant="sidebar"
            className={cn('app-sidebar bg-sidebar', className)}
        >
            {/* display: contents leva os tokens também ao drawer mobile (portal). */}
            <div className="app-sidebar contents">
                <CompactContext.Provider value={compact}>
                    {showLogo && (
                        <SidebarHeader className="px-3 pt-5 pb-7">
                            <Link
                                href={dashboard()}
                                prefetch
                                aria-label="Ir ao dashboard"
                                className={cn(
                                    'flex h-10 items-center rounded-[8px] text-sidebar-foreground outline-none focus-visible:ring-1 focus-visible:ring-(--focus-ring)',
                                    compact ? 'justify-center' : 'px-1',
                                )}
                            >
                                <AppLogo showText={!compact} />
                            </Link>
                        </SidebarHeader>
                    )}

                    <SidebarContent onClickCapture={closeDrawerOnLink}>
                        <nav
                            aria-label="Navegação principal"
                            className={cn(
                                'flex flex-col',
                                compact ? 'gap-2' : 'gap-6',
                            )}
                        >
                            {globalItems.length > 0 && (
                                <NavGroup>
                                    <NavRows items={globalItems} />
                                </NavGroup>
                            )}
                            {nav.style === 'modules' ? (
                                <ModulesSidebarContent />
                            ) : (
                                <FlatSidebarContent />
                            )}
                        </nav>
                    </SidebarContent>

                    <SidebarFooter
                        className="gap-3 px-3 pt-4 pb-5"
                        onClickCapture={closeDrawerOnLink}
                    >
                        {footerItems.length > 0 && (
                            <NavRows items={footerItems} />
                        )}
                        <UserFooter />
                    </SidebarFooter>
                </CompactContext.Provider>
            </div>
        </Sidebar>
    );
}
