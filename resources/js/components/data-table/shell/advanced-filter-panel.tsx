import { ChevronRight, Filter, RotateCcw, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

import { isActiveFilterValue } from '../core/filter-utils';
import type {
    DataTableDateColumn,
    DataTableDatePreset,
    DataTableFilterConfig,
    DataTableFilterValue,
} from '../core/types';
import { useDataTableFilters } from '../hooks/use-data-table-filters';
import { CategoryContent } from './filter-category-content';
import { DateFilterSection } from './filter-date-section';

interface AdvancedFilterPanelProps {
    filters: DataTableFilterConfig[];
    dateFilterColumns: DataTableDateColumn[];
    dateFilterPresets?: DataTableDatePreset[];
    values: Record<string, DataTableFilterValue>;
    onChange: (id: string, value: DataTableFilterValue) => void;
    onReset: () => void;
}

function filterTitle(filter: DataTableFilterConfig): string {
    return filter.title ?? filter.label;
}

export function AdvancedFilterPanel({
    filters,
    dateFilterColumns,
    dateFilterPresets,
    values,
    onChange,
    onReset,
}: AdvancedFilterPanelProps) {
    const [open, setOpen] = useState(false);
    const [categorySearch, setCategorySearch] = useState('');
    const [activeCategory, setActiveCategory] = useState(filters[0]?.id ?? '');
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
    const filtersWithDates = useMemo(
        () => [...filters, ...dateFilters],
        [dateFilters, filters],
    );
    const { activeCount } = useDataTableFilters(filtersWithDates, values);

    const activeFilter =
        filters.find((filter) => filter.id === activeCategory) ?? filters[0];
    const filteredCategories = useMemo(() => {
        if (!categorySearch.trim()) {
            return filters;
        }

        return filters.filter((filter) =>
            filterTitle(filter)
                .toLowerCase()
                .includes(categorySearch.toLowerCase()),
        );
    }, [categorySearch, filters]);

    if (filters.length === 0 && dateFilterColumns.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className={cn(
                            'gap-2',
                            activeCount > 0 && 'border-primary/50 bg-primary/5',
                        )}
                    >
                        <Filter />
                        Filtros
                        {activeCount > 0 && (
                            <Badge className="h-5 min-w-5 rounded-full px-1.5 text-xs">
                                {activeCount}
                            </Badge>
                        )}
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    align="start"
                    className="w-[min(720px,calc(100vw-2rem))] overflow-hidden rounded-xl p-0"
                >
                    <div className="border-b bg-muted/30">
                        <div className="flex items-center justify-between px-4 py-3">
                            <span className="text-sm font-semibold">
                                Filtros
                            </span>
                            {activeCount > 0 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={onReset}
                                >
                                    <RotateCcw />
                                    Limpar tudo
                                </Button>
                            )}
                        </div>

                        <DateFilterSection
                            columns={dateFilterColumns}
                            presets={dateFilterPresets}
                            values={values}
                            onChange={onChange}
                        />
                    </div>

                    {filters.length > 0 && (
                        <div className="grid max-h-[min(32rem,65vh)] grid-cols-1 overflow-y-auto sm:min-h-80 sm:grid-cols-[220px_minmax(0,1fr)]">
                            <div className="border-r bg-muted/20 p-2">
                                {filters.length > 6 && (
                                    <div className="relative mb-2">
                                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar categoria..."
                                            aria-label="Buscar categoria de filtro"
                                            value={categorySearch}
                                            onChange={(event) =>
                                                setCategorySearch(
                                                    event.target.value,
                                                )
                                            }
                                            className="h-8 pl-8"
                                        />
                                    </div>
                                )}

                                <div className="flex max-h-40 flex-wrap gap-1 overflow-y-auto pr-1 sm:block sm:max-h-72 sm:space-y-1">
                                    {filteredCategories.map((filter) => {
                                        const Icon = filter.icon;
                                        const selected =
                                            activeFilter?.id === filter.id;
                                        const value = values[filter.id];
                                        const valueCount = Array.isArray(value)
                                            ? value.length
                                            : isActiveFilterValue(value)
                                              ? 1
                                              : 0;

                                        return (
                                            <button
                                                key={filter.id}
                                                type="button"
                                                aria-pressed={selected}
                                                className={cn(
                                                    'flex min-w-0 items-center gap-2 rounded-lg px-3 py-2.5 text-sm transition-colors hover:bg-accent sm:w-full',
                                                    selected
                                                        ? 'bg-accent font-medium text-accent-foreground'
                                                        : 'text-muted-foreground',
                                                )}
                                                onClick={() =>
                                                    setActiveCategory(filter.id)
                                                }
                                            >
                                                {Icon && (
                                                    <Icon
                                                        className={cn(
                                                            'size-4 shrink-0',
                                                            selected &&
                                                                'text-primary',
                                                        )}
                                                    />
                                                )}
                                                <span className="flex-1 truncate text-left">
                                                    {filterTitle(filter)}
                                                </span>
                                                {valueCount > 0 && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="h-5 min-w-5 rounded-full px-1.5 text-xs"
                                                    >
                                                        {valueCount}
                                                    </Badge>
                                                )}
                                                <ChevronRight
                                                    className={cn(
                                                        'size-4 shrink-0 transition-transform',
                                                        selected && 'rotate-90',
                                                    )}
                                                />
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="min-w-0 p-4">
                                {activeFilter && (
                                    <div className="space-y-4">
                                        <div>
                                            <h4 className="text-sm font-medium">
                                                {filterTitle(activeFilter)}
                                            </h4>
                                            {activeFilter.placeholder && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {activeFilter.placeholder}
                                                </p>
                                            )}
                                        </div>

                                        <CategoryContent
                                            filter={activeFilter}
                                            value={values[activeFilter.id]}
                                            onChange={(value) =>
                                                onChange(activeFilter.id, value)
                                            }
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </PopoverContent>
            </Popover>
        </div>
    );
}
