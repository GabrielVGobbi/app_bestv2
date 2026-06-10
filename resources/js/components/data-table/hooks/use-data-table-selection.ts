import type { Dispatch, SetStateAction } from 'react';
import { useCallback, useEffect, useRef } from 'react';

import type { RowSelectionState } from '@tanstack/react-table';

interface UseDataTableSelectionArgs<TData> {
    storageKey: string;
    keyExtractor: (row: TData) => string | number;
    currentRows: TData[];
    rowSelection: RowSelectionState;
    setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
    enabled: boolean;
}

interface UseDataTableSelectionResult<TData> {
    selectedRows: TData[];
    selectedCount: number;
    clearSelection: () => void;
}

export function useDataTableSelection<TData>({
    storageKey,
    keyExtractor,
    currentRows,
    rowSelection,
    setRowSelection,
    enabled,
}: UseDataTableSelectionArgs<TData>): UseDataTableSelectionResult<TData> {
    // Ref-based cache: holds row data for every selected row ever seen.
    // Using a ref avoids triggering extra re-renders when the cache updates.
    const cacheRef = useRef<Record<string, TData>>({});
    const restoredRef = useRef(false);
    const persistTimerRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    // Restore from localStorage once on mount.
    useEffect(() => {
        if (!enabled || restoredRef.current) return;
        restoredRef.current = true;

        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return;
            const ids: string[] = JSON.parse(raw);
            if (ids.length === 0) return;
            setRowSelection(
                ids.reduce<RowSelectionState>((acc, id) => {
                    acc[id] = true;
                    return acc;
                }, {}),
            );
        } catch {
            // ignore malformed storage
        }
    }, [enabled, storageKey, setRowSelection]);

    // Debounced localStorage persist — defers the synchronous write so rapid
    // multi-clicks don't block the main thread on each selection change.
    useEffect(() => {
        if (!enabled) return;

        clearTimeout(persistTimerRef.current);
        persistTimerRef.current = setTimeout(() => {
            const ids = Object.keys(rowSelection).filter((id) => rowSelection[id]);
            if (ids.length === 0) {
                localStorage.removeItem(storageKey);
            } else {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(ids));
                } catch {
                    // ignore quota errors
                }
            }
        }, 300);

        return () => clearTimeout(persistTimerRef.current);
    }, [enabled, rowSelection, storageKey]);

    // clearSelection resets everything: TanStack state, ref cache, localStorage.
    const clearSelection = useCallback(() => {
        setRowSelection({});
        cacheRef.current = {};
        clearTimeout(persistTimerRef.current);
        localStorage.removeItem(storageKey);
    }, [setRowSelection, storageKey]);

    if (!enabled) {
        return { selectedRows: [], selectedCount: 0, clearSelection };
    }

    // Sync cache inline during render — mutating a ref here is safe and
    // idempotent. No state update means no extra re-render per click.
    const cache = cacheRef.current;

    for (const row of currentRows) {
        const id = String(keyExtractor(row));
        if (rowSelection[id]) {
            cache[id] = row;
        }
    }

    for (const id of Object.keys(cache)) {
        if (!rowSelection[id]) {
            delete cache[id];
        }
    }

    // selectedCount reads from rowSelection directly — includes rows not visible
    // in the current query (e.g. filtered out), giving the accurate total.
    const selectedCount = Object.values(rowSelection).filter(Boolean).length;

    // selectedRows comes from the cache — only rows whose data we've loaded.
    const selectedRows = Object.values(cache);

    return { selectedRows, selectedCount, clearSelection };
}
