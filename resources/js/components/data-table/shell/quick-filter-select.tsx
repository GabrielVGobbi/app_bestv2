import { X } from 'lucide-react';

import { AsyncSelect } from '@/components/async-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

import type { DataTableFilterPrimitive, DataTableFilterValue, DataTableQuickFilter } from '../core/types';

interface QuickFilterSelectProps {
    filter: DataTableQuickFilter;
    value: DataTableFilterValue;
    onChange: (value: DataTableFilterValue) => void;
}

function selectedValueLabel(filter: DataTableQuickFilter, value: DataTableFilterValue): string | null {
    const options = filter.options ?? [];

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return null;
        }

        if (value.length === 1) {
            return options.find((option) => String(option.value) === String(value[0]))?.label ?? String(value[0]);
        }

        return String(value.length);
    }

    if (value === null || value === undefined || value === '') {
        return null;
    }

    return options.find((option) => String(option.value) === String(value))?.label ?? String(value);
}

function optionColor(color?: string) {
    if (!color) {
        return null;
    }

    return <span className="size-2.5 rounded-full" style={{ backgroundColor: color }} />;
}

export function QuickFilterSelect({ filter, value, onChange }: QuickFilterSelectProps) {
    const Icon = filter.icon;
    const selectedValues = Array.isArray(value) ? value.map(String) : value === undefined || value === null ? [] : [String(value)];
    const hasValue = selectedValues.length > 0;
    const valueLabel = selectedValueLabel(filter, value);

    if (filter.type === 'async-select' && filter.asyncSelect) {
        const asyncValue =
            value === undefined || value === null || Array.isArray(value) || typeof value === 'object'
                ? null
                : String(value);

        return (
            <div className="min-w-52 md:w-56">
                <AsyncSelect
                    value={asyncValue}
                    endpoint={filter.asyncSelect.endpoint}
                    params={filter.asyncSelect.params}
                    fieldLabel={filter.label}
                    placeholder={filter.placeholder ?? filter.label}
                    searchPlaceholder={filter.asyncSelect.searchPlaceholder}
                    emptyMessage={filter.asyncSelect.emptyMessage}
                    clearable={filter.clearable !== false}
                    className={cn('min-h-9 py-1.5 text-sm', hasValue && 'border-primary/50 bg-primary/5')}
                    onChange={(nextValue) => onChange(nextValue ?? undefined)}
                />
            </div>
        );
    }

    function toggleMultiple(optionValue: DataTableFilterPrimitive, checked: boolean): void {
        const stringValue = String(optionValue);
        const nextValues = checked
            ? [...selectedValues, stringValue]
            : selectedValues.filter((selectedValue) => selectedValue !== stringValue);

        onChange(nextValues.length > 0 ? nextValues : undefined);
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn('h-9 max-w-72 justify-start gap-2', hasValue && 'border-primary/50 bg-primary/5')}
                >
                    {Icon && <Icon className="size-4 shrink-0" />}
                    <span className="flex min-w-0 items-center gap-1.5">
                        <span className={cn('truncate', hasValue ? 'text-muted-foreground' : 'text-foreground')}>
                            {filter.label}
                        </span>
                        {hasValue && valueLabel && (
                            <>
                                <span className="text-muted-foreground">·</span>
                                <span className="truncate font-medium">{valueLabel}</span>
                            </>
                        )}
                    </span>
                    {hasValue && filter.clearable !== false && (
                        <span
                            role="button"
                            tabIndex={0}
                            aria-label={`Limpar filtro ${filter.label}`}
                            className="hover:bg-background/80 rounded-full p-0.5"
                            onClick={(event) => {
                                event.stopPropagation();
                                onChange(undefined);
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' || event.key === ' ') {
                                    event.preventDefault();
                                    onChange(undefined);
                                }
                            }}
                        >
                            <X className="size-3" />
                        </span>
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent align="start" className="w-56 p-1">
                <div className="px-2 py-1.5 text-sm font-medium border-b border-border mb-3">{filter.label}</div>
                <div className="max-h-64 overflow-auto">
                    {(filter.options ?? []).map((option) => {
                        const checked = selectedValues.includes(String(option.value));

                        if (filter.multiple) {
                            return (
                                <div
                                    key={String(option.value)}
                                    role="menuitemcheckbox"
                                    aria-checked={checked}
                                    tabIndex={0}
                                    className="hover:bg-accent flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none focus-visible:bg-accent"
                                    onClick={() => toggleMultiple(option.value, !checked)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault();
                                            toggleMultiple(option.value, !checked);
                                        }
                                    }}
                                >
                                    <Checkbox
                                        checked={checked}
                                        onClick={(event) => event.stopPropagation()}
                                        onCheckedChange={(nextChecked) => toggleMultiple(option.value, Boolean(nextChecked))}
                                    />
                                    {optionColor(option.color)}
                                    <span className="flex-1">{option.label}</span>
                                </div>
                            );
                        }

                        return (
                            <button
                                key={String(option.value)}
                                type="button"
                                className={cn(
                                    'hover:bg-accent flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm',
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
