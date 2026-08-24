import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Card, CardContent } from '@/components/ui/card';

import { isColumnVisible } from '../core/column-visibility';
import { isActiveFilterValue } from '../core/filter-utils';
import type { DataTableShellProps } from '../core/types';
import { DataTableBody } from './data-table-body';
import { DataTableBulkActionsBar } from './data-table-bulk-actions-bar';
import { DataTableHeader } from './data-table-header';
import { DataTablePagination } from './data-table-pagination';
import { DataTableResultSummary } from './data-table-result-summary';
import { DataTableToolbar } from './data-table-toolbar';

export function DataTableShell<TData>({
    table,
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
    onSearchChange,
    onFilterChange,
    onResetFilters,
    onResetTable,
    canResetTable,
    onClearSearch,
    onRefresh,
    onPageChange,
    onPerPageChange,
    exportConfig,
}: DataTableShellProps<TData>) {
    const tableViewportRef = useRef<HTMLDivElement>(null);
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

    const visibleLeafColumns = table
        .getAllLeafColumns()
        .filter((column) => isColumnVisible(column.id, state.columnVisibility));
    const visibleTableWidth = visibleLeafColumns.reduce(
        (total, column) => total + column.getSize(),
        0,
    );

    return (
        <div className="flex flex-col gap-3">
            <Card className="overflow-hidden rounded-xl border-app-line py-0 shadow-none">
                <CardContent className="p-0">
                    <div className="border-b border-app-line bg-card p-3">
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
                            onResetTable={onResetTable}
                            canResetTable={canResetTable}
                            onRefresh={onRefresh}
                            exportConfig={exportConfig}
                            currentSort={state.sorting[0]?.id}
                            currentSortDirection={
                                state.sorting[0]?.desc ? 'desc' : 'asc'
                            }
                        />
                    </div>

                    <div
                        ref={tableViewportRef}
                        className="custom-scrollbar overflow-auto"
                        style={{
                            maxHeight: tableMaxHeight,
                        }}
                    >
                        <table
                            className="w-full caption-bottom border-collapse text-sm"
                            style={{
                                tableLayout: 'fixed',
                                minWidth: visibleTableWidth,
                            }}
                        >
                            <DataTableHeader
                                headerGroups={table.getHeaderGroups()}
                                appearance={appearance}
                                isScrolled={isScrolled}
                                columnVisibility={state.columnVisibility}
                            />
                            <DataTableBody
                                rows={rows}
                                rowSelection={state.rowSelection}
                                columnVisibility={state.columnVisibility}
                                isScrolled={isScrolled}
                                visibleColumnCount={visibleLeafColumns.length}
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

            <div className="flex flex-col gap-3 px-1 md:flex-row md:items-center md:justify-between">
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
                />
            )}
        </div>
    );
}
