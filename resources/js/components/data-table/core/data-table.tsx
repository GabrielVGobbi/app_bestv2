import { Link, router } from '@inertiajs/react';
import type { ColumnDef, VisibilityState } from '@tanstack/react-table';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { MoreHorizontal } from 'lucide-react';
import type { ReactElement, ReactNode } from 'react';
import { Fragment, isValidElement, useMemo, useRef } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownContent,
    DropdownMenu,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import type { IconComponent } from '@/components/ui/fluid/lib/icon-context';
import { MenuItem } from '@/components/ui/fluid/menu-item';
import { Tooltip } from '@/components/ui/fluid/tooltip';

import { usePrivateState } from '@/contexts/private-state-context';
import { cn } from '@/lib/utils';
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

// Stable empty-array references: prevents new array instances on every render
// which would cause useMemo/useEffect deps to fire unnecessarily.
const EMPTY_ARRAY: never[] = [];

/**
 * O MenuItem do Fluid recebe o componente do ícone para controlar tamanho, traço
 * e cor. shortcut: usa o tipo do elemento (ícones Lucide); outro nó vira linha sem ícone.
 */
function menuIcon(icon: ReactNode): IconComponent | undefined {
    return isValidElement(icon) && typeof icon.type !== 'string'
        ? (icon.type as IconComponent)
        : undefined;
}

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

type RowActionVariant = NonNullable<DataTableAction<unknown>['variant']>;

/** Ações só com ícone nunca ficam vermelhas em repouso: o vermelho aparece em hover e foco. */
function rowActionVariant(
    action: { variant?: RowActionVariant },
    showLabel: boolean,
): { variant: RowActionVariant; className?: string } {
    if (!showLabel && action.variant === 'destructive') {
        return {
            variant: 'ghost',
            className:
                'hover:bg-destructive-soft hover:text-destructive-soft-foreground focus-visible:bg-destructive-soft focus-visible:text-destructive-soft-foreground',
        };
    }

    if (!showLabel && action.variant === 'outline') {
        return { variant: 'ghost' };
    }

    return { variant: action.variant ?? 'ghost' };
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
            <Tooltip key={action.label} content={tooltip}>
                {actionButton as ReactElement}
            </Tooltip>
        );
    }

    return (
        <div className="flex items-center justify-end gap-1.5">
            {actions.map((action) => {
                if (action.render) {
                    return (
                        <Fragment key={action.label}>
                            {action.render(row)}
                        </Fragment>
                    );
                }

                const disabled = action.disabled?.(row) ?? false;
                const showLabel = shouldShowLabel(action);
                const actionSize = actionButtonSize(showLabel);
                const actionVariant = rowActionVariant(action, showLabel);
                const actionClassName = cn(
                    actionSize === 'icon' &&
                        'size-8 shrink-0 rounded-[6px] shadow-none',
                    actionVariant.className,
                );

                if (action.href && !disabled) {
                    return renderWithTooltip(
                        action,
                        <Button
                            key={action.label}
                            type="button"
                            variant={actionVariant.variant}
                            className={actionClassName}
                            size={actionSize}
                            asChild
                            aria-label={action.label}
                        >
                            <Link
                                draggable={false}
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
                        variant={actionVariant.variant}
                        className={actionClassName}
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
            <DropdownTrigger
                render={
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Acoes"
                    >
                        <MoreHorizontal />
                    </Button>
                }
            />
            <DropdownContent align="end" className="w-56">
                {actions.map((action, index) => {
                    const disabled = action.disabled?.(row) ?? false;

                    return (
                        <MenuItem
                            key={action.label}
                            index={index}
                            icon={menuIcon(action.icon)}
                            label={action.label}
                            disabled={disabled}
                            destructive={action.variant === 'destructive'}
                            onSelect={() =>
                                action.href
                                    ? router.visit(action.href(row))
                                    : action.onClick?.(row)
                            }
                        />
                    );
                })}
            </DropdownContent>
        </DropdownMenu>
    );
}

export function DataTable<TData>(props: DataTableProps<TData>) {
    const { scope } = usePrivateState();

    return (
        <DataTableContent
            key={JSON.stringify([
                scope.userId,
                scope.generation,
                props.tableId,
                props.queryKey,
                props.endpoint,
            ])}
            {...props}
        />
    );
}

function DataTableContent<TData>({
    queryKey,
    tableId,
    endpoint,
    mode = 'infinite-scroll',
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
    selectionResolver,
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
        gridLines: gridLines ?? appearance?.gridLines ?? 'both',
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
                enableHiding: false,
                size: 44,
                minSize: 44,
                maxSize: 44,
                enableSorting: false,
                enableResizing: false,
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
            const gapPx = 6; // gap-1.5
            const cellPaddingPx = 32; // px-4 on both sides at the default density

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
                enableHiding: false,
                size: actionsConfig?.columnSize ?? autoSize,
                minSize: actionsConfig?.columnSize ?? autoSize,
                maxSize: actionsConfig?.columnSize ?? autoSize,
                enableSorting: false,
                enableResizing: false,
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
                    label: actionsConfig?.label ?? 'Acoes',
                    type: 'actions',
                    sticky:
                        (actionsConfig?.sticky ?? true) ? 'right' : undefined,
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
        tableId,
        initialPerPage,
        initialDensity: resolvedAppearance.size,
        initialColumnPinning: {
            left: activeFeatures.selection ? ['select'] : [],
            right: (actionsConfig?.sticky ?? true) ? ['actions'] : [],
        },
        initialColumnVisibility,
        initialFilters,
        filters: allFilters,
        columnIds,
        nonHideableColumnIds: tableColumns
            .filter((column) => column.enableHiding === false)
            .map(
                (column) =>
                    column.id ??
                    ('accessorKey' in column ? String(column.accessorKey) : ''),
            )
            .filter(Boolean),
        persistState,
    });

    const paginatedQuery = useDataTableQuery<TData>({
        queryKey,
        endpoint,
        state: tableState.state,
        filters: allFilters,
        enabled: mode === 'paginated' && tableState.ready,
        ready: tableState.ready,
    });

    const infiniteQuery = useDataTableInfiniteQuery<TData>({
        queryKey,
        endpoint,
        state: tableState.state,
        filters: allFilters,
        enabled: mode === 'infinite-scroll' && tableState.ready,
        ready: tableState.ready,
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

    const selection = useDataTableSelection<TData>({
        storageKey: `datatable-selection:${queryKey}`,
        keyExtractor,
        currentRows: query.rows,
        rowSelection: tableState.state.rowSelection,
        setRowSelection: tableState.setRowSelection,
        enabled: activeFeatures.selection && hasBulkActions,
        selectionResolver,
    });

    const table = useReactTable({
        data: query.rows,
        columns: tableColumns,
        getCoreRowModel: getCoreRowModel(),
        getRowId: (row) => String(keyExtractor(row)),
        manualPagination: true,
        manualSorting: true,
        enableColumnResizing: true,
        columnResizeMode: 'onEnd',
        defaultColumn: { minSize: 60, maxSize: 1200 },
        enableRowSelection: activeFeatures.selection,
        state: {
            sorting: tableState.state.sorting,
            columnVisibility: tableState.state.columnVisibility,
            columnOrder: tableState.state.columnOrder,
            columnPinning: tableState.state.columnPinning,
            columnSizing: tableState.state.columnSizing,
            rowSelection: selection.rowSelection,
        },
        onSortingChange: tableState.setSorting,
        onColumnVisibilityChange: tableState.setColumnVisibility,
        onColumnOrderChange: tableState.setColumnOrder,
        onColumnPinningChange: tableState.setColumnPinning,
        onColumnSizingChange: tableState.setColumnSizing,
        onRowSelectionChange: tableState.setRowSelection,
    });

    return (
        <DataTableShell
            table={table}
            headerGroups={table.getHeaderGroups()}
            tableWidth={table.getTotalSize()}
            rows={table.getRowModel().rows}
            isLoading={!tableState.ready || query.isLoading}
            isFetching={query.isFetching}
            error={query.error}
            meta={query.meta}
            state={{
                ...tableState.state,
                rowSelection: selection.rowSelection,
            }}
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
            appearance={{
                ...resolvedAppearance,
                size: tableState.state.density,
            }}
            selectedRows={selection.selectedRows}
            selectedCount={selection.selectedCount}
            onClearSelection={selection.clearSelection}
            selectionStatus={selection.status}
            onSearchChange={tableState.setSearch}
            onFilterChange={tableState.setFilterValue}
            onResetFilters={tableState.resetFilters}
            onClearSearch={() => tableState.setSearch('')}
            onResetLayout={tableState.resetLayout}
            onResetAll={tableState.resetAll}
            onDensityChange={tableState.setDensity}
            storageStatus={tableState.storageStatus}
            onRefresh={query.refresh}
            onPageChange={tableState.setPage}
            onPerPageChange={tableState.setPerPage}
            exportConfig={exportConfig}
        />
    );
}
