import { Check, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

import { isActiveFilterValue, rangeValue, valueToString } from '../core/filter-utils';
import type {
    DataTableFilterConfig,
    DataTableFilterOption,
    DataTableFilterValue,
} from '../core/types';

export function renderOptionMarker(option: DataTableFilterOption) {
    if (!option.color) {
        return null;
    }

    return (
        <span
            className="size-2.5 rounded-full"
            style={{ backgroundColor: option.color }}
        />
    );
}

export function CategoryContent({
    filter,
    value,
    onChange,
}: {
    filter: DataTableFilterConfig;
    value: DataTableFilterValue;
    onChange: (value: DataTableFilterValue) => void;
}) {
    if (filter.type === 'select' || filter.type === 'boolean') {
        return (
            <div className="space-y-2">
                <Select
                    value={valueToString(value)}
                    onValueChange={(nextValue) => {
                        const selectedOption = filter.options?.find(
                            (option) => String(option.value) === nextValue,
                        );

                        onChange(selectedOption?.value ?? nextValue);
                    }}
                >
                    <SelectTrigger className="w-full">
                        <SelectValue
                            placeholder={filter.placeholder ?? 'Selecionar'}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {filter.options?.map((option) => (
                            <SelectItem
                                key={String(option.value)}
                                value={String(option.value)}
                            >
                                <span className="inline-flex items-center gap-2">
                                    {renderOptionMarker(option)}
                                    {option.label}
                                </span>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {filter.clearable !== false && isActiveFilterValue(value) && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange(undefined)}
                    >
                        <X />
                        Limpar
                    </Button>
                )}
            </div>
        );
    }

    if (filter.type === 'multi-select') {
        const selectedValues = Array.isArray(value) ? value.map(String) : [];

        return (
            <div className="space-y-2">
                {filter.options?.map((option) => {
                    const optionValue = String(option.value);
                    const checked = selectedValues.includes(optionValue);

                    return (
                        <label
                            key={optionValue}
                            className={cn(
                                'flex cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors hover:bg-accent',
                                checked && 'bg-accent',
                            )}
                        >
                            <Checkbox
                                checked={checked}
                                onCheckedChange={(nextChecked) => {
                                    const nextValues = nextChecked
                                        ? [...selectedValues, optionValue]
                                        : selectedValues.filter(
                                              (selectedValue) =>
                                                  selectedValue !== optionValue,
                                          );

                                    onChange(nextValues);
                                }}
                            />
                            {renderOptionMarker(option)}
                            <span className="flex-1">{option.label}</span>
                            {checked && (
                                <Check className="size-4 text-primary" />
                            )}
                        </label>
                    );
                })}
            </div>
        );
    }

    if (filter.type === 'date-range' || filter.type === 'number-range') {
        const currentRange = rangeValue(value);
        const inputType = filter.type === 'date-range' ? 'date' : 'number';

        return (
            <div className="grid grid-cols-2 gap-3">
                <label className="space-y-1.5 text-sm">
                    <span className="text-xs font-medium text-muted-foreground">
                        De
                    </span>
                    <Input
                        type={inputType}
                        value={currentRange.from ?? ''}
                        onChange={(event) =>
                            onChange({
                                ...currentRange,
                                from: event.target.value,
                            })
                        }
                    />
                </label>
                <label className="space-y-1.5 text-sm">
                    <span className="text-xs font-medium text-muted-foreground">
                        Até
                    </span>
                    <Input
                        type={inputType}
                        value={currentRange.to ?? ''}
                        onChange={(event) =>
                            onChange({
                                ...currentRange,
                                to: event.target.value,
                            })
                        }
                    />
                </label>
            </div>
        );
    }

    if (filter.type === 'date' || filter.type === 'number') {
        return (
            <Input
                type={filter.type === 'date' ? 'date' : 'number'}
                placeholder={filter.placeholder}
                value={valueToString(value)}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }

    return (
        <Input
            placeholder={filter.placeholder}
            value={valueToString(value)}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}
