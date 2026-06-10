import {
    CalendarDays,
    ChevronRight,
    Filter,
    RotateCcw,
    Search,
    X,
} from 'lucide-react';
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

import { formatShortDate } from '../core/date-presets';
import { isActiveFilterValue, optionLabel } from '../core/filter-utils';
import { CategoryContent } from './filter-category-content';
import { DateFilterSection } from './filter-date-section';
import type {
    DataTableDateColumn,
    DataTableDatePreset,
    DataTableFilterConfig,
    DataTableFilterPrimitive,
    DataTableFilterValue,
} from '../core/types';
import { useDataTableFilters } from '../hooks/use-data-table-filters';

interface FilterChip {
    id: string;
    title: string;
    label: string;
    value?: string;
    type?: 'date' | 'filter';
}

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


function buildChips(
    filters: DataTableFilterConfig[],
    values: Record<string, DataTableFilterValue>,
): FilterChip[] {
    return filters.flatMap((filter): FilterChip[] => {
        const value = values[filter.id];

        if (!isActiveFilterValue(value)) {
            return [];
        }

        if (Array.isArray(value)) {
            return value.map((item) => ({
                id: filter.id,
                title: filterTitle(filter),
                label: optionLabel(filter.options, item),
                value: String(item),
            }));
        }

        if (typeof value === 'object' && value !== null) {
            const from = value.from ? formatShortDate(value.from) : '...';
            const to = value.to ? formatShortDate(value.to) : '...';

            return [
                {
                    id: filter.id,
                    title: filterTitle(filter),
                    label: `${from} - ${to}`,
                    type: 'date',
                },
            ];
        }

        const primitiveValue = value as DataTableFilterPrimitive;

        return [
            {
                id: filter.id,
                title: filterTitle(filter),
                label: optionLabel(filter.options, primitiveValue),
                value: String(primitiveValue),
            },
        ];
    });
}

function removeChipValue(
    chip: FilterChip,
    currentValue: DataTableFilterValue,
    onChange: (value: DataTableFilterValue) => void,
): void {
    if (Array.isArray(currentValue) && chip.value !== undefined) {
        const nextValue = currentValue.filter(
            (value) => String(value) !== chip.value,
        );

        onChange(nextValue.length > 0 ? nextValue : undefined);

        return;
    }

    onChange(undefined);
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
    const chips = useMemo(
        () => buildChips(filtersWithDates, values),
        [filtersWithDates, values],
    );
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
                        <div className="grid min-h-80 grid-cols-[220px_1fr]">
                            <div className="border-r bg-muted/20 p-2">
                                {filters.length > 6 && (
                                    <div className="relative mb-2">
                                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            placeholder="Buscar categoria..."
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

                                <div className="max-h-72 space-y-1 overflow-y-auto pr-1">
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
                                                className={cn(
                                                    'flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-sm transition-colors hover:bg-accent',
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

                            <div className="p-4">
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

            {chips.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5">
                    {chips.slice(0, 3).map((chip) => (
                        <Badge
                            key={`${chip.id}-${chip.value ?? chip.label}`}
                            variant="secondary"
                            className={cn(
                                'h-8 gap-1.5 pr-1.5 pl-2.5',
                                chip.type === 'date' &&
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                            )}
                        >
                            {chip.type === 'date' && (
                                <CalendarDays className="size-3" />
                            )}
                            <span className="text-xs font-medium">
                                {chip.title}:
                            </span>
                            <span className="max-w-32 truncate text-xs">
                                {chip.label}
                            </span>
                            <button
                                type="button"
                                className="rounded-full p-0.5 hover:bg-background/60"
                                onClick={() =>
                                    removeChipValue(
                                        chip,
                                        values[chip.id],
                                        (value) => onChange(chip.id, value),
                                    )
                                }
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}

                    {chips.length > 3 && (
                        <Badge
                            variant="outline"
                            className="h-7 rounded-full text-xs"
                        >
                            +{chips.length - 3} mais
                        </Badge>
                    )}
                </div>
            )}
        </div>
    );
}
