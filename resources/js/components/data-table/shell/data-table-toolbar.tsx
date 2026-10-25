import type { Table } from '@tanstack/react-table';
import { Download, FilterX, RefreshCw, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

import { buildFilterChips } from '../core/filter-presentation';
import { isActiveFilterValue } from '../core/filter-utils';
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
import { AdvancedFilterPanel } from './advanced-filter-panel';
import { DataTableActionsMenu } from './data-table-actions-menu';
import { DataTableExportDialog } from './data-table-export-dialog';
import { DataTableExportStatus } from './data-table-export-status';
import { QuickFilterSelect } from './quick-filter-select';
import { TableLayoutSettings } from './table-layout-settings';
import type { TableLayoutControls } from './table-layout-settings';

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
    onRefresh: () => void;
    exportConfig?: DataTableExportConfig;
    currentSort?: string;
    currentSortDirection?: 'asc' | 'desc';
    layoutControls?: TableLayoutControls;
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
    onRefresh,
    exportConfig,
    currentSort,
    currentSortDirection,
    layoutControls,
}: DataTableToolbarProps<TData>) {
    const [searchExpanded, setSearchExpanded] = useState(false);
    const [exportDialogOpen, setExportDialogOpen] = useState(false);
    const searchOpen = searchExpanded || search.length > 0;

    const hasActiveFilters = useMemo(
        () => Object.values(filterValues).some(isActiveFilterValue),
        [filterValues],
    );

    const dateFilters = useMemo<DataTableFilterConfig[]>(
        () =>
            dateFilterColumns.map((column) => ({
                id: column.value,
                label: column.label,
                type: 'date-range' as const,
                operator: 'between' as const,
            })),
        [dateFilterColumns],
    );

    const exportFilterConfig = useMemo(
        () => [...quickFilters, ...filters, ...dateFilters],
        [dateFilters, filters, quickFilters],
    );
    const activeChips = buildFilterChips(
        [...filters, ...dateFilters].filter(
            (filter) => !quickFilters.some((quick) => quick.id === filter.id),
        ),
        filterValues,
    );

    const showExport =
        features.export && exportConfig && exportConfig.enabled !== false;
    const { activeExports, startExport, markAsDownloaded } = useExport(
        exportConfig,
        Boolean(showExport),
    );

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    {features.search && (
                        <div
                            className={cn(
                                'relative h-9 transition-[width] duration-200 ease-out',
                                searchOpen ? 'w-full md:w-60' : 'w-9',
                            )}
                            onMouseEnter={() => setSearchExpanded(true)}
                            onMouseLeave={() => {
                                if (!search) {
                                    setSearchExpanded(false);
                                }
                            }}
                            onFocus={() => setSearchExpanded(true)}
                            onBlur={(event) => {
                                if (
                                    !event.currentTarget.contains(
                                        event.relatedTarget,
                                    ) &&
                                    !search
                                ) {
                                    setSearchExpanded(false);
                                }
                            }}
                        >
                            {!searchOpen ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Buscar"
                                >
                                    <Search />
                                </Button>
                            ) : (
                                <>
                                    <Search className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        aria-label={searchPlaceholder}
                                        value={search}
                                        onChange={(event) =>
                                            onSearchChange(event.target.value)
                                        }
                                        placeholder={searchPlaceholder}
                                        className="h-9 pr-9 pl-9"
                                        autoFocus={searchExpanded && !search}
                                    />
                                    {search.length > 0 && (
                                        <button
                                            type="button"
                                            className="absolute top-1/2 right-2 z-10 -translate-y-1/2 rounded-sm p-1 text-muted-foreground hover:text-foreground"
                                            onClick={() => {
                                                onSearchChange('');
                                                setSearchExpanded(false);
                                            }}
                                            aria-label="Limpar busca"
                                        >
                                            <X className="size-4" />
                                        </button>
                                    )}
                                </>
                            )}
                        </div>
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

                    {activeChips.length > 0 && (
                        <>
                            {activeChips.map((chip) => (
                                <Button
                                    key={`${chip.id}:${chip.value ?? ''}`}
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    aria-label={`Remover filtro ${chip.title}: ${chip.label}`}
                                    onClick={() => {
                                        const value = filterValues[chip.id];
                                        onFilterChange(
                                            chip.id,
                                            Array.isArray(value)
                                                ? value.filter(
                                                      (item) =>
                                                          String(item) !==
                                                          chip.value,
                                                  )
                                                : undefined,
                                        );
                                    }}
                                >
                                    {chip.title}: {chip.label}
                                    <X className="size-3.5" />
                                </Button>
                            ))}
                        </>
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

                    {hasActiveFilters && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onResetFilters}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            <FilterX className="size-4" />
                            Limpar filtros
                        </Button>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    {features.refresh && (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={onRefresh}
                            disabled={isFetching}
                            aria-label="Atualizar"
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    isFetching && 'animate-spin',
                                )}
                            />
                        </Button>
                    )}

                    {showExport && (
                        <>
                            <DataTableExportStatus
                                activeExports={activeExports}
                                onDownload={markAsDownloaded}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setExportDialogOpen(true)}
                            >
                                <Download className="size-4" />
                                Exportar
                            </Button>
                            <DataTableExportDialog
                                open={exportDialogOpen}
                                onClose={() => setExportDialogOpen(false)}
                                modelClass={exportConfig.modelClass}
                                columnsEndpoint={exportConfig.columnsEndpoint}
                                currentFilters={filterValues}
                                fixedFilters={exportConfig.fixedFilters}
                                currentFilterConfig={exportFilterConfig}
                                currentSearch={search}
                                currentSort={currentSort}
                                currentSortDirection={currentSortDirection}
                                activeExports={activeExports}
                                onStartExport={startExport}
                                onExportStarted={() =>
                                    setExportDialogOpen(false)
                                }
                            />
                        </>
                    )}
                    {features.columnVisibility && layoutControls ? (
                        <TableLayoutSettings
                            table={table}
                            {...layoutControls}
                        />
                    ) : (
                        <DataTableActionsMenu
                            table={table}
                            showColumnVisibility={features.columnVisibility}
                        />
                    )}
                </div>
            </div>
        </div>
    );
}
