import {
    addDays,
    addMonths,
    endOfMonth,
    endOfWeek,
    format,
    isAfter,
    isBefore,
    isSameDay,
    isSameMonth,
    isToday,
    startOfMonth,
    startOfWeek,
    subMonths,
} from 'date-fns';
import type { Locale } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface CalendarRange {
    from?: Date;
    to?: Date;
}

export interface CalendarProps {
    mode?: 'single' | 'range';
    selected?: Date | CalendarRange;
    onSelect?: (date: Date | undefined) => void;
    onRangeSelect?: (range: CalendarRange) => void;
    disabled?: (date: Date) => boolean;
    locale?: Locale;
    className?: string;
    showOutsideDays?: boolean;
    minDate?: Date;
    maxDate?: Date;
}

const weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

function isRange(value: Date | CalendarRange | undefined): value is CalendarRange {
    return !!value && !(value instanceof Date);
}

function Calendar({
    mode = 'single',
    selected,
    onSelect,
    onRangeSelect,
    disabled,
    locale = ptBR,
    className,
    showOutsideDays = true,
    minDate,
    maxDate,
}: CalendarProps) {
    const selectedStart = isRange(selected) ? selected.from : selected;
    const [month, setMonth] = React.useState(() =>
        startOfMonth(selectedStart ?? new Date()),
    );
    const [hoveredDate, setHoveredDate] = React.useState<Date>();
    const labelId = React.useId();

    React.useEffect(() => {
        if (selectedStart) {
            setMonth(startOfMonth(selectedStart));
        }
    }, [selectedStart]);

    const days = React.useMemo(() => {
        const first = startOfWeek(startOfMonth(month));
        const last = endOfWeek(endOfMonth(month));
        const result: Date[] = [];

        for (let date = first; date <= last; date = addDays(date, 1)) {
            result.push(date);
        }

        return result;
    }, [month]);

    function isUnavailable(date: Date): boolean {
        return (
            Boolean(disabled?.(date)) ||
            Boolean(minDate && isBefore(date, minDate)) ||
            Boolean(maxDate && isAfter(date, maxDate))
        );
    }

    function choose(date: Date): void {
        if (isUnavailable(date)) {
            return;
        }

        if (mode === 'single') {
            onSelect?.(date);
            return;
        }

        const range = isRange(selected) ? selected : undefined;
        if (!range?.from || range.to) {
            onRangeSelect?.({ from: date });
            return;
        }

        onRangeSelect?.(
            isBefore(date, range.from)
                ? { from: date, to: range.from }
                : { from: range.from, to: date },
        );
    }

    function selectionState(date: Date) {
        const range = isRange(selected) ? selected : undefined;
        const selectedDay = selected instanceof Date && isSameDay(date, selected);
        const start = Boolean(range?.from && isSameDay(date, range.from));
        const end = Boolean(range?.to && isSameDay(date, range.to));
        const provisionalEnd = range?.from && !range.to ? hoveredDate : undefined;
        const lower = range?.from && provisionalEnd && isBefore(provisionalEnd, range.from) ? provisionalEnd : range?.from;
        const upper = provisionalEnd && range?.from && isBefore(provisionalEnd, range.from) ? range.from : (range?.to ?? provisionalEnd);
        const within = Boolean(lower && upper && isAfter(date, lower) && isBefore(date, upper));

        return { selectedDay, start, end, within };
    }

    return (
        <div className={cn('w-80 p-3 select-none', className)}>
            <div className="mb-4 flex items-center justify-between">
                <Button type="button" variant="ghost" size="icon" className="size-8 rounded-full" onClick={() => setMonth(subMonths(month, 1))} aria-label="Mês anterior">
                    <ChevronLeft className="size-4" />
                </Button>
                <h2 id={labelId} className="text-sm font-semibold capitalize" aria-live="polite">
                    {format(month, 'MMMM yyyy', { locale })}
                </h2>
                <Button type="button" variant="ghost" size="icon" className="size-8 rounded-full" onClick={() => setMonth(addMonths(month, 1))} aria-label="Próximo mês">
                    <ChevronRight className="size-4" />
                </Button>
            </div>
            <div className="mb-2 grid grid-cols-7">
                {weekdays.map((weekday) => (
                    <span key={weekday} className="flex h-8 items-center justify-center text-xs font-medium text-muted-foreground">
                        {weekday}
                    </span>
                ))}
            </div>
            <div role="grid" aria-labelledby={labelId} className="grid grid-cols-7 gap-y-1">
                {days.map((date) => {
                    const unavailable = isUnavailable(date);
                    const { selectedDay, start, end, within } = selectionState(date);
                    const currentMonth = isSameMonth(date, month);

                    if (!showOutsideDays && !currentMonth) {
                        return <span key={date.toISOString()} className="size-10" />;
                    }

                    return (
                        <span key={date.toISOString()} role="gridcell" className={cn('flex size-10 items-center justify-center', within && 'bg-primary/10', start && 'rounded-l-full bg-primary/10', end && 'rounded-r-full bg-primary/10')}>
                            <button
                                type="button"
                                disabled={unavailable}
                                aria-label={format(date, "d 'de' MMMM 'de' yyyy", { locale })}
                                aria-pressed={selectedDay || start || end}
                                aria-current={isToday(date) ? 'date' : undefined}
                                onClick={() => choose(date)}
                                onMouseEnter={() => mode === 'range' && setHoveredDate(date)}
                                onMouseLeave={() => setHoveredDate(undefined)}
                                className={cn(
                                    'size-9 rounded-full text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    !currentMonth && 'text-muted-foreground/40',
                                    isToday(date) && !selectedDay && !start && !end && 'bg-accent font-medium',
                                    (selectedDay || start || end) && 'bg-primary text-primary-foreground hover:bg-primary',
                                    !selectedDay && !start && !end && 'hover:bg-accent',
                                    unavailable && 'cursor-not-allowed opacity-40 hover:bg-transparent',
                                )}
                            >
                                {format(date, 'd')}
                            </button>
                        </span>
                    );
                })}
            </div>
        </div>
    );
}

export { Calendar };
