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
                <p className="mb-2 px-3 text-[11px] font-semibold tracking-[0.08em] text-sidebar-muted uppercase">
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
                        ? 'h-9 shrink-0 gap-2 rounded-lg px-4 text-sm'
                        : 'w-full min-h-10 rounded-lg px-3 py-2.5 text-sm',
                    !isHeader &&
                        (isCollapsed ? 'justify-center p-2' : 'gap-2.5'),
                    isHeader
                        ? isActive
                            ? 'bg-sidebar-accent font-semibold text-sidebar-accent-foreground'
                            : 'text-header-foreground/70 hover:bg-white/10 hover:text-header-foreground'
                        : isActive
                          ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                          : 'text-sidebar-muted hover:bg-white/5 hover:text-sidebar-foreground',
                );

                const inner = (
                    <>
                        {mod.icon && (
                            <mod.icon
                                size={isHeader ? 16 : 18}
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
