import { usePage } from '@inertiajs/react';
import { createContext, useContext, useMemo, useState } from 'react';
import { resolveNavModules } from '@/lib/resolve-nav-items';
import type { NavModule } from '@/types';

type NavModuleContextValue = {
    modules: NavModule[];
    activeModule: NavModule | undefined;
    selectModule: (slug: string) => void;
};

const NavModuleContext = createContext<NavModuleContextValue | null>(null);

function detectModule(
    modules: NavModule[],
    pathname: string,
): NavModule | undefined {
    return modules
        .flatMap((module) =>
            module.routePrefixes.map((prefix) => ({ module, prefix })),
        )
        .sort((a, b) => b.prefix.length - a.prefix.length)
        .find(
            ({ prefix }) =>
                pathname === prefix ||
                pathname.startsWith(prefix.replace(/\/$/, '') + '/'),
        )?.module;
}

export function NavModuleProvider({ children }: { children: React.ReactNode }) {
    const {
        props: { nav },
        url,
    } = usePage();
    const modules = useMemo(
        () => resolveNavModules(nav.modules),
        [nav.modules],
    );
    const pathname = new URL(url, 'http://localhost').pathname;
    const [selection, setSelection] = useState<{
        slug: string;
        pathname: string;
    }>();
    const selectedModule =
        selection?.pathname === pathname
            ? modules.find((module) => module.slug === selection.slug)
            : undefined;
    const activeModule =
        selectedModule ??
        detectModule(modules, pathname) ??
        modules.find((module) => module.default) ??
        modules[0];
    function selectModule(slug: string): void {
        setSelection({ slug, pathname });
    }
    const value = { modules, activeModule, selectModule };

    return (
        <NavModuleContext.Provider value={value}>
            {children}
        </NavModuleContext.Provider>
    );
}

export function useNavModule(): NavModuleContextValue {
    const context = useContext(NavModuleContext);

    if (!context) {
        throw new Error('useNavModule must be used inside NavModuleProvider');
    }

    return context;
}
