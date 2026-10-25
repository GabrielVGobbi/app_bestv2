import { Link } from '@inertiajs/react';
import { m, useReducedMotion } from 'motion/react';
import { spring } from '@/components/ui/fluid/lib/springs';
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
    const reduceMotion = useReducedMotion() ?? false;
    const isHeader = variant === 'header';

    return (
        <nav
            className={cn(
                isHeader
                    ? 'flex min-w-0 items-center gap-0.5 overflow-x-auto'
                    : 'space-y-0.5',
            )}
            aria-label="Módulos"
        >
            {!isHeader && !isCollapsed && (
                <p className="mb-2 px-3 text-2xs font-semibold tracking-wide text-sidebar-muted-foreground uppercase">
                    Módulos
                </p>
            )}

            {modules.map((mod) => {
                const isLinkModule = mod.items.length === 0 && !!mod.href;
                const moduleHref = isLinkModule ? mod.href : mod.defaultHref;
                // No header, o ativo é o módulo cujos itens a sidebar está mostrando.
                const isActive = isHeader
                    ? isLinkModule
                        ? isCurrentUrl(mod.href as string)
                        : mod.slug === activeModule.slug
                    : isLinkModule
                      ? sidebarView === 'modules' &&
                        isCurrentUrl(mod.href as string)
                      : sidebarView === 'items' &&
                        mod.slug === activeModule.slug;

                const sharedClass = cn(
                    'group relative flex items-center font-medium transition-all duration-150 ease-out',
                    isHeader
                        ? 'h-8 shrink-0 gap-2 rounded-full px-3 text-[13px]'
                        : 'w-full rounded-full px-3 py-2 text-[13px]',
                    !isHeader &&
                        (isCollapsed ? 'justify-center p-2' : 'gap-2.5'),
                    isHeader
                        ? isActive
                            ? 'text-foreground'
                            : 'text-muted-foreground hover:bg-hover hover:text-foreground'
                        : isActive
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                          : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                );

                const inner = (
                    <>
                        {/* Pílula do selecionado (mesmo fundo das abas sutis do Fluid); desliza entre os módulos. */}
                        {isHeader && isActive && (
                            <m.span
                                layoutId="header-module-active"
                                aria-hidden="true"
                                className="absolute inset-0 rounded-full bg-active"
                                transition={
                                    reduceMotion
                                        ? { duration: 0 }
                                        : spring.moderate
                                }
                            />
                        )}
                        {mod.icon && (
                            <mod.icon
                                size={isHeader ? 15 : 16}
                                className={cn(
                                    'relative shrink-0',
                                    isHeader &&
                                        isActive &&
                                        'text-module-active',
                                )}
                            />
                        )}
                        <span
                            className={cn(
                                'relative overflow-hidden whitespace-nowrap',
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
                                    'pointer-events-none absolute left-full z-50 ml-2 translate-x-2 rounded-md bg-popover px-2 py-1 text-2xs font-semibold whitespace-nowrap text-popover-foreground opacity-0 shadow-md transition-[opacity,transform] delay-200 duration-150 group-hover:translate-x-0 group-hover:opacity-100 group-hover:delay-500',
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
                            prefetch
                            aria-current={isActive ? 'page' : undefined}
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
