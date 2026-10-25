import {
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    X,
} from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/fluid/select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

import {
    dateToApiDateTime,
    dateToInputValue,
    defaultDatePresets,
    toApiDateTime,
    toDateInputValue,
} from '../core/date-presets';
import { filterValueLabel } from '../core/filter-presentation';
import { isActiveFilterValue, rangeValue } from '../core/filter-utils';
import type {
    DataTableDateColumn,
    DataTableDatePreset,
    DataTableFilterValue,
} from '../core/types';

const MONTH_NAMES = [
    'Janeiro',
    'Fevereiro',
    'Março',
    'Abril',
    'Maio',
    'Junho',
    'Julho',
    'Agosto',
    'Setembro',
    'Outubro',
    'Novembro',
    'Dezembro',
];
const DAY_NAMES = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

function buildCalendarCells(
    year: number,
    month: number,
): Array<{ day: number; dateStr: string | null; isCurrentMonth: boolean }> {
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    const cells: Array<{
        day: number;
        dateStr: string | null;
        isCurrentMonth: boolean;
    }> = [];

    for (let i = firstWeekday - 1; i >= 0; i--) {
        cells.push({
            day: daysInPrevMonth - i,
            dateStr: null,
            isCurrentMonth: false,
        });
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const m = String(month + 1).padStart(2, '0');
        const day = String(d).padStart(2, '0');
        cells.push({
            day: d,
            dateStr: `${year}-${m}-${day}`,
            isCurrentMonth: true,
        });
    }

    const remaining = Math.ceil(cells.length / 7) * 7 - cells.length;

    for (let d = 1; d <= remaining; d++) {
        cells.push({ day: d, dateStr: null, isCurrentMonth: false });
    }

    return cells;
}

function formatDisplayDate(dateStr: string): string {
    const parts = dateStr.split('-');

    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

export function DatePickerInput({
    value,
    onChange,
    placeholder = 'Selecione',
    minDate,
    label,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    minDate?: string;
    label?: string;
}) {
    const [open, setOpen] = useState(false);
    const today = new Date();
    const parsedDate = value ? new Date(value + 'T00:00:00') : null;
    const [viewYear, setViewYear] = useState(
        parsedDate?.getFullYear() ?? today.getFullYear(),
    );
    const [viewMonth, setViewMonth] = useState(
        parsedDate?.getMonth() ?? today.getMonth(),
    );
    const todayStr = dateToInputValue(today);

    function prevMonth(): void {
        if (viewMonth === 0) {
            setViewMonth(11);
            setViewYear((y) => y - 1);
        } else {
            setViewMonth((m) => m - 1);
        }
    }

    function nextMonth(): void {
        if (viewMonth === 11) {
            setViewMonth(0);
            setViewYear((y) => y + 1);
        } else {
            setViewMonth((m) => m + 1);
        }
    }

    const cells = buildCalendarCells(viewYear, viewMonth);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    aria-label={`${label ?? placeholder}${value ? `: ${formatDisplayDate(value)}` : ''}`}
                    className={cn(
                        'w-full justify-start gap-2 border-input text-left font-normal',
                        !value && 'text-muted-foreground',
                    )}
                >
                    <CalendarDays className="size-4 shrink-0" />
                    {value ? formatDisplayDate(value) : placeholder}
                </Button>
            </PopoverTrigger>

            <PopoverContent align="start" className="w-auto p-0">
                {/* Header */}
                <div className="flex items-center justify-between border-b px-2 py-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={prevMonth}
                        aria-label="Mês anterior"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span className="text-sm font-semibold">
                        {MONTH_NAMES[viewMonth]} {viewYear}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={nextMonth}
                        aria-label="Próximo mês"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>

                {/* Day names */}
                <div className="grid grid-cols-7 border-b px-2 py-1">
                    {DAY_NAMES.map((d) => (
                        <div
                            key={d}
                            className="flex h-7 w-9 items-center justify-center text-[11px] font-medium text-muted-foreground"
                        >
                            {d}
                        </div>
                    ))}
                </div>

                {/* Day cells */}
                <div className="grid grid-cols-7 gap-y-0.5 p-2">
                    {cells.map((cell, i) => {
                        if (!cell.isCurrentMonth || !cell.dateStr) {
                            return (
                                <div
                                    key={i}
                                    className="flex h-9 w-9 items-center justify-center text-xs text-muted-foreground/30"
                                >
                                    {cell.day}
                                </div>
                            );
                        }

                        const isSelected = cell.dateStr === value;
                        const isToday = cell.dateStr === todayStr;
                        const isDisabled = minDate
                            ? cell.dateStr < minDate
                            : false;

                        return (
                            <button
                                key={i}
                                type="button"
                                disabled={isDisabled}
                                aria-label={formatDisplayDate(cell.dateStr)}
                                aria-pressed={isSelected}
                                onClick={() => {
                                    onChange(cell.dateStr!);
                                    setOpen(false);
                                }}
                                className={cn(
                                    'flex h-9 w-9 items-center justify-center rounded-md text-xs font-medium transition-colors',
                                    isSelected &&
                                        'bg-primary text-primary-foreground shadow-sm',
                                    !isSelected &&
                                        isToday &&
                                        'border border-primary/40 text-primary',
                                    !isSelected &&
                                        !isToday &&
                                        'hover:bg-accent',
                                    isDisabled &&
                                        'cursor-not-allowed opacity-25',
                                )}
                            >
                                {cell.day}
                            </button>
                        );
                    })}
                </div>

                {value && (
                    <div className="border-t px-2 py-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 w-full text-xs text-muted-foreground"
                            onClick={() => {
                                onChange('');
                                setOpen(false);
                            }}
                        >
                            <X className="size-3" />
                            Limpar
                        </Button>
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}

export function DateFilterSection({
    columns,
    presets = defaultDatePresets,
    values,
    onChange,
}: {
    columns: DataTableDateColumn[];
    presets?: DataTableDatePreset[];
    values: Record<string, DataTableFilterValue>;
    onChange: (id: string, value: DataTableFilterValue) => void;
}) {
    const activeColumn =
        columns.find((column) => isActiveFilterValue(values[column.value]))
            ?.value ??
        columns[0]?.value ??
        '';
    const [selectedColumn, setSelectedColumn] = useState(activeColumn);
    const [activePreset, setActivePreset] = useState<string | null>(null);
    const selectedValue = rangeValue(values[selectedColumn]);
    const hasDateFilter = isActiveFilterValue(values[selectedColumn]);

    function setRange(from?: string, to?: string): void {
        setActivePreset('custom');
        onChange(selectedColumn, {
            from: toApiDateTime(from ?? '', 'from'),
            to: toApiDateTime(to ?? '', 'to'),
        });
    }

    function applyPreset(preset: DataTableDatePreset): void {
        const range = preset.getRange();

        setActivePreset(preset.value);
        onChange(selectedColumn, {
            from: dateToApiDateTime(range.from, 'from'),
            to: dateToApiDateTime(range.to, 'to'),
        });
    }

    function clearDateFilter(): void {
        setActivePreset(null);
        onChange(selectedColumn, undefined);
    }

    if (columns.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3 border-t border-border/40 px-4 py-3">
            <div className="flex flex-col gap-2 md:flex-row md:items-center">
                <Select
                    value={selectedColumn}
                    onValueChange={(value) => {
                        if (value !== selectedColumn && hasDateFilter) {
                            onChange(selectedColumn, undefined);
                        }

                        setSelectedColumn(value);
                        setActivePreset(null);
                    }}
                >
                    <SelectTrigger
                        icon={CalendarDays}
                        className="w-full md:w-60"
                        aria-label="Campo de data"
                        placeholder="Selecione a coluna"
                    />
                    <SelectContent>
                        {columns.map((column, index) => (
                            <SelectItem
                                key={column.value}
                                index={index}
                                value={column.value}
                            >
                                {column.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {hasDateFilter && (
                    <Badge
                        variant="secondary"
                        className="h-7 bg-primary/10 px-2.5 text-primary"
                    >
                        {
                            columns.find(
                                (column) => column.value === selectedColumn,
                            )?.label
                        }
                        :{' '}
                        {filterValueLabel(
                            { label: 'Data', type: 'date-range' },
                            selectedValue,
                        )}
                    </Badge>
                )}

                {hasDateFilter && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={clearDateFilter}
                    >
                        <X />
                        Limpar data
                    </Button>
                )}
            </div>

            <div className="flex flex-wrap gap-1.5">
                {presets.slice(0, 6).map((preset) => (
                    <Button
                        key={preset.value}
                        type="button"
                        variant={
                            activePreset === preset.value
                                ? 'default'
                                : 'secondary'
                        }
                        size="sm"
                        className="h-7 rounded-full px-2.5 text-xs"
                        aria-pressed={activePreset === preset.value}
                        onClick={() => applyPreset(preset)}
                    >
                        {preset.label}
                    </Button>
                ))}

                {presets.length > 6 && (
                    <Popover>
                        <PopoverTrigger asChild>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                className="h-7 rounded-full px-2.5 text-xs"
                            >
                                Mais
                                <ChevronDown className="size-3" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent align="start" className="w-44 p-1">
                            {presets.slice(6).map((preset) => (
                                <button
                                    key={preset.value}
                                    type="button"
                                    className={cn(
                                        'w-full rounded-md px-3 py-2 text-left text-sm hover:bg-accent',
                                        activePreset === preset.value &&
                                            'bg-primary text-primary-foreground',
                                    )}
                                    onClick={() => applyPreset(preset)}
                                    aria-pressed={activePreset === preset.value}
                                >
                                    {preset.label}
                                </button>
                            ))}
                        </PopoverContent>
                    </Popover>
                )}
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                <div className="space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground">
                        De
                    </span>
                    <DatePickerInput
                        value={toDateInputValue(selectedValue.from)}
                        placeholder="Data inicial"
                        label={`${columns.find((column) => column.value === selectedColumn)?.label ?? 'Data'}: início`}
                        onChange={(date) =>
                            setRange(date, toDateInputValue(selectedValue.to))
                        }
                    />
                </div>
                <div className="space-y-1.5">
                    <span className="text-xs font-medium text-muted-foreground">
                        Até
                    </span>
                    <DatePickerInput
                        value={toDateInputValue(selectedValue.to)}
                        placeholder="Data final"
                        label={`${columns.find((column) => column.value === selectedColumn)?.label ?? 'Data'}: fim`}
                        minDate={
                            toDateInputValue(selectedValue.from) || undefined
                        }
                        onChange={(date) =>
                            setRange(toDateInputValue(selectedValue.from), date)
                        }
                    />
                </div>
            </div>
        </div>
    );
}
