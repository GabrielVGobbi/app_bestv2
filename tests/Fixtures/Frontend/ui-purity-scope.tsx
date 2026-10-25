import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';

export interface SyntheticScope {
    userId: number;
    isCurrent: () => boolean;
    storageKey: (key: string) => string;
}

const ScopeContext = createContext<{ scope: SyntheticScope } | null>(null);

export function ScopeProvider({
    scope,
    children,
}: {
    scope: SyntheticScope;
    children: ReactNode;
}) {
    return (
        <ScopeContext.Provider value={{ scope }}>
            {children}
        </ScopeContext.Provider>
    );
}

export function usePrivateState() {
    const value = useContext(ScopeContext);

    if (!value) {
        throw new Error('UI008 scope provider missing.');
    }

    return value;
}
