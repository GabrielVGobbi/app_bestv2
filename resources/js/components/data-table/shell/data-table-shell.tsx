import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Card, CardContent } from '@/components/ui/card';

import { isActiveFilterValue } from '../core/filter-utils';
import { clampColumnWidth } from '../core/table-layout';
import type { DataTableShellProps } from '../core/types';
import { useTableGeometry } from '../hooks/use-table-geometry';
import { DataTableBody } from './data-table-body';
import { DataTableBulkActionsBar } from './data-table-bulk-actions-bar';
import { DataTableHeader } from './data-table-header';
import { DataTablePagination } from './data-table-pagination';
import { DataTableResultSummary } from './data-table-result-summary';
import { DataTableToolbar } from './data-table-toolbar';

export function DataTableShell<TData>({
    table,
    headerGroups,
    rows,
    isLoading,
    isFetching,
    error,
    meta,
    state,
    features,
    filterConfig,
    quickFilters,
    dateFilterColumns,
    dateFilterPresets,
    searchPlaceholder,
    emptyMessage,
    mode,
    infiniteScrollConfig,
    hasMore,
    isFetchingMore,
    loadMoreRef,
    bulkActions,
    bulkAggregates,
    bulkActionsConfig,
    appearance,
    selectedRows = [],
    selectedCount,
    onClearSelection,
    selectionStatus,
    onSearchChange,
    onFilterChange,
    onResetFilters,
    onClearSearch,
    onResetLayout,
    onResetAll,
    onDensityChange,
    storageStatus,
    onRefresh,
    onPageChange,
    onPerPageChange,
    exportConfig,
}: DataTableShellProps<TData>) {
    const tableViewportRef = useRef<HTMLDivElement>(null);
    const { geometry, widths } = useTableGeometry(
        tableViewportRef,
        table,
        state,
    );
    function autosize(columnId?: string) {
        const viewport = tableViewportRef.current;
        const context = document.createElement('canvas').getContext('2d');

        if (!viewport || !context) {
            return;
        }

        const sizes: Record<string, number> = {};

        for (const column of table.getVisibleLeafColumns()) {
            if (
                !column.getCanResize() ||
                (columnId && column.id !== columnId)
            ) {
                continue;
            }

            let width = 0;

            for (const cell of viewport.querySelectorAll<HTMLElement>(
                '[data-column-id]',
            )) {
                if (cell.dataset.columnId !== column.id) {
                    continue;
                }

                const style = getComputedStyle(cell);
                context.font = style.font;
                const textWidth = Math.max(
                    ...(cell.textContent ?? '')
                        .split('\n')
                        .map((line) => context.measureText(line).width),
                    0,
                );
                const controlsWidth = [
                    ...cell.querySelectorAll('svg, input, [role="checkbox"]'),
                ].reduce(
                    (sum, control) =>
                        sum + control.getBoundingClientRect().width + 8,
                    0,
                );
                width = Math.max(
                    width,
                    textWidth +
                        parseFloat(style.paddingLeft) +
                        parseFloat(style.paddingRight) +
                        controlsWidth +
                        12,
                );
            }

            sizes[column.id] = clampColumnWidth(
                width,
                column.columnDef.minSize,
                column.columnDef.maxSize,
            );
        }

        table.setColumnSizing((current) => ({ ...current, ...sizes }));
    }
    const [viewportHeight, setViewportHeight] = useState<string>();
    const [isScrolled, setIsScrolled] = useState(false);
    const shouldFitViewport =
        mode === 'infinite-scroll' && infiniteScrollConfig?.fitViewport;

    useEffect(() => {
        if (!shouldFitViewport) {
            return;
        }

        function updateViewportHeight() {
            const element = tableViewportRef.current;

            if (!element) {
                return;
            }

            const bottomOffset =
                infiniteScrollConfig?.viewportBottomOffset ?? 96;
            const minHeight = infiniteScrollConfig?.minHeight ?? 320;
            const availableHeight =
                window.innerHeight -
                element.getBoundingClientRect().top -
                bottomOffset;
            const nextHeight = `${Math.max(minHeight, availableHeight)}px`;

            setViewportHeight((currentHeight) =>
                currentHeight === nextHeight ? currentHeight : nextHeight,
            );
        }

        const frame = window.requestAnimationFrame(updateViewportHeight);
        window.addEventListener('resize', updateViewportHeight);
        window.addEventListener('orientationchange', updateViewportHeight);

        return () => {
            window.cancelAnimationFrame(frame);
            window.removeEventListener('resize', updateViewportHeight);
            window.removeEventListener(
                'orientationchange',
                updateViewportHeight,
            );
        };
    }, [
        infiniteScrollConfig?.minHeight,
        infiniteScrollConfig?.viewportBottomOffset,
        shouldFitViewport,
    ]);

    useEffect(() => {
        const el = tableViewportRef.current;

        if (!el) {
            return;
        }

        function handleScroll() {
            setIsScrolled((el?.scrollLeft ?? 0) > 0);
        }

        el.addEventListener('scroll', handleScroll, { passive: true });

        return () => el.removeEventListener('scroll', handleScroll);
    }, []);

    const tableMaxHeight =
        mode === 'infinite-scroll'
            ? ((shouldFitViewport
                  ? viewportHeight
                  : infiniteScrollConfig?.height) ?? '60vh')
            : undefined;

    const hasFilters = useMemo(
        () => Object.values(state.filters).some(isActiveFilterValue),
        [state.filters],
    );

    return (
        <div className="flex flex-col gap-4">
            <DataTableToolbar
                table={table}
                features={features}
                filters={filterConfig}
                quickFilters={quickFilters}
                dateFilterColumns={dateFilterColumns}
                dateFilterPresets={dateFilterPresets}
                filterValues={state.filters}
                search={state.search}
                searchPlaceholder={searchPlaceholder}
                isFetching={isFetching}
                onSearchChange={onSearchChange}
                onFilterChange={onFilterChange}
                onResetFilters={onResetFilters}
                onRefresh={onRefresh}
                exportConfig={exportConfig}
                currentSort={state.sorting[0]?.id}
                currentSortDirection={state.sorting[0]?.desc ? 'desc' : 'asc'}
                layoutControls={{
                    state,
                    density: appearance.size,
                    storageStatus,
                    onDensityChange,
                    onResetLayout,
                    onResetAll,
                    onAutosize: autosize,
                }}
            />

            <Card className="overflow-hidden rounded-xs py-0">
                <CardContent className="p-0">
                    <div
                        ref={tableViewportRef}
                        className="overflow-auto"
                        style={{
                            maxHeight: tableMaxHeight,
                        }}
                    >
                        <table
                            className="caption-bottom border-collapse text-sm"
                            style={{
                                tableLayout: 'fixed',
                                width: Object.values(widths).reduce(
                                    (sum, width) => sum + width,
                                    0,
                                ),
                            }}
                        >
                            <colgroup>
                                {table.getVisibleLeafColumns().map((column) => (
                                    <col
                                        key={column.id}
                                        data-column-id={column.id}
                                        style={{ width: widths[column.id] }}
                                    />
                                ))}
                            </colgroup>
                            <DataTableHeader
                                headerGroups={headerGroups}
                                appearance={appearance}
                                isScrolled={isScrolled}
                                geometry={geometry}
                                widths={widths}
                                onAutosize={autosize}
                            />
                            <DataTableBody
                                rows={rows}
                                geometry={geometry}
                                rowSelection={state.rowSelection}
                                isScrolled={isScrolled}
                                visibleColumnCount={
                                    table.getVisibleLeafColumns().length
                                }
                                isLoading={isLoading}
                                appearance={appearance}
                                error={error}
                                emptyMessage={emptyMessage}
                                hasSearch={state.search.length > 0}
                                hasFilters={hasFilters}
                                onClearSearch={onClearSearch}
                                onResetFilters={onResetFilters}
                                onRetry={onRefresh}
                            />
                        </table>

                        {mode === 'infinite-scroll' && (
                            <div
                                ref={loadMoreRef}
                                className="flex h-14 items-center justify-center"
                            >
                                {isFetchingMore && (
                                    <span className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                        <Loader2 className="size-4 animate-spin" />
                                        Carregando mais
                                    </span>
                                )}
                                {!hasMore && rows.length > 0 && (
                                    <span className="text-sm text-muted-foreground">
                                        Todos os registros foram carregados
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <DataTableResultSummary
                    meta={meta}
                    loadedCount={rows.length}
                    mode={mode}
                />

                {mode === 'paginated' && (
                    <DataTablePagination
                        meta={meta}
                        page={state.page}
                        perPage={state.perPage}
                        showSummary={false}
                        onPageChange={onPageChange}
                        onPerPageChange={onPerPageChange}
                    />
                )}
            </div>

            {bulkActions && bulkActions.length > 0 && onClearSelection && (
                <DataTableBulkActionsBar
                    selectedRows={selectedRows}
                    selectedCount={selectedCount ?? selectedRows.length}
                    actions={bulkActions}
                    aggregates={bulkAggregates}
                    config={bulkActionsConfig}
                    onClear={onClearSelection}
                    status={selectionStatus}
                />
            )}
        </div>
    );
}
