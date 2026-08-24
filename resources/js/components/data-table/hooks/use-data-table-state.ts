import type {
    RowSelectionState,
    SortingState,
    VisibilityState,
} from '@tanstack/react-table';
import type { Dispatch, SetStateAction } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';

import type {
    DataTableFilterConfig,
    DataTableFilterValue,
    DataTablePersistStateConfig,
    DataTableQuickFilter,
    DataTableState,
} from '../core/types';

interface DataTablePersistedState {
    search?: string;
    sorting?: SortingState;
    perPage?: number;
    columnVisibility?: VisibilityState;
    filters?: Record<string, DataTableFilterValue>;
}

interface ResolvedPersistStateConfig {
    key: string;
    search: boolean;
    filters: boolean;
    sorting: boolean;
    perPage: boolean;
    columnVisibility: boolean;
}

function isPlainRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isFilterValue(value: unknown): value is DataTableFilterValue {
    if (
        value === null ||
        value === undefined ||
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
    ) {
        return true;
    }

    if (Array.isArray(value)) {
        return value.every(
            (item) =>
                typeof item === 'string' ||
                typeof item === 'number' ||
                typeof item === 'boolean',
        );
    }

    if (isPlainRecord(value)) {
        return (
            ['from', 'to'].some((key) => key in value) &&
            [value.from, value.to].every(
                (item) =>
                    item === null ||
                    item === undefined ||
                    typeof item === 'string' ||
                    typeof item === 'number',
            )
        );
    }

    return false;
}

function sanitizeFilters(
    value: unknown,
    filterIds: Set<string>,
): Record<string, DataTableFilterValue> {
    if (!isPlainRecord(value)) {
        return {};
    }

    return Object.entries(value).reduce<Record<string, DataTableFilterValue>>(
        (filters, [id, filterValue]) => {
            if (filterIds.has(id) && isFilterValue(filterValue)) {
                filters[id] = filterValue;
            }

            return filters;
        },
        {},
    );
}

function sanitizeSorting(value: unknown, columnIds: Set<string>): SortingState {
    if (!Array.isArray(value)) {
        return [];
    }

    return value.reduce<SortingState>((sorting, item) => {
        if (
            isPlainRecord(item) &&
            typeof item.id === 'string' &&
            typeof item.desc === 'boolean' &&
            columnIds.has(item.id)
        ) {
            sorting.push({ id: item.id, desc: item.desc });
        }

        return sorting;
    }, []);
}

function sanitizeColumnVisibility(
    value: unknown,
    columnIds: Set<string>,
): VisibilityState {
    if (!isPlainRecord(value)) {
        return {};
    }

    return Object.entries(value).reduce<VisibilityState>(
        (visibility, [id, isVisible]) => {
            if (columnIds.has(id) && typeof isVisible === 'boolean') {
                visibility[id] = isVisible;
            }

            return visibility;
        },
        {},
    );
}

function isDefaultColumnVisibility(
    current: VisibilityState,
    initial?: VisibilityState,
): boolean {
    const keys = new Set([
        ...Object.keys(current),
        ...Object.keys(initial ?? {}),
    ]);

    for (const key of keys) {
        const currentVisible = current[key] ?? true;
        const initialVisible = initial?.[key] ?? true;

        if (currentVisible !== initialVisible) {
            return false;
        }
    }

    return true;
}

function readPersistedState(args: {
    config: ResolvedPersistStateConfig | null;
    filterIds: Set<string>;
    columnIds: Set<string>;
}): DataTablePersistedState {
    if (!args.config || typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = window.localStorage.getItem(args.config.key);

        if (!raw) {
            return {};
        }

        const parsed: unknown = JSON.parse(raw);

        if (!isPlainRecord(parsed)) {
            return {};
        }

        return {
            search:
                args.config.search && typeof parsed.search === 'string'
                    ? parsed.search
                    : undefined,
            perPage:
                args.config.perPage &&
                typeof parsed.perPage === 'number' &&
                Number.isInteger(parsed.perPage) &&
                parsed.perPage > 0
                    ? parsed.perPage
                    : undefined,
            sorting: args.config.sorting
                ? sanitizeSorting(parsed.sorting, args.columnIds)
                : undefined,
            columnVisibility: args.config.columnVisibility
                ? sanitizeColumnVisibility(
                      parsed.columnVisibility,
                      args.columnIds,
                  )
                : undefined,
            filters: args.config.filters
                ? sanitizeFilters(parsed.filters, args.filterIds)
                : undefined,
        };
    } catch {
        return {};
    }
}

export function useDataTableState(args: {
    queryKey: string;
    initialPerPage: number;
    initialColumnVisibility?: VisibilityState;
    initialFilters?: Record<string, DataTableFilterValue>;
    filters: Array<DataTableFilterConfig | DataTableQuickFilter>;
    columnIds?: string[];
    persistState?: boolean | DataTablePersistStateConfig;
}): {
    state: DataTableState;
    setPage: (page: number) => void;
    setPerPage: (perPage: number) => void;
    setSearch: (search: string) => void;
    setSorting: Dispatch<SetStateAction<SortingState>>;
    setColumnVisibility: Dispatch<SetStateAction<VisibilityState>>;
    setRowSelection: Dispatch<SetStateAction<RowSelectionState>>;
    setFilterValue: (id: string, value: DataTableFilterValue) => void;
    resetFilters: () => void;
    resetTable: () => void;
    canResetTable: boolean;
} {
    const persistStateConfig =
        typeof args.persistState === 'object' ? args.persistState : undefined;
    const persistStateEnabled = Boolean(args.persistState);
    const persistConfig = useMemo(() => {
        if (!persistStateEnabled) {
            return null;
        }

        return {
            key: persistStateConfig?.key ?? `datatable-state:${args.queryKey}`,
            search: persistStateConfig?.search ?? true,
            filters: persistStateConfig?.filters ?? true,
            sorting: persistStateConfig?.sorting ?? true,
            perPage: persistStateConfig?.perPage ?? true,
            columnVisibility: persistStateConfig?.columnVisibility ?? true,
        };
    }, [
        args.queryKey,
        persistStateConfig?.columnVisibility,
        persistStateConfig?.filters,
        persistStateConfig?.key,
        persistStateConfig?.perPage,
        persistStateConfig?.search,
        persistStateConfig?.sorting,
        persistStateEnabled,
    ]);
    const filterIds = useMemo(
        () => new Set(args.filters.map((filter) => filter.id)),
        [args.filters],
    );
    const columnIds = useMemo(
        () => new Set(args.columnIds ?? []),
        [args.columnIds],
    );

    // Use refs so the restore effect can read the latest values without
    // re-triggering every time filterIds/columnIds get new Set instances
    // (which happens when default empty-array props cause useMemo to re-run).
    const filterIdsRef = useRef(filterIds);
    const columnIdsRef = useRef(columnIds);
    const initialPerPageRef = useRef(args.initialPerPage);
    const initialColumnVisibilityRef = useRef(args.initialColumnVisibility);
    const initialFiltersRef = useRef(args.initialFilters);

    useEffect(() => {
        filterIdsRef.current = filterIds;
        columnIdsRef.current = columnIds;
        initialPerPageRef.current = args.initialPerPage;
        initialColumnVisibilityRef.current = args.initialColumnVisibility;
        initialFiltersRef.current = args.initialFilters;
    }, [
        args.initialColumnVisibility,
        args.initialFilters,
        args.initialPerPage,
        columnIds,
        filterIds,
    ]);

    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(args.initialPerPage);
    const [search, setSearchState] = useState('');
    const [sorting, setSortingState] = useState<SortingState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({
        ...(args.initialColumnVisibility ?? {}),
    });
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [filters, setFilters] = useState<
        Record<string, DataTableFilterValue>
    >({ ...(args.initialFilters ?? {}) });
    const [hasRestoredPersistedState, setHasRestoredPersistedState] = useState(
        () => !persistConfig,
    );
    const skipNextPersistRef = useRef(false);

    useEffect(() => {
        if (!persistConfig) {
            return;
        }

        const timeout = window.setTimeout(() => {
            const restoredState = readPersistedState({
                config: persistConfig,
                filterIds: filterIdsRef.current,
                columnIds: columnIdsRef.current,
            });

            setPage(1);
            setPerPage(restoredState.perPage ?? initialPerPageRef.current);
            setSearchState(restoredState.search ?? '');
            setSortingState(restoredState.sorting ?? []);
            setColumnVisibility({
                ...(initialColumnVisibilityRef.current ?? {}),
                ...(restoredState.columnVisibility ?? {}),
            });
            setFilters(
                restoredState.filters ?? {
                    ...(initialFiltersRef.current ?? {}),
                },
            );
            setHasRestoredPersistedState(true);
        }, 0);

        return () => window.clearTimeout(timeout);
        // Only re-run when persistConfig itself changes (i.e. once on mount).
        // filterIds/columnIds/initialPerPage/initialColumnVisibility are read via
        // refs above so they don't need to be deps here.
    }, [persistConfig]);

    useEffect(() => {
        if (
            !persistConfig ||
            !hasRestoredPersistedState ||
            typeof window === 'undefined'
        ) {
            return;
        }

        if (skipNextPersistRef.current) {
            skipNextPersistRef.current = false;

            return;
        }

        const timeout = window.setTimeout(() => {
            const persistedState: DataTablePersistedState = {};

            if (persistConfig.search) {
                persistedState.search = search;
            }

            if (persistConfig.filters) {
                persistedState.filters = filters;
            }

            if (persistConfig.sorting) {
                persistedState.sorting = sorting;
            }

            if (persistConfig.perPage) {
                persistedState.perPage = perPage;
            }

            if (persistConfig.columnVisibility) {
                persistedState.columnVisibility = columnVisibility;
            }

            try {
                window.localStorage.setItem(
                    persistConfig.key,
                    JSON.stringify(persistedState),
                );
            } catch {
                // Ignore storage quota and browser privacy mode failures.
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [
        columnVisibility,
        filters,
        hasRestoredPersistedState,
        perPage,
        persistConfig,
        search,
        sorting,
    ]);

    function setSearch(value: string): void {
        setPage(1);
        setSearchState(value);
    }

    function setPerPageValue(value: number): void {
        setPage(1);
        setPerPage(value);
    }

    function setSorting(value: SetStateAction<SortingState>): void {
        setPage(1);
        setSortingState(value);
    }

    function setFilterValue(id: string, value: DataTableFilterValue): void {
        setPage(1);
        setFilters((current) => ({
            ...current,
            [id]: value,
        }));
    }

    function resetFilters(): void {
        setPage(1);
        setFilters({ ...(initialFiltersRef.current ?? {}) });
    }

    function resetTable(): void {
        setPage(1);
        setPerPage(initialPerPageRef.current);
        setSearchState('');
        setSortingState([]);
        setColumnVisibility({
            ...(initialColumnVisibilityRef.current ?? {}),
        });
        setFilters({ ...(initialFiltersRef.current ?? {}) });
        setRowSelection({});
        skipNextPersistRef.current = true;

        if (persistConfig && typeof window !== 'undefined') {
            try {
                window.localStorage.removeItem(persistConfig.key);
            } catch {
                // Ignore storage quota and browser privacy mode failures.
            }
        }
    }

    const canResetTable =
        search.length > 0 ||
        sorting.length > 0 ||
        page !== 1 ||
        perPage !== args.initialPerPage ||
        JSON.stringify(filters) !== JSON.stringify(args.initialFilters ?? {}) ||
        !isDefaultColumnVisibility(
            columnVisibility,
            args.initialColumnVisibility,
        );

    return {
        state: {
            page,
            perPage,
            search,
            sorting,
            columnVisibility,
            rowSelection,
            filters,
        },
        setPage,
        setPerPage: setPerPageValue,
        setSearch,
        setSorting,
        setColumnVisibility,
        setRowSelection,
        setFilterValue,
        resetFilters,
        resetTable,
        canResetTable,
    };
}
