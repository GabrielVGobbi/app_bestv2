/**
 * NavModuleItems — componente portátil para renderizar os itens do módulo ativo.
 *
 * Variantes:
 *   "sidebar"  — lista vertical com ícones, grupos expansíveis (padrão)
 *   "header"   — links horizontais (para header/topbar)
 *   "dropdown" — itens de dropdown menu
 *
 * Uso:
 *   <NavModuleItems />                      // sidebar (padrão)
 *   <NavModuleItems variant="header" />     // header tabs
 *   <NavModuleItems variant="dropdown" />   // dentro de um DropdownMenuContent
 */

import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useNavModule } from '@/contexts/nav-module-context';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

type Variant = 'sidebar' | 'header' | 'dropdown';

// ---------------------------------------------------------------------------
// Sidebar variant
// ---------------------------------------------------------------------------

function SidebarNavButton({
    item,
    isActive,
    indent = false,
}: {
    item: NavItem;
    isActive: boolean;
    indent?: boolean;
}) {
    if (!item.href) {
        return null;
    }

    const isExternal =
        typeof item.href === 'string' && item.href.startsWith('http');
    const cls = cn(
        'relative flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium transition-colors',
        indent && 'pl-4',
        isActive
            ? 'bg-sidebar-accent text-sidebar-accent-foreground before:absolute before:inset-y-1.5 before:left-0 before:w-[3px] before:rounded-full before:bg-primary'
            : 'text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground',
    );

    const content = (
        <>
            {item.icon && <item.icon size={15} className="shrink-0" />}
            <span className="truncate">{item.title}</span>
        </>
    );

    if (isExternal) {
        return (
            <a
                href={item.href as string}
                target="_blank"
                rel="noopener noreferrer"
                className={cls}
            >
                {content}
            </a>
        );
    }

    return (
        <Link href={item.href as string} prefetch="click" className={cls}>
            {content}
        </Link>
    );
}

function SidebarNavGroup({ item }: { item: NavItem }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const anyChildActive = (items: NavItem[]): boolean =>
        items.some(
            (c) =>
                (!!c.href && isCurrentOrParentUrl(c.href as string)) ||
                (c.items ? anyChildActive(c.items) : false),
        );

    const childActive = anyChildActive(item.items ?? []);
    const [open, setOpen] = useState(childActive);

    if (!item.items?.length) {
        return (
            <SidebarNavButton
                item={item}
                isActive={
                    !!item.href && isCurrentOrParentUrl(item.href as string)
                }
            />
        );
    }

    return (
        <div>
            <button
                onClick={() => setOpen((v) => !v)}
                className={cn(
                    'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium transition-colors',
                    childActive
                        ? 'text-sidebar-accent-foreground'
                        : 'text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground',
                )}
            >
                {item.icon && <item.icon size={15} className="shrink-0" />}
                <span className="truncate">{item.title}</span>
                <ChevronRight
                    size={12}
                    className={cn(
                        'ml-auto shrink-0 transition-transform duration-200',
                        open && 'rotate-90',
                    )}
                />
            </button>
            {open && (
                <div className="mt-0.5 ml-2 space-y-0.5 border-l border-sidebar-border pl-3">
                    {item.items.map((child) =>
                        child.type === 'section' ? (
                            <SidebarNavSection
                                key={child.title}
                                item={child}
                                nested
                            />
                        ) : child.items?.length ? (
                            <SidebarNavGroup key={child.title} item={child} />
                        ) : (
                            <SidebarNavButton
                                key={child.title}
                                item={child}
                                isActive={
                                    !!child.href &&
                                    isCurrentOrParentUrl(child.href as string)
                                }
                                indent
                            />
                        ),
                    )}
                </div>
            )}
        </div>
    );
}

function SidebarNavSection({
    item,
    nested = false,
}: {
    item: NavItem;
    nested?: boolean;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className={cn('space-y-1', !nested && 'pt-4 first:pt-0')}>
            <div
                className={cn(
                    'mt-5 px-3 pb-1 text-[10px] leading-none font-semibold tracking-[0.08em] text-sidebar-foreground/40 uppercase',
                    nested && 'pl-1',
                )}
            >
                {item.title}
            </div>

            {item.items?.map((child) =>
                child.type === 'section' ? (
                    <SidebarNavSection key={child.title} item={child} nested />
                ) : child.items?.length ? (
                    <SidebarNavGroup key={child.title} item={child} />
                ) : (
                    <SidebarNavButton
                        key={child.title}
                        item={child}
                        isActive={
                            !!child.href &&
                            isCurrentOrParentUrl(child.href as string)
                        }
                    />
                ),
            )}
        </div>
    );
}

function SidebarVariant({ items }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="space-y-1">
            {items.map((item) =>
                item.type === 'section' ? (
                    <SidebarNavSection key={item.title} item={item} />
                ) : item.items?.length ? (
                    <SidebarNavGroup key={item.title} item={item} />
                ) : (
                    <SidebarNavButton
                        key={item.title}
                        item={item}
                        isActive={
                            !!item.href &&
                            isCurrentOrParentUrl(item.href as string)
                        }
                    />
                ),
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Header variant — links horizontais
// ---------------------------------------------------------------------------

function HeaderVariant({ items }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    // Flatten: só links de primeiro nível (grupos viram separadores visuais)
    const flattenItems = (source: NavItem[]): NavItem[] =>
        source.flatMap((item) =>
            item.type === 'section' || item.items?.length
                ? flattenItems(item.items ?? [])
                : [item],
        );
    const flat = flattenItems(items);

    return (
        <nav className="flex items-center gap-1">
            {flat.map((item) => {
                if (!item.href) {
                    return null;
                }

                const isActive = isCurrentOrParentUrl(item.href as string);

                return (
                    <Link
                        key={item.title}
                        href={item.href as string}
                        prefetch="click"
                        className={cn(
                            'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[13px] font-medium transition-colors',
                            isActive
                                ? 'bg-secondary text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {item.icon && <item.icon size={14} />}
                        {item.title}
                    </Link>
                );
            })}
        </nav>
    );
}

// ---------------------------------------------------------------------------
// Dropdown variant
// ---------------------------------------------------------------------------

function DropdownVariant({ items }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const renderDropdownItem = (item: NavItem) => {
        if (!item.href) {
            return null;
        }

        return (
            <DropdownMenuItem key={item.title} asChild>
                <Link
                    href={item.href}
                    className={cn(
                        isCurrentOrParentUrl(item.href as string) &&
                            'font-semibold',
                    )}
                >
                    {item.icon && <item.icon size={14} />}
                    {item.title}
                </Link>
            </DropdownMenuItem>
        );
    };

    const renderDropdownChildren = (source: NavItem[]): ReactNode =>
        source.map((child) =>
            child.type === 'section' || child.items?.length
                ? renderDropdownChildren(child.items ?? [])
                : renderDropdownItem(child),
        );

    return (
        <>
            {items.map((item, i) => {
                if (item.type === 'section') {
                    return (
                        <DropdownMenuGroup key={item.title}>
                            {i > 0 && <DropdownMenuSeparator />}
                            <DropdownMenuLabel className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                {item.title}
                            </DropdownMenuLabel>
                            {renderDropdownChildren(item.items ?? [])}
                        </DropdownMenuGroup>
                    );
                }

                if (item.items?.length) {
                    return (
                        <DropdownMenuGroup key={item.title}>
                            {i > 0 && <DropdownMenuSeparator />}
                            <DropdownMenuLabel className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                                {item.title}
                            </DropdownMenuLabel>
                            {renderDropdownChildren(item.items)}
                        </DropdownMenuGroup>
                    );
                }

                return renderDropdownItem(item);
            })}
        </>
    );
}

// ---------------------------------------------------------------------------
// Export principal
// ---------------------------------------------------------------------------

export function NavModuleItems({ variant = 'sidebar' }: { variant?: Variant }) {
    const { activeModule } = useNavModule();

    if (variant === 'header') {
        return <HeaderVariant items={activeModule.items} />;
    }

    if (variant === 'dropdown') {
        return <DropdownVariant items={activeModule.items} />;
    }

    return <SidebarVariant items={activeModule.items} />;
}
