import { Link } from '@inertiajs/react';
import { useNavModule } from '@/contexts/nav-module-context';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

type Variant = 'sidebar' | 'header';

type Props = {
    isCollapsed?: boolean;
    variant?: Variant;
};

export function NavModuleSwitcher({
    isCollapsed = false,
    variant = 'sidebar',
}: Props) {
    const { modules, activeModule, sidebarView, selectModule } = useNavModule();
    const { isCurrentUrl } = useCurrentUrl();
    const isHeader = variant === 'header';

    return (
        <nav
            className={cn(
                isHeader
                    ? 'flex min-w-0 items-center gap-0.5 overflow-hidden'
                    : 'space-y-0.5',
            )}
            aria-label="Módulos"
        >
            {!isHeader && !isCollapsed && (
                <p className="mb-2 px-3 text-[10px] font-semibold tracking-[0.08em] text-sidebar-foreground/40 uppercase">
                    Módulos
                </p>
            )}

            {modules.map((mod) => {
                const isLinkModule = mod.items.length === 0 && !!mod.href;
                const moduleHref = isLinkModule ? mod.href : mod.defaultHref;
                const isActive = isLinkModule
                    ? sidebarView === 'modules' &&
                      isCurrentUrl(mod.href as string)
                    : sidebarView === 'items' && mod.slug === activeModule.slug;

                const sharedClass = cn(
                    'group relative flex items-center font-medium transition-all duration-150 ease-out',
                    isHeader
                        ? 'h-7 shrink-0 gap-2 rounded-md px-3.5 text-[13px]'
                        : 'w-full rounded-lg px-3 py-2 text-[13px]',
                    !isHeader &&
                        (isCollapsed ? 'justify-center p-2' : 'gap-2.5'),
                    isHeader
                        ? isActive
                            ? 'bg-muted font-semibold text-foreground after:absolute after:inset-x-3 after:-bottom-1 after:h-0.5 after:rounded-full after:bg-primary'
                            : 'text-foreground/55 hover:bg-muted hover:text-foreground'
                        : isActive
                          ? 'bg-sidebar-accent text-sidebar-accent-foreground before:absolute before:inset-y-1.5 before:left-0 before:w-[3px] before:rounded-full before:bg-primary'
                          : 'text-sidebar-foreground/65 hover:bg-sidebar-accent hover:text-sidebar-foreground',
                );

                const inner = (
                    <>
                        {mod.icon && (
                            <mod.icon
                                size={isHeader ? 15 : 16}
                                className="shrink-0"
                            />
                        )}
                        <span
                            className={cn(
                                'overflow-hidden whitespace-nowrap',
                                isHeader
                                    ? 'max-w-32 truncate'
                                    : 'transition-[opacity,max-width] duration-200 ease-out',
                                !isHeader &&
                                    (isCollapsed
                                        ? 'max-w-0 opacity-0'
                                        : 'max-w-40 opacity-100'),
                            )}
                        >
                            {mod.label}
                        </span>

                        {!isHeader && (
                            <div
                                className={cn(
                                    'pointer-events-none absolute left-full z-50 ml-2 translate-x-2 rounded-md bg-foreground px-2 py-1 text-[10px] font-bold whitespace-nowrap text-background opacity-0 shadow-xl transition-[opacity,transform] delay-200 duration-150 group-hover:translate-x-0 group-hover:opacity-100 group-hover:delay-500',
                                    !isCollapsed && 'hidden',
                                )}
                            >
                                {mod.label}
                            </div>
                        )}
                    </>
                );

                if (moduleHref) {
                    return (
                        <Link
                            key={mod.slug}
                            href={moduleHref}
                            prefetch="click"
                            onClick={() => {
                                if (!isLinkModule) {
                                    selectModule(mod.slug);
                                }
                            }}
                            className={sharedClass}
                        >
                            {inner}
                        </Link>
                    );
                }

                return (
                    <button
                        key={mod.slug}
                        type="button"
                        onClick={() => selectModule(mod.slug)}
                        className={sharedClass}
                    >
                        {inner}
                    </button>
                );
            })}
        </nav>
    );
}
