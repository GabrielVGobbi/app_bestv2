import type {
    ColumnOrderState,
    ColumnPinningState,
    ColumnSizingState,
    RowSelectionState,
    SortingState,
    VisibilityState,
} from '@tanstack/react-table';
import type { Dispatch, SetStateAction } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePrivateState } from '@/contexts/private-state-context';
import type { PrivateStateScope } from '@/contexts/private-state-context';

import {
    readTablePreferences,
    resolveTablePreferencesConfig,
    sanitizeColumnOrder,
    sanitizeColumnPinning,
    sanitizeColumnSizing,
    sanitizeColumnVisibility,
    writeTablePreferences,
} from '../core/table-preferences';
import type {
    TablePreferencesConfig,
    TablePreferencesStorageStatus,
} from '../core/table-preferences';
import type {
    DataTableFilterConfig,
    DataTableFilterValue,
    DataTablePersistStateConfig,
    DataTableQuickFilter,
    DataTableSize,
    DataTableState,
} from '../core/types';

interface StateArgs {
    queryKey: string;
    tableId?: string;
    initialPerPage: number;
    initialColumnVisibility?: VisibilityState;
    initialColumnPinning?: ColumnPinningState;
    nonHideableColumnIds?: string[];
    initialDensity?: DataTableSize;
    filters: Array<DataTableFilterConfig | DataTableQuickFilter>;
    columnIds?: string[];
    persistState?: boolean | DataTablePersistStateConfig;
}

function initialState(args: StateArgs): DataTableState {
    return {
        page: 1,
        perPage: args.initialPerPage,
        search: '',
        sorting: [],
        filters: {},
        rowSelection: {},
        columnVisibility: sanitizeColumnVisibility(
            args.initialColumnVisibility,
            args.columnIds ?? [],
            undefined,
            args.nonHideableColumnIds,
        ),
        columnOrder: sanitizeColumnOrder([], args.columnIds ?? []),
        columnPinning: sanitizeColumnPinning(
            args.initialColumnPinning,
            args.columnIds ?? [],
            args.initialColumnPinning,
        ),
        columnSizing: {},
        density: args.initialDensity ?? 'md',
    };
}

function resolveUpdate<T>(value: SetStateAction<T>, current: T): T {
    return typeof value === 'function'
        ? (value as (previous: T) => T)(current)
        : value;
}

export function useDataTableState(args: StateArgs): {
    state: DataTableState;
    ready: boolean;
    storageStatus: TablePreferencesStorageStatus;
    setPage: (page: number) => void;
    setPerPage: (perPage: number) => void;
    setSearch: (search: string) => void;
    setSorting: Dispatch<SetStateAction<SortingState>>;
    setColumnVisibility: Dispatch<SetStateAction<VisibilityState>>;
    setColumnOrder: Dispatch<SetStateAction<ColumnOrderState>>;
    setColumnPinning: Dispatch<SetStateAction<ColumnPinningState>>;
    setColumnSizing: Dispatch<SetStateAction<ColumnSizingState>>;
    setDensity: (density: DataTableSize) => void;
    setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
    setFilterValue: (id: string, value: DataTableFilterValue) => void;
    resetFilters: () => void;
    resetLayout: () => void;
    resetAll: () => void;
} {
    const { scope } = usePrivateState();
    const flags =
        typeof args.persistState === 'object' ? args.persistState : undefined;
    const persistEnabled = Boolean(args.persistState);
    const persistConfig = useMemo(
        () =>
            resolveTablePreferencesConfig({
                scope,
                queryKey: args.queryKey,
                tableId: args.tableId,
                persistState: persistEnabled
                    ? {
                          key: flags?.key,
                          search: flags?.search,
                          filters: flags?.filters,
                          sorting: flags?.sorting,
                          perPage: flags?.perPage,
                          columnVisibility: flags?.columnVisibility,
                          layout: flags?.layout,
                          density: flags?.density,
                      }
                    : false,
            }),
        [
            args.queryKey,
            args.tableId,
            flags?.columnVisibility,
            flags?.density,
            flags?.filters,
            flags?.key,
            flags?.layout,
            flags?.perPage,
            flags?.search,
            flags?.sorting,
            persistEnabled,
            scope,
        ],
    );
    const inputs = useRef(args);
    useEffect(() => {
        inputs.current = args;
    });

    const [store, setStore] = useState(() => ({
        state: initialState(args),
        revision: 0,
    }));
    const [restored, setRestored] = useState<{
        config: TablePreferencesConfig | null;
        scope: PrivateStateScope;
        storageStatus: TablePreferencesStorageStatus;
    } | null>(() =>
        !persistEnabled
            ? { config: persistConfig, scope, storageStatus: 'disabled' }
            : null,
    );
    const ready =
        scope.isCurrent() &&
        restored?.scope === scope &&
        restored.config === persistConfig;

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (!scope.isCurrent()) {
                return;
            }

            const current = inputs.current;
            const result = readTablePreferences({
                config: persistConfig,
                scope,
                getStorage: () => window.localStorage,
                columnIds: current.columnIds ?? [],
                filterIds: current.filters.map((filter) => filter.id),
                initialColumnVisibility: current.initialColumnVisibility,
                initialColumnPinning: current.initialColumnPinning,
                nonHideableColumnIds: current.nonHideableColumnIds,
            });

            if (!scope.isCurrent()) {
                return;
            }

            setStore((previous) => ({
                state: {
                    ...initialState(current),
                    ...result.preferences,
                    rowSelection: previous.state.rowSelection,
                },
                revision: 0,
            }));
            setRestored({
                config: persistConfig,
                scope,
                storageStatus: result.storageStatus,
            });
        }, 0);
        const cancel = () => window.clearTimeout(timeout);
        scope.signal.addEventListener('abort', cancel, { once: true });

        return () => {
            cancel();
            scope.signal.removeEventListener('abort', cancel);
        };
    }, [persistConfig, scope]);

    useEffect(() => {
        if (!ready || !persistConfig || store.revision === 0) {
            return;
        }

        if (!scope.isCurrent()) {
            return;
        }

        const current = inputs.current;
        const storageStatus = writeTablePreferences({
            config: persistConfig,
            scope,
            getStorage: () => window.localStorage,
            columnIds: current.columnIds ?? [],
            filterIds: current.filters.map((filter) => filter.id),
            initialColumnVisibility: current.initialColumnVisibility,
            initialColumnPinning: current.initialColumnPinning,
            nonHideableColumnIds: current.nonHideableColumnIds,
            preferences: store.state,
        });
        queueMicrotask(() => {
            if (scope.isCurrent()) {
                setRestored((previous) =>
                    previous?.scope === scope &&
                    previous.config === persistConfig
                        ? { config: persistConfig, scope, storageStatus }
                        : previous,
                );
            }
        });
    }, [persistConfig, ready, scope, store]);

    function update(change: (state: DataTableState) => DataTableState): void {
        if (!ready || !scope.isCurrent()) {
            return;
        }

        setStore((current) => ({
            state: change(current.state),
            revision: current.revision + 1,
        }));
    }

    function setSorting(value: SetStateAction<SortingState>): void {
        update((state) => ({
            ...state,
            page: 1,
            sorting: resolveUpdate(value, state.sorting),
        }));
    }

    function setColumnVisibility(value: SetStateAction<VisibilityState>): void {
        update((state) => ({
            ...state,
            columnVisibility: sanitizeColumnVisibility(
                resolveUpdate(value, state.columnVisibility),
                inputs.current.columnIds ?? [],
                undefined,
                inputs.current.nonHideableColumnIds,
            ),
        }));
    }

    function resetLayout(): void {
        update((state) => {
            const defaults = initialState(inputs.current);

            return {
                ...state,
                columnVisibility: defaults.columnVisibility,
                columnOrder: defaults.columnOrder,
                columnPinning: defaults.columnPinning,
                columnSizing: defaults.columnSizing,
                density: defaults.density,
            };
        });
    }

    const setRowSelection = useCallback(
        (value: SetStateAction<RowSelectionState>) => {
            if (ready && scope.isCurrent()) {
                setStore((current) => ({
                    ...current,
                    state: {
                        ...current.state,
                        rowSelection: resolveUpdate(
                            value,
                            current.state.rowSelection,
                        ),
                    },
                }));
            }
        },
        [ready, scope],
    );

    return {
        state: store.state,
        ready,
        storageStatus: ready ? restored.storageStatus : 'disabled',
        setPage: (page) => update((state) => ({ ...state, page })),
        setPerPage: (perPage) =>
            update((state) => ({ ...state, page: 1, perPage })),
        setSearch: (search) =>
            update((state) => ({ ...state, page: 1, search })),
        setSorting,
        setColumnVisibility,
        setColumnOrder: (value) =>
            update((state) => ({
                ...state,
                columnOrder: sanitizeColumnOrder(
                    resolveUpdate(value, state.columnOrder),
                    inputs.current.columnIds ?? [],
                ),
            })),
        setColumnPinning: (value) =>
            update((state) => ({
                ...state,
                columnPinning: sanitizeColumnPinning(
                    resolveUpdate(value, state.columnPinning),
                    inputs.current.columnIds ?? [],
                    inputs.current.initialColumnPinning,
                ),
            })),
        setColumnSizing: (value) =>
            update((state) => ({
                ...state,
                columnSizing: sanitizeColumnSizing(
                    resolveUpdate(value, state.columnSizing),
                    inputs.current.columnIds ?? [],
                ),
            })),
        setDensity: (density) => update((state) => ({ ...state, density })),
        setRowSelection,
        setFilterValue: (id, value) =>
            update((state) => ({
                ...state,
                page: 1,
                filters: { ...state.filters, [id]: value },
            })),
        resetFilters: () =>
            update((state) => ({ ...state, page: 1, filters: {} })),
        resetLayout,
        resetAll: () =>
            update((state) => ({
                ...initialState(inputs.current),
                rowSelection: state.rowSelection,
            })),
    };
}
