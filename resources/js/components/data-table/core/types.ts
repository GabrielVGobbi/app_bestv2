import type {
    ColumnDef,
    Row,
    RowSelectionState,
    SortingState,
    Table,
    VisibilityState,
} from '@tanstack/react-table';
import type { ComponentType, ReactNode } from 'react';

export interface DataTableBulkAction<TData> {
    id: string;
    label: string;
    icon?: ReactNode;
    variant?:
        | 'default'
        | 'outline'
        | 'secondary'
        | 'ghost'
        | 'link'
        | 'destructive';
    onClick: (rows: TData[], clearSelection: () => void) => void;
    disabled?: (rows: TData[]) => boolean;
}

export interface DataTableBulkAggregate<TData> {
    id: string;
    label: string;
    fn: (rows: TData[]) => string | number;
    format?: (value: string | number) => string;
}

export interface DataTableBulkActionsConfig {
    selectedLabel?: string;
}

import type { Paginated, PaginationMeta } from '@/types/pagination';

export type DataTableMode = 'paginated' | 'infinite-scroll';

export type DataTableSize = 'sm' | 'md';

export type DataTableGridLines = 'none' | 'horizontal' | 'vertical' | 'both';

export type DataTableRowStyle = 'plain' | 'striped' | 'soft-striped';

export interface DataTableAppearance {
    size?: DataTableSize;
    gridLines?: DataTableGridLines;
    rowStyle?: DataTableRowStyle;
}

export type DataTableFilterOperator =
    | 'eq'
    | 'contains'
    | 'starts_with'
    | 'in'
    | 'between'
    | 'gte'
    | 'lte'
    | 'null'
    | 'not_null';

export type DataTableFilterPrimitive = string | number | boolean;

export type DataTableRangeValue = {
    from?: string | number;
    to?: string | number;
};

export type DataTableFilterValue =
    | DataTableFilterPrimitive
    | DataTableFilterPrimitive[]
    | DataTableRangeValue
    | null
    | undefined;

export type DataTableFilterType =
    | 'text'
    | 'select'
    | 'multi-select'
    | 'boolean'
    | 'date'
    | 'date-range'
    | 'number'
    | 'number-range'
    | 'async-select';

export interface DataTableFilterOption {
    value: string | number | boolean;
    label: string;
    color?: string;
    count?: number;
}

export interface DataTableFilterConfig {
    id: string;
    label: string;
    title?: string;
    type: DataTableFilterType;
    operator?: DataTableFilterOperator;
    placeholder?: string;
    options?: DataTableFilterOption[];
    icon?: ComponentType<{ className?: string }>;
    multiple?: boolean;
    clearable?: boolean;
}

export interface DataTableAsyncSelectConfig {
    endpoint: string;
    params?: Record<string, string | number | boolean | null | undefined>;
    searchPlaceholder?: string;
    emptyMessage?: string;
}

export interface DataTableQuickFilter {
    id: string;
    label: string;
    type?: 'select' | 'async-select';
    placeholder?: string;
    icon?: ComponentType<{ className?: string }>;
    options?: DataTableFilterOption[];
    asyncSelect?: DataTableAsyncSelectConfig;
    multiple?: boolean;
    clearable?: boolean;
    operator?: DataTableFilterOperator;
}

export interface DataTableDateColumn {
    value: string;
    label: string;
}

export interface DataTableDatePreset {
    value: string;
    label: string;
    getRange: () => { from: Date; to: Date };
}

export interface DataTableFeatures {
    search?: boolean;
    filters?: boolean;
    columnVisibility?: boolean;
    refresh?: boolean;
    selection?: boolean;
    export?: boolean;
}

export interface DataTablePersistStateConfig {
    key?: string;
    search?: boolean;
    filters?: boolean;
    sorting?: boolean;
    perPage?: boolean;
    columnVisibility?: boolean;
}

export interface DataTableExportConfig {
    modelClass: string;
    columnsEndpoint: string;
    enabled?: boolean;
    fixedFilters?: Record<string, unknown>;
}

export interface DataTableAction<TData> {
    label: string;
    render?: (row: TData) => ReactNode;
    href?: (row: TData) => string;
    onClick?: (row: TData) => void;
    icon?: ReactNode;
    variant?:
        | 'default'
        | 'outline'
        | 'secondary'
        | 'ghost'
        | 'link'
        | 'destructive';
    disabled?: (row: TData) => boolean;
    showLabel?: boolean;
    tooltip?: string | boolean;
    prefetch?: boolean;
}

export interface DataTableActionsConfig {
    display?: 'menu' | 'inline';
    label?: string;
    buttonSize?: 'sm' | 'default' | 'icon';
    showLabels?: boolean;
    showTooltips?: boolean;
    /** Override the auto-calculated column width in pixels. */
    columnSize?: number;
    /** Pin the actions column to the right edge during horizontal scroll. Default: false. */
    sticky?: boolean;
}

export interface DataTableInfiniteScrollConfig {
    height?: string;
    fitViewport?: boolean;
    viewportBottomOffset?: number;
    minHeight?: number;
    loadMoreThreshold?: string;
}

export interface DataTableState {
    page: number;
    perPage: number;
    search: string;
    sorting: SortingState;
    columnVisibility: VisibilityState;
    rowSelection: RowSelectionState;
    filters: Record<string, DataTableFilterValue>;
}

export interface DataTableQueryParams {
    page?: number;
    perPage?: number;
    search?: string;
    sort?: string;
    direction?: 'asc' | 'desc';
    filters?: Record<string, DataTableFilterValue>;
    filterConfig?: DataTableFilterConfig[];
}

export interface DataTableProps<TData> {
    queryKey: string;
    endpoint: string;
    mode?: DataTableMode;
    columns: ColumnDef<TData>[];
    keyExtractor: (row: TData) => string | number;
    features?: DataTableFeatures;
    filters?: DataTableFilterConfig[];
    quickFilters?: DataTableQuickFilter[];
    dateFilterColumns?: DataTableDateColumn[];
    dateFilterPresets?: DataTableDatePreset[];
    actions?: DataTableAction<TData>[];
    actionsConfig?: DataTableActionsConfig;
    bulkActions?: DataTableBulkAction<TData>[];
    bulkAggregates?: DataTableBulkAggregate<TData>[];
    bulkActionsConfig?: DataTableBulkActionsConfig;
    infiniteScrollConfig?: DataTableInfiniteScrollConfig;
    appearance?: DataTableAppearance;
    size?: DataTableSize;
    gridLines?: DataTableGridLines;
    rowStyle?: DataTableRowStyle;
    initialPerPage?: number;
    initialFilters?: Record<string, DataTableFilterValue>;
    searchPlaceholder?: string;
    emptyMessage?: string;
    exportConfig?: DataTableExportConfig;
    persistState?: boolean | DataTablePersistStateConfig;
}

export interface DataTableShellProps<TData> {
    table: Table<TData>;
    rows: Row<TData>[];
    isLoading: boolean;
    isFetching: boolean;
    error: Error | null;
    meta?: PaginationMeta;
    state: DataTableState;
    features: DataTableFeatures;
    filterConfig: DataTableFilterConfig[];
    quickFilters: DataTableQuickFilter[];
    dateFilterColumns: DataTableDateColumn[];
    dateFilterPresets?: DataTableDatePreset[];
    searchPlaceholder: string;
    emptyMessage: string;
    mode: DataTableMode;
    infiniteScrollConfig?: DataTableInfiniteScrollConfig;
    hasMore?: boolean;
    isFetchingMore?: boolean;
    loadMoreRef?: (node: HTMLDivElement | null) => void;
    bulkActions?: DataTableBulkAction<TData>[];
    bulkAggregates?: DataTableBulkAggregate<TData>[];
    bulkActionsConfig?: DataTableBulkActionsConfig;
    appearance: Required<DataTableAppearance>;
    selectedRows?: TData[];
    selectedCount?: number;
    onClearSelection?: () => void;
    onSearchChange: (value: string) => void;
    onFilterChange: (id: string, value: DataTableFilterValue) => void;
    onResetFilters: () => void;
    onResetTable: () => void;
    canResetTable: boolean;
    onClearSearch: () => void;
    onRefresh: () => void;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    exportConfig?: DataTableExportConfig;
}

export interface DataTableHookResult<TData> {
    data?: Paginated<TData>;
    rows: TData[];
    meta?: PaginationMeta;
    isLoading: boolean;
    isFetching: boolean;
    error: Error | null;
    refresh: () => void;
}
