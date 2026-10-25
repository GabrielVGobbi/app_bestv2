import { X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

import { filterValueLabel } from '../core/filter-presentation';
import { isActiveFilterValue } from '../core/filter-utils';
import type {
    DataTableFilterPrimitive,
    DataTableFilterValue,
    DataTableQuickFilter,
} from '../core/types';

interface QuickFilterSelectProps {
    filter: DataTableQuickFilter;
    value: DataTableFilterValue;
    onChange: (value: DataTableFilterValue) => void;
}

function optionColor(color?: string) {
    if (!color) {
        return null;
    }

    return (
        <span
            className="size-2.5 rounded-full"
            style={{ backgroundColor: color }}
        />
    );
}

export function QuickFilterSelect({
    filter,
    value,
    onChange,
}: QuickFilterSelectProps) {
    const Icon = filter.icon;
    const hasValue = isActiveFilterValue(value);
    const selectedValues = Array.isArray(value)
        ? value.map(String)
        : hasValue
          ? [String(value)]
          : [];
    const summary = filterValueLabel(filter, value);
    const label = hasValue ? `${filter.label}: ${summary}` : filter.label;

    function toggleMultiple(
        optionValue: DataTableFilterPrimitive,
        checked: boolean,
    ): void {
        const stringValue = String(optionValue);
        const currentValues = Array.isArray(value) ? value : [];
        const nextValues = checked
            ? [
                  ...currentValues.filter(
                      (selectedValue) => String(selectedValue) !== stringValue,
                  ),
                  optionValue,
              ]
            : currentValues.filter(
                  (selectedValue) => String(selectedValue) !== stringValue,
              );

        onChange(nextValues.length > 0 ? nextValues : undefined);
    }

    return (
        <Popover>
            <div
                className={cn(
                    'flex h-9 min-w-0 items-center rounded-sm border bg-card shadow-xs',
                    hasValue
                        ? 'border-primary/50 bg-primary/5'
                        : 'border-border',
                )}
            >
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        aria-label={label}
                        title={label}
                        className="h-full min-w-0 justify-start gap-2 border-0 shadow-none"
                    >
                        {Icon && <Icon className="size-4" />}
                        <span className="max-w-48 min-w-0 truncate text-left">
                            <span className="text-muted-foreground">
                                {filter.label}
                                {hasValue ? ':' : ''}
                            </span>
                            {hasValue && <> {summary}</>}
                        </span>
                    </Button>
                </PopoverTrigger>
                {hasValue && filter.clearable !== false && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="mr-1 size-7 shrink-0"
                        aria-label={`Limpar ${filter.label}`}
                        onClick={() => onChange(undefined)}
                    >
                        <X className="size-3" />
                    </Button>
                )}
            </div>

            <PopoverContent align="start" className="w-56 p-1">
                <div className="mb-3 border-b border-border px-2 py-1.5 text-sm font-medium">
                    {filter.label}
                </div>
                <div className="max-h-64 overflow-auto">
                    <button
                        type="button"
                        aria-pressed={!hasValue}
                        className={cn(
                            'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent',
                            !hasValue && 'bg-accent',
                        )}
                        onClick={() => onChange(undefined)}
                    >
                        <span className="flex-1">Todos</span>
                    </button>
                    {filter.options.map((option) => {
                        const checked = selectedValues.includes(
                            String(option.value),
                        );

                        if (filter.multiple) {
                            return (
                                <label
                                    key={String(option.value)}
                                    className="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={(nextChecked) =>
                                            toggleMultiple(
                                                option.value,
                                                Boolean(nextChecked),
                                            )
                                        }
                                    />
                                    {optionColor(option.color)}
                                    <span className="flex-1">
                                        {option.label}
                                    </span>
                                </label>
                            );
                        }

                        return (
                            <button
                                key={String(option.value)}
                                type="button"
                                aria-pressed={checked}
                                className={cn(
                                    'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent',
                                    checked && 'bg-accent',
                                )}
                                onClick={() => onChange(option.value)}
                            >
                                {optionColor(option.color)}
                                <span className="flex-1">{option.label}</span>
                            </button>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}
