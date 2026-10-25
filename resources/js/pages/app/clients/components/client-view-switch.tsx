import { Columns2, Table2 } from 'lucide-react';

import { TabItem, Tabs, TabsList } from '@/components/ui/fluid/tabs';

export type ClientViewMode = 'table' | 'split';

const CLIENT_VIEW_STORAGE_PREFIX = 'bestplay:clients:view-mode';

export function clientViewStorageKey(userId: number): string {
    return `${CLIENT_VIEW_STORAGE_PREFIX}:${userId}`;
}

export function readStoredViewMode(storageKey: string): ClientViewMode {
    if (typeof window === 'undefined') {
        return 'table';
    }

    try {
        return window.localStorage.getItem(storageKey) === 'split'
            ? 'split'
            : 'table';
    } catch {
        return 'table';
    }
}

export function writeStoredViewMode(
    storageKey: string,
    viewMode: ClientViewMode,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(storageKey, viewMode);
    } catch {
        // localStorage is only a preference cache; ignore privacy/quota failures.
    }
}

interface ClientViewSwitchProps {
    value: ClientViewMode;
    onChange: (viewMode: ClientViewMode) => void;
}

/** Controle segmentado (Tabs do Fluid) entre a tabela e a visão em duas colunas. */
export function ClientViewSwitch({ value, onChange }: ClientViewSwitchProps) {
    return (
        <Tabs
            value={value}
            onValueChange={(viewMode) => {
                if (viewMode !== value) {
                    onChange(viewMode as ClientViewMode);
                }
            }}
        >
            <TabsList aria-label="Visualização de clientes">
                <TabItem value="table" label="Tabela" icon={Table2} />
                <TabItem value="split" label="Duas colunas" icon={Columns2} />
            </TabsList>
        </Tabs>
    );
}
