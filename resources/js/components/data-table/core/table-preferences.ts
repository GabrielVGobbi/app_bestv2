import type {
    ColumnOrderState,
    ColumnPinningState,
    ColumnSizingState,
    SortingState,
    VisibilityState,
} from '@tanstack/react-table';
import type {
    DataTableFilterValue,
    DataTablePersistStateConfig,
    DataTableSize,
} from './types';

export type TablePreferencesStorageStatus =
    | 'disabled'
    | 'available'
    | 'saved'
    | 'unavailable'
    | 'invalid';

export interface TablePreferences {
    search?: string;
    filters?: Record<string, DataTableFilterValue>;
    sorting?: SortingState;
    perPage?: number;
    columnVisibility?: VisibilityState;
    columnOrder?: ColumnOrderState;
    columnPinning?: ColumnPinningState;
    columnSizing?: ColumnSizingState;
    density?: DataTableSize;
}

interface PreferenceScope {
    userId: number | null;
    isCurrent: () => boolean;
}

export interface TablePreferencesConfig {
    userId: number;
    key: string;
    legacyKey: string;
    search: boolean;
    filters: boolean;
    sorting: boolean;
    perPage: boolean;
    columnVisibility: boolean;
    layout: boolean;
    density: boolean;
}

interface PreferencesContext {
    config: TablePreferencesConfig | null;
    scope: PreferenceScope;
    getStorage: () => Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;
    columnIds: string[];
    filterIds: string[];
    initialColumnVisibility?: VisibilityState;
    initialColumnPinning?: ColumnPinningState;
    nonHideableColumnIds?: string[];
}

const protectedColumns = new Set(['select', 'actions']);

function isPlainRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isFilterValue(value: unknown): value is DataTableFilterValue {
    if (
        value === null ||
        value === undefined ||
        typeof value === 'string' ||
        typeof value === 'boolean' ||
        (typeof value === 'number' && Number.isFinite(value))
    ) {
        return true;
    }

    if (Array.isArray(value)) {
        return value.every(
            (item) =>
                typeof item === 'string' ||
                typeof item === 'boolean' ||
                (typeof item === 'number' && Number.isFinite(item)),
        );
    }

    return (
        isPlainRecord(value) &&
        ['from', 'to'].some((key) => key in value) &&
        Object.keys(value).every((key) => key === 'from' || key === 'to') &&
        [value.from, value.to].every(
            (item) =>
                item === undefined ||
                typeof item === 'string' ||
                (typeof item === 'number' && Number.isFinite(item)),
        )
    );
}

export function resolveTablePreferencesConfig(args: {
    scope: PreferenceScope;
    queryKey: string;
    tableId?: string;
    persistState?: boolean | DataTablePersistStateConfig;
}): TablePreferencesConfig | null {
    if (!args.persistState || args.scope.userId === null) {
        return null;
    }

    const config =
        typeof args.persistState === 'object' ? args.persistState : undefined;

    return {
        userId: args.scope.userId,
        key: `datatable-preferences:v1:${args.scope.userId}:${args.tableId ?? config?.key ?? args.queryKey}`,
        legacyKey: `private-state:${args.scope.userId}:${config?.key ?? `datatable-state:${args.queryKey}`}`,
        search: config?.search ?? true,
        filters: config?.filters ?? true,
        sorting: config?.sorting ?? true,
        perPage: config?.perPage ?? true,
        columnVisibility: config?.columnVisibility ?? true,
        layout: config?.layout ?? true,
        density: config?.density ?? true,
    };
}

export function sanitizeColumnVisibility(
    value: unknown,
    columnIds: string[],
    defaults: VisibilityState = {},
    nonHideableColumnIds: string[] = [],
): VisibilityState {
    const candidates = { ...defaults, ...(isPlainRecord(value) ? value : {}) };
    const visibility = Object.fromEntries(
        Object.entries(candidates).filter(
            (entry): entry is [string, boolean] =>
                columnIds.includes(entry[0]) && typeof entry[1] === 'boolean',
        ),
    );

    for (const id of columnIds) {
        if (protectedColumns.has(id) || nonHideableColumnIds.includes(id)) {
            visibility[id] = true;
        }
    }

    const dataColumns = columnIds.filter((id) => !protectedColumns.has(id));

    if (
        dataColumns.length > 0 &&
        dataColumns.every((id) => visibility[id] === false)
    ) {
        visibility[dataColumns[0]] = true;
    }

    return visibility;
}

export function sanitizeColumnOrder(
    value: unknown,
    columnIds: string[],
): ColumnOrderState {
    const requested = Array.isArray(value)
        ? value.filter(
              (id): id is string =>
                  typeof id === 'string' && columnIds.includes(id),
          )
        : [];
    const dataColumns = [...new Set([...requested, ...columnIds])].filter(
        (id) => !protectedColumns.has(id),
    );

    return [
        ...(columnIds.includes('select') ? ['select'] : []),
        ...dataColumns,
        ...(columnIds.includes('actions') ? ['actions'] : []),
    ];
}

export function sanitizeColumnPinning(
    value: unknown,
    columnIds: string[],
    defaults: ColumnPinningState = {},
): ColumnPinningState {
    const candidate = isPlainRecord(value) ? value : {};
    const pinned = new Set<string>();
    const side = (items: unknown): string[] =>
        Array.isArray(items)
            ? items.filter((id): id is string => {
                  if (
                      typeof id !== 'string' ||
                      !columnIds.includes(id) ||
                      protectedColumns.has(id) ||
                      pinned.has(id)
                  ) {
                      return false;
                  }

                  pinned.add(id);

                  return true;
              })
            : [];

    return {
        left: [
            ...(columnIds.includes('select') &&
            defaults.left?.includes('select')
                ? ['select']
                : []),
            ...side(candidate.left),
        ],
        right: [
            ...side(candidate.right),
            ...(columnIds.includes('actions') &&
            defaults.right?.includes('actions')
                ? ['actions']
                : []),
        ],
    };
}

export function sanitizeColumnSizing(
    value: unknown,
    columnIds: string[],
): ColumnSizingState {
    return Object.fromEntries(
        Object.entries(isPlainRecord(value) ? value : {}).filter(
            (entry): entry is [string, number] => {
                const [id, width] = entry;

                return (
                    columnIds.includes(id) &&
                    !protectedColumns.has(id) &&
                    typeof width === 'number' &&
                    Number.isFinite(width) &&
                    width > 0
                );
            },
        ),
    );
}

function sanitizePreferences(
    value: Record<string, unknown>,
    context: PreferencesContext,
): TablePreferences {
    const config = context.config;
    const preferences: TablePreferences = {};

    if (!config) {
        return preferences;
    }

    if (config.search && typeof value.search === 'string') {
        preferences.search = value.search;
    }

    if (
        config.perPage &&
        typeof value.perPage === 'number' &&
        Number.isInteger(value.perPage) &&
        value.perPage > 0
    ) {
        preferences.perPage = value.perPage;
    }

    if (config.sorting && Array.isArray(value.sorting)) {
        preferences.sorting = value.sorting
            .filter(
                (item): item is SortingState[number] =>
                    isPlainRecord(item) &&
                    typeof item.id === 'string' &&
                    typeof item.desc === 'boolean' &&
                    context.columnIds.includes(item.id),
            )
            .map(({ id, desc }) => ({ id, desc }));
    }

    if (config.filters && isPlainRecord(value.filters)) {
        preferences.filters = Object.fromEntries(
            Object.entries(value.filters).filter(
                (entry): entry is [string, DataTableFilterValue] =>
                    context.filterIds.includes(entry[0]) &&
                    isFilterValue(entry[1]),
            ),
        );
    }

    if (config.columnVisibility && isPlainRecord(value.columnVisibility)) {
        preferences.columnVisibility = sanitizeColumnVisibility(
            value.columnVisibility,
            context.columnIds,
            context.initialColumnVisibility,
            context.nonHideableColumnIds,
        );
    }

    if (config.layout) {
        if (Array.isArray(value.columnOrder)) {
            preferences.columnOrder = sanitizeColumnOrder(
                value.columnOrder,
                context.columnIds,
            );
        }

        if (isPlainRecord(value.columnPinning)) {
            preferences.columnPinning = sanitizeColumnPinning(
                value.columnPinning,
                context.columnIds,
                context.initialColumnPinning,
            );
        }

        if (isPlainRecord(value.columnSizing)) {
            preferences.columnSizing = sanitizeColumnSizing(
                value.columnSizing,
                context.columnIds,
            );
        }
    }

    if (config.density && (value.density === 'sm' || value.density === 'md')) {
        preferences.density = value.density;
    }

    return preferences;
}

function isCurrent(context: PreferencesContext): boolean {
    return Boolean(
        context.config &&
        context.scope.userId === context.config.userId &&
        context.scope.isCurrent(),
    );
}

export function writeTablePreferences(
    context: PreferencesContext & { preferences: TablePreferences },
): TablePreferencesStorageStatus {
    if (!isCurrent(context) || !context.config) {
        return 'disabled';
    }

    try {
        const storage = context.getStorage();
        const preferences = sanitizePreferences(
            context.preferences as Record<string, unknown>,
            context,
        );

        if (!isCurrent(context)) {
            return 'disabled';
        }

        storage.setItem(
            context.config.key,
            JSON.stringify({ version: 1, ...preferences }),
        );

        return 'saved';
    } catch {
        return 'unavailable';
    }
}

export function readTablePreferences(context: PreferencesContext): {
    preferences: TablePreferences;
    storageStatus: TablePreferencesStorageStatus;
} {
    if (!isCurrent(context) || !context.config) {
        return { preferences: {}, storageStatus: 'disabled' };
    }

    try {
        const storage = context.getStorage();
        const current = storage.getItem(context.config.key);
        const raw = current ?? storage.getItem(context.config.legacyKey);

        if (raw === null) {
            return { preferences: {}, storageStatus: 'available' };
        }

        let parsed: unknown;

        try {
            parsed = JSON.parse(raw);
        } catch {
            return { preferences: {}, storageStatus: 'invalid' };
        }

        if (
            !isPlainRecord(parsed) ||
            ((current !== null || 'version' in parsed) && parsed.version !== 1)
        ) {
            return { preferences: {}, storageStatus: 'invalid' };
        }

        if (!isCurrent(context)) {
            return { preferences: {}, storageStatus: 'disabled' };
        }

        const preferences = sanitizePreferences(parsed, context);

        if (current !== null) {
            return { preferences, storageStatus: 'available' };
        }

        const storageStatus = writeTablePreferences({
            ...context,
            preferences,
        });

        if (storageStatus === 'saved' && isCurrent(context)) {
            try {
                storage.removeItem(context.config.legacyKey);
            } catch {
                return { preferences, storageStatus: 'unavailable' };
            }
        }

        return { preferences, storageStatus };
    } catch {
        return { preferences: {}, storageStatus: 'unavailable' };
    }
}
