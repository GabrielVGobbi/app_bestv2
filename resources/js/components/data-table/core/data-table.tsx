import { Link } from '@inertiajs/react';
import type { ColumnDef, VisibilityState } from '@tanstack/react-table';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useRef } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

import { useDataTableInfiniteQuery } from '../hooks/use-data-table-infinite-query';
import { useDataTableQuery } from '../hooks/use-data-table-query';
import { useDataTableSelection } from '../hooks/use-data-table-selection';
import { useDataTableState } from '../hooks/use-data-table-state';
import { useIntersectionLoadMore } from '../hooks/use-intersection-load-more';
import { DataTableShell } from '../shell/data-table-shell';
import type {
    DataTableAction,
    DataTableActionsConfig,
    DataTableBulkAction,
    DataTableBulkAggregate,
    DataTableDateColumn,
    DataTableProps,
    DataTableQuickFilter,
} from './types';

// Stable empty-array references — prevents new array instances on every render
// which would cause useMemo/useEffect deps to fire unnecessarily.
const EMPTY_ARRAY: never[] = [];

function getInitialColumnVisibility<TData>(
    columns: ColumnDef<TData>[],
): VisibilityState {
    return columns.reduce<VisibilityState>((visibility, column) => {
        const columnId =
            column.id ??
            ('accessorKey' in column && typeof column.accessorKey === 'string'
                ? column.accessorKey
                : undefined);

        if (columnId && column.meta?.initialVisibility === false) {
            visibility[columnId] = false;
        }

        return visibility;
    }, {});
}

function renderInlineActions<TData>(
    row: TData,
    actions: Array<DataTableAction<TData>>,
    config?: DataTableActionsConfig,
) {
    const defaultShowLabels = config?.showLabels ?? true;
    const defaultShowTooltips = config?.showTooltips;

    function shouldShowLabel(action: DataTableAction<TData>): boolean {
        return action.showLabel ?? defaultShowLabels;
    }

    function tooltipText(
        action: DataTableAction<TData>,
        showLabel: boolean,
    ): string | null {
        if (typeof action.tooltip === 'string') {
            return action.tooltip;
        }

        const showTooltip = action.tooltip ?? defaultShowTooltips ?? !showLabel;

        return showTooltip ? action.label : null;
    }

    function actionButtonSize(showLabel: boolean) {
        return config?.buttonSize ?? (showLabel ? 'sm' : 'icon');
    }

    function renderWithTooltip(
        action: DataTableAction<TData>,
        actionButton: ReactNode,
        showLabel: boolean,
    ) {
        const tooltip = tooltipText(action, showLabel);

        if (!tooltip) {
            return actionButton;
        }

        return (
            <Tooltip key={action.label}>
                <TooltipTrigger asChild>{actionButton}</TooltipTrigger>
                <TooltipContent>{tooltip}</TooltipContent>
            </Tooltip>
        );
    }

    return (
        <div className="flex items-center justify-end gap-2.5">
            {actions.map((action) => {
                const disabled = action.disabled?.(row) ?? false;
                const showLabel = shouldShowLabel(action);
                const actionSize = actionButtonSize(showLabel);

                if (action.render) {
                    return <div key={action.label}>{action.render(row)}</div>;
                }

                if (action.href && !disabled) {
                    return renderWithTooltip(
                        action,
                        <Button
                            key={action.label}
                            type="button"
                            variant={action.variant ?? 'ghost'}
                            size={actionSize}
                            asChild
                            aria-label={action.label}
                        >
                            <Link
                                href={action.href(row)}
                                prefetch={action.prefetch}
                            >
                                {action.icon}
                                {showLabel && action.label}
                            </Link>
                        </Button>,
                        showLabel,
                    );
                }

                return renderWithTooltip(
                    action,
                    <Button
                        key={action.label}
                        type="button"
                        variant={action.variant ?? 'ghost'}
                        size={actionSize}
                        disabled={disabled}
                        onClick={() => action.onClick?.(row)}
                        aria-label={action.label}
                    >
                        {action.icon}
                        {showLabel && action.label}
                    </Button>,
                    showLabel,
                );
            })}
        </div>
    );
}

function renderMenuActions<TData>(
    row: TData,
    actions: Array<DataTableAction<TData>>,
) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Ações"
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {actions.map((action) => {
                    const disabled = action.disabled?.(row) ?? false;
                    const content = (
                        <>
                            {action.icon}
                            {action.label}
                        </>
                    );

                    if (action.render) {
                        return (
                            <div key={action.label}>{action.render(row)}</div>
                        );
                    }

                    if (action.href && !disabled) {
                        return (
                            <DropdownMenuItem key={action.label} asChild>
                                <Link
                                    href={action.href(row)}
                                    prefetch={action.prefetch}
                                >
                                    {content}
                                </Link>
                            </DropdownMenuItem>
                        );
                    }

                    return (
                        <DropdownMenuItem
                            key={action.label}
                            disabled={disabled}
                            onClick={() => action.onClick?.(row)}
                        >
                            {content}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function DataTable<TData>({
    queryKey,
    endpoint,
    mode = 'paginated',
    columns,
    keyExtractor,
    features = {},
    filters = [],
    quickFilters = EMPTY_ARRAY as DataTableQuickFilter[],
    dateFilterColumns = EMPTY_ARRAY as DataTableDateColumn[],
    dateFilterPresets,
    actions = EMPTY_ARRAY as DataTableAction<TData>[],
    actionsConfig,
    bulkActions = EMPTY_ARRAY as DataTableBulkAction<TData>[],
    bulkAggregates = EMPTY_ARRAY as DataTableBulkAggregate<TData>[],
    bulkActionsConfig,
    infiniteScrollConfig,
    appearance,
    size,
    gridLines,
    rowStyle,
    initialPerPage = mode === 'infinite-scroll' ? 30 : 15,
    initialFilters,
    searchPlaceholder = 'Buscar...',
    emptyMessage = 'Nenhum registro encontrado.',
    exportConfig,
    persistState = true,
}: DataTableProps<TData>) {
    const activeFeatures = {
        search: true,
        filters: false,
        columnVisibility: true,
        refresh: true,
        selection: false,
        export: false,
        ...features,
    };

    const resolvedAppearance = {
        size: size ?? appearance?.size ?? 'md',
        gridLines: gridLines ?? appearance?.gridLines ?? 'horizontal',
        rowStyle: rowStyle ?? appearance?.rowStyle ?? 'plain',
    } as const;

    const dateFilters = useMemo(
        () =>
            dateFilterColumns.map((column) => ({
                id: column.value,
                label: column.label,
                type: 'date-range' as const,
                operator: 'between' as const,
            })),
        [dateFilterColumns],
    );

    const allFilters = useMemo(
        () => [...quickFilters, ...filters, ...dateFilters],
        [dateFilters, filters, quickFilters],
    );

    // Tracks whether the current selection was initiated by the header checkbox.
    // This ensures a second header click always clears the full selection, even
    // when the state is `indeterminate` (e.g. after new rows load via infinite scroll).
    const headerSelectionActiveRef = useRef(false);

    const tableColumns = useMemo<ColumnDef<TData>[]>(() => {
        const nextColumns = [...columns];

        if (activeFeatures.selection) {
            nextColumns.unshift({
                id: 'select',
                size: 44,
                enableSorting: false,
                enableResizing: false,
                enableHiding: false,
                header: ({ table }) => {
                    const rowSelection = table.getState().rowSelection;
                    const rows = table.getRowModel().rows;
                    const selectedOnPage = rows.filter(
                        (row) => rowSelection[row.id] === true,
                    ).length;
                    const totalOnPage = rows.length;
                    const headerChecked: boolean | 'indeterminate' =
                        totalOnPage > 0 && selectedOnPage > 0
                            ? selectedOnPage === totalOnPage
                                ? true
                                : 'indeterminate'
                            : false;

                    return (
                        <Checkbox
                            checked={headerChecked}
                            onCheckedChange={(value) => {
                                const hasSelection = Object.values(
                                    table.getState().rowSelection,
                                ).some(Boolean);

                                // Deselect everything when:
                                // - the checkbox is explicitly unchecked (value === false), OR
                                // - the header initiated the selection and rows are still selected
                                //   (handles the indeterminate → clear case after infinite scroll loads)
                                if (
                                    value === false ||
                                    (headerSelectionActiveRef.current &&
                                        hasSelection)
                                ) {
                                    headerSelectionActiveRef.current = false;
                                    table.setRowSelection({});

                                    return;
                                }

                                headerSelectionActiveRef.current = true;
                                table.toggleAllPageRowsSelected(true);
                            }}
                            aria-label="Selecionar todos"
                        />
                    );
                },
                cell: ({ row }) => (
                    <Checkbox
                        checked={row.getIsSelected()}
                        onCheckedChange={(value) =>
                            row.toggleSelected(Boolean(value))
                        }
                        aria-label="Selecionar linha"
                    />
                ),
                meta: {
                    align: 'center',
                    label: 'Selecionar',
                },
            });
        }

        if (actions.length > 0) {
            const actionDisplay = actionsConfig?.display ?? 'menu';

            // Per-button width by size variant (button px + gap between buttons)
            const buttonPx =
                actionsConfig?.buttonSize === 'icon'
                    ? 32
                    : actionsConfig?.buttonSize === 'sm'
                      ? 56
                      : 80;
            const gapPx = 10;
            const cellPaddingPx = 32;

            const autoSize =
                actionDisplay === 'inline'
                    ? Math.max(
                          80,
                          actions.length * buttonPx +
                              Math.max(0, actions.length - 1) * gapPx +
                              cellPaddingPx,
                      )
                    : 52;

            nextColumns.push({
                id: 'actions',
                size: actionsConfig?.columnSize ?? autoSize,
                enableSorting: false,
                enableResizing: false,
                enableHiding: false,
                header: actionsConfig?.label ?? '',
                cell: ({ row }) =>
                    actionDisplay === 'inline'
                        ? renderInlineActions(
                              row.original,
                              actions,
                              actionsConfig,
                          )
                        : renderMenuActions(row.original, actions),
                meta: {
                    align: 'right',
                    label: actionsConfig?.label ?? 'Ações',
                    type: 'actions',
                    sticky: actionsConfig?.sticky ? 'right' : undefined,
                },
            });
        }

        return nextColumns;
    }, [actions, actionsConfig, activeFeatures.selection, columns]);

    const initialColumnVisibility = useMemo(
        () => getInitialColumnVisibility(tableColumns),
        [tableColumns],
    );

    const columnIds = useMemo(
        () =>
            tableColumns
                .map(
                    (column) =>
                        column.id ??
                        ('accessorKey' in column &&
                        typeof column.accessorKey === 'string'
                            ? column.accessorKey
                            : undefined),
                )
                .filter((columnId): columnId is string => Boolean(columnId)),
        [tableColumns],
    );

    const tableState = useDataTableState({
        queryKey,
        initialPerPage,
        initialColumnVisibility,
        initialFilters,
        filters: allFilters,
        columnIds,
        persistState,
    });

    const paginatedQuery = useDataTableQuery<TData>({
        queryKey,
        endpoint,
        state: tableState.state,
        filters: allFilters,
        enabled: mode === 'paginated',
    });

    const infiniteQuery = useDataTableInfiniteQuery<TData>({
        queryKey,
        endpoint,
        state: tableState.state,
        filters: allFilters,
        enabled: mode === 'infinite-scroll',
    });

    const query = mode === 'infinite-scroll' ? infiniteQuery : paginatedQuery;

    const loadMoreRef = useIntersectionLoadMore({
        enabled: mode === 'infinite-scroll',
        hasMore: infiniteQuery.hasMore,
        isFetchingMore: infiniteQuery.isFetchingMore,
        rootMargin: infiniteScrollConfig?.loadMoreThreshold,
        onLoadMore: infiniteQuery.fetchNextPage,
    });

    const hasBulkActions = bulkActions.length > 0;

    const table = useReactTable({
        data: query.rows,
        columns: tableColumns,
        getCoreRowModel: getCoreRowModel(),
        getRowId: (row) => String(keyExtractor(row)),
        manualPagination: true,
        manualSorting: true,
        enableColumnResizing: true,
        columnResizeMode: 'onChange',
        enableRowSelection: activeFeatures.selection,
        state: {
            sorting: tableState.state.sorting,
            columnVisibility: tableState.state.columnVisibility,
            rowSelection: tableState.state.rowSelection,
        },
        onSortingChange: tableState.setSorting,
        onColumnVisibilityChange: tableState.setColumnVisibility,
        onRowSelectionChange: tableState.setRowSelection,
    });

    const { selectedRows, selectedCount, clearSelection } =
        useDataTableSelection<TData>({
            storageKey: `datatable-selection:${queryKey}`,
            keyExtractor,
            currentRows: query.rows,
            rowSelection: tableState.state.rowSelection,
            setRowSelection: tableState.setRowSelection,
            enabled: activeFeatures.selection && hasBulkActions,
        });

    return (
        <DataTableShell
            table={table}
            rows={table.getRowModel().rows}
            isLoading={query.isLoading}
            isFetching={query.isFetching}
            error={query.error}
            meta={query.meta}
            state={tableState.state}
            features={activeFeatures}
            filterConfig={filters}
            quickFilters={quickFilters}
            dateFilterColumns={dateFilterColumns}
            dateFilterPresets={dateFilterPresets}
            searchPlaceholder={searchPlaceholder}
            emptyMessage={emptyMessage}
            mode={mode}
            infiniteScrollConfig={infiniteScrollConfig}
            hasMore={infiniteQuery.hasMore}
            isFetchingMore={infiniteQuery.isFetchingMore}
            loadMoreRef={loadMoreRef}
            bulkActions={hasBulkActions ? bulkActions : undefined}
            bulkAggregates={bulkAggregates}
            bulkActionsConfig={bulkActionsConfig}
            appearance={resolvedAppearance}
            selectedRows={selectedRows}
            selectedCount={selectedCount}
            onClearSelection={clearSelection}
            onSearchChange={tableState.setSearch}
            onFilterChange={tableState.setFilterValue}
            onResetFilters={tableState.resetFilters}
            onResetTable={tableState.resetTable}
            canResetTable={tableState.canResetTable}
            onClearSearch={() => tableState.setSearch('')}
            onRefresh={query.refresh}
            onPageChange={tableState.setPage}
            onPerPageChange={tableState.setPerPage}
            exportConfig={exportConfig}
        />
    );
}
