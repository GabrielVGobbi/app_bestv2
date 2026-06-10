import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { resolveNavModules } from '@/lib/resolve-nav-items';
import type { NavModule } from '@/types';

// ---------------------------------------------------------------------------
// Tipos
// ---------------------------------------------------------------------------

export type SidebarView = 'modules' | 'items';

type NavModuleContextValue = {
    /** Todos os módulos disponíveis. */
    modules: NavModule[];
    /** Módulo atualmente ativo (auto-detectado pela URL). */
    activeModule: NavModule;
    /** Tela atual da sidebar. */
    sidebarView: SidebarView;
    /** Volta para a lista de módulos. */
    showModuleList: () => void;
    /** Seleciona um módulo manualmente e vai para os items. */
    selectModule: (slug: string) => void;
};

// ---------------------------------------------------------------------------
// Context
// ---------------------------------------------------------------------------

const NavModuleContext = createContext<NavModuleContextValue | null>(null);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function detectModule(modules: NavModule[], pathname: string): NavModule | undefined {
    // Apenas módulos com items participam da detecção (link-modules navegam diretamente)
    const candidates = modules
        .filter((m) => m.items.length > 0)
        .flatMap((m) => m.routePrefixes.map((prefix) => ({ module: m, prefix })));

    candidates.sort((a, b) => b.prefix.length - a.prefix.length);
    return candidates.find(({ prefix }) => pathname.startsWith(prefix))?.module;
}

// ---------------------------------------------------------------------------
// Provider
// ---------------------------------------------------------------------------

export function NavModuleProvider({ children }: { children: React.ReactNode }) {
    const page = usePage();
    const { nav } = page.props;
    const rawUrl = page.url as string;

    // Memoize resolved modules — nav.modules is stable within a page, only
    // changes on Inertia navigations, so this avoids re-creating objects every render.
    const modules = useMemo(() => resolveNavModules(nav.modules), [nav.modules]);

    // Memoize pathname extraction — avoids creating a URL object on every render.
    const pathname = useMemo(() => {
        try {
            return new URL(rawUrl, 'http://x').pathname;
        } catch {
            return rawUrl;
        }
    }, [rawUrl]);

    // Módulo padrão = primeiro módulo com items (link-modules não têm view de items).
    // Memoized so it doesn't recompute on every render.
    const defaultModuleWithItems = useMemo(
        () =>
            modules.filter((m) => m.items.length > 0).find((m) => m.default) ??
            modules.find((m) => m.items.length > 0) ??
            modules[0],
        [modules],
    );

    const detected = detectModule(modules, pathname);

    // Se a URL não bate nenhum módulo com items → mostra lista de módulos
    const [activeSlug, setActiveSlug] = useState((detected ?? defaultModuleWithItems)?.slug ?? '');
    const [sidebarView, setSidebarView] = useState<SidebarView>(detected ? 'items' : 'modules');

    // Quando a URL muda → re-detecta módulo ativo.
    // modules is stable within a page (memoized above) so including it here
    // is safe and removes the need to suppress the exhaustive-deps rule.
    const prevSlugRef = useRef(activeSlug);
    useEffect(() => {
        const next = detectModule(modules, pathname);
        if (next) {
            if (next.slug !== prevSlugRef.current) {
                prevSlugRef.current = next.slug;
                setActiveSlug(next.slug);
            }
            setSidebarView('items');
        } else {
            // URL não pertence a nenhum módulo com items → volta para lista de módulos
            setSidebarView('modules');
        }
    }, [pathname, modules]);

    // Memoize activeModule so consumers don't re-render when other state changes.
    const activeModule = useMemo(
        () => modules.find((m) => m.slug === activeSlug) ?? defaultModuleWithItems,
        [modules, activeSlug, defaultModuleWithItems],
    );

    // Stable function references so the memoized context value below doesn't
    // invalidate on every render.
    const showModuleList = useCallback(() => setSidebarView('modules'), []);

    const selectModule = useCallback((slug: string) => {
        setActiveSlug(slug);
        prevSlugRef.current = slug;
        setSidebarView('items');
    }, []);

    // Memoize the context value so consumers only re-render when something
    // actually changed, not on every provider render (e.g. Inertia visit events).
    const contextValue = useMemo(
        () => ({ modules, activeModule, sidebarView, showModuleList, selectModule }),
        [modules, activeModule, sidebarView, showModuleList, selectModule],
    );

    return (
        <NavModuleContext.Provider value={contextValue}>
            {children}
        </NavModuleContext.Provider>
    );
}

// ---------------------------------------------------------------------------
// Hook
// ---------------------------------------------------------------------------

export function useNavModule(): NavModuleContextValue {
    const ctx = useContext(NavModuleContext);
    if (!ctx) throw new Error('useNavModule must be used inside NavModuleProvider');
    return ctx;
}
