import type { Table } from '@tanstack/react-table';
import { Search, X } from 'lucide-react';
import { useState } from 'react';

import { Input } from '@/components/ui/input';

import type {
    DataTableDateColumn,
    DataTableDatePreset,
    DataTableExportConfig,
    DataTableFeatures,
    DataTableFilterConfig,
    DataTableFilterValue,
    DataTableQuickFilter,
} from '../core/types';

import { useExport } from '../hooks/use-export';
import {
    AdvancedFilterPanel,
    DataTableFilterChips,
} from './advanced-filter-panel';
import { DataTableActionsMenu } from './data-table-actions-menu';
import { DataTableExportDialog } from './data-table-export-dialog';
import { DataTableExportStatus } from './data-table-export-status';
import { QuickFilterSelect } from './quick-filter-select';

interface DataTableToolbarProps<TData> {
    table: Table<TData>;
    features: DataTableFeatures;
    filters: DataTableFilterConfig[];
    quickFilters: DataTableQuickFilter[];
    dateFilterColumns: DataTableDateColumn[];
    dateFilterPresets?: DataTableDatePreset[];
    filterValues: Record<string, DataTableFilterValue>;
    search: string;
    searchPlaceholder: string;
    isFetching: boolean;
    onSearchChange: (value: string) => void;
    onFilterChange: (id: string, value: DataTableFilterValue) => void;
    onResetFilters: () => void;
    onResetTable: () => void;
    canResetTable: boolean;
    onRefresh: () => void;
    exportConfig?: DataTableExportConfig;
    currentSort?: string;
    currentSortDirection?: 'asc' | 'desc';
}

export function DataTableToolbar<TData>({
    table,
    features,
    filters,
    quickFilters,
    dateFilterColumns,
    dateFilterPresets,
    filterValues,
    search,
    searchPlaceholder,
    isFetching,
    onSearchChange,
    onFilterChange,
    onResetFilters,
    onResetTable,
    canResetTable,
    onRefresh,
    exportConfig,
    currentSort,
    currentSortDirection,
}: DataTableToolbarProps<TData>) {
    const [exportDialogOpen, setExportDialogOpen] = useState(false);

    const { activeExports, startExport, markAsDownloaded } =
        useExport(exportConfig);

    const showExport = Boolean(
        features.export && exportConfig && exportConfig.enabled !== false,
    );

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-start gap-2">
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    {features.search && (
                        <div className="relative w-full shrink-0 md:max-w-80">
                            <Search className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    onSearchChange(event.target.value)
                                }
                                placeholder={searchPlaceholder}
                                aria-label={searchPlaceholder}
                                className="h-10 bg-background pr-9 pl-10"
                            />
                            {search.length > 0 && (
                                <button
                                    type="button"
                                    className="absolute top-1/2 right-2 z-10 -translate-y-1/2 rounded-sm p-1 text-muted-foreground hover:text-foreground"
                                    onClick={() => onSearchChange('')}
                                    aria-label="Limpar busca"
                                >
                                    <X className="size-4" />
                                </button>
                            )}
                        </div>
                    )}

                    {features.filters && (
                        <AdvancedFilterPanel
                            filters={filters}
                            dateFilterColumns={dateFilterColumns}
                            dateFilterPresets={dateFilterPresets}
                            values={filterValues}
                            onChange={onFilterChange}
                            onReset={onResetFilters}
                        />
                    )}

                    {quickFilters.map((filter) => (
                        <QuickFilterSelect
                            key={filter.id}
                            filter={filter}
                            value={filterValues[filter.id]}
                            onChange={(value) =>
                                onFilterChange(filter.id, value)
                            }
                        />
                    ))}
                </div>

                <div className="ml-auto flex shrink-0 items-center gap-2">
                    {showExport && (
                        <DataTableExportStatus
                            activeExports={activeExports}
                            onDownload={markAsDownloaded}
                        />
                    )}
                    <DataTableActionsMenu
                        table={table}
                        showColumnVisibility={features.columnVisibility}
                        canResetTable={canResetTable}
                        onResetTable={onResetTable}
                        showRefresh={features.refresh}
                        isFetching={isFetching}
                        onRefresh={onRefresh}
                        showExport={showExport}
                        onExport={() => setExportDialogOpen(true)}
                    />
                    {showExport && exportConfig && (
                        <DataTableExportDialog
                            open={exportDialogOpen}
                            onClose={() => setExportDialogOpen(false)}
                            modelClass={exportConfig.modelClass}
                            columnsEndpoint={exportConfig.columnsEndpoint}
                            currentFilters={{
                                ...filterValues,
                                ...(exportConfig.fixedFilters ?? {}),
                            }}
                            currentSearch={search}
                            currentSort={currentSort}
                            currentSortDirection={currentSortDirection}
                            activeExports={activeExports}
                            onStartExport={startExport}
                            onExportStarted={() => setExportDialogOpen(false)}
                        />
                    )}
                </div>
            </div>

            {features.filters && (
                <DataTableFilterChips
                    filters={filters}
                    dateFilterColumns={dateFilterColumns}
                    values={filterValues}
                    onChange={onFilterChange}
                    onReset={onResetFilters}
                />
            )}
        </div>
    );
}
