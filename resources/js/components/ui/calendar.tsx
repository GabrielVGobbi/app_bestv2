'use client';

import * as React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
    format,
    startOfMonth,
    endOfMonth,
    startOfWeek,
    endOfWeek,
    addDays,
    addMonths,
    subMonths,
    isSameMonth,
    isSameDay,
    isToday,
    isBefore,
    isAfter,
    type Locale,
} from 'date-fns';
import { ptBR } from 'date-fns/locale';

export interface CalendarProps {
    mode?: 'single' | 'range';
    selected?: Date | { from?: Date; to?: Date };
    onSelect?: (date: Date | undefined) => void;
    onRangeSelect?: (range: { from?: Date; to?: Date }) => void;
    disabled?: (date: Date) => boolean;
    locale?: Locale;
    className?: string;
    initialFocus?: boolean;
    showOutsideDays?: boolean;
    minDate?: Date;
    maxDate?: Date;
}

const WEEKDAYS_SHORT = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

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
    const [currentMonth, setCurrentMonth] = React.useState(() => {
        if (mode === 'single' && selected instanceof Date) {
            return startOfMonth(selected);
        }
        if (mode === 'range' && selected && typeof selected === 'object' && 'from' in selected && selected.from) {
            return startOfMonth(selected.from);
        }
        return startOfMonth(new Date());
    });

    const [hoveredDate, setHoveredDate] = React.useState<Date | null>(null);

    // Generate calendar days
    const generateCalendarDays = React.useMemo(() => {
        const monthStart = startOfMonth(currentMonth);
        const monthEnd = endOfMonth(currentMonth);
        const calendarStart = startOfWeek(monthStart, { weekStartsOn: 0 });
        const calendarEnd = endOfWeek(monthEnd, { weekStartsOn: 0 });

        const days: Date[] = [];
        let day = calendarStart;

        while (day <= calendarEnd) {
            days.push(day);
            day = addDays(day, 1);
        }

        return days;
    }, [currentMonth]);

    // Navigation handlers
    const goToPreviousMonth = () => setCurrentMonth(subMonths(currentMonth, 1));
    const goToNextMonth = () => setCurrentMonth(addMonths(currentMonth, 1));

    // Check if date is selected
    const isSelected = (date: Date): boolean => {
        if (mode === 'single' && selected instanceof Date) {
            return isSameDay(date, selected);
        }
        if (mode === 'range' && selected && typeof selected === 'object' && 'from' in selected) {
            const { from, to } = selected;
            if (from && isSameDay(date, from)) return true;
            if (to && isSameDay(date, to)) return true;
        }
        return false;
    };

    // Check if date is in range
    const isInRange = (date: Date): boolean => {
        if (mode !== 'range' || !selected || typeof selected !== 'object' || !('from' in selected)) {
            return false;
        }
        const { from, to } = selected;

        // If we have both from and to
        if (from && to) {
            return isAfter(date, from) && isBefore(date, to);
        }

        // If we only have from and hovering
        if (from && !to && hoveredDate) {
            const rangeStart = isBefore(from, hoveredDate) ? from : hoveredDate;
            const rangeEnd = isAfter(from, hoveredDate) ? from : hoveredDate;
            return isAfter(date, rangeStart) && isBefore(date, rangeEnd);
        }

        return false;
    };

    // Check if date is range start or end
    const isRangeStart = (date: Date): boolean => {
        if (mode !== 'range' || !selected || typeof selected !== 'object' || !('from' in selected)) {
            return false;
        }
        return selected.from ? isSameDay(date, selected.from) : false;
    };

    const isRangeEnd = (date: Date): boolean => {
        if (mode !== 'range' || !selected || typeof selected !== 'object' || !('from' in selected)) {
            return false;
        }
        return selected.to ? isSameDay(date, selected.to) : false;
    };

    // Check if date is disabled
    const isDateDisabled = (date: Date): boolean => {
        if (disabled && disabled(date)) return true;
        if (minDate && isBefore(date, minDate)) return true;
        if (maxDate && isAfter(date, maxDate)) return true;
        return false;
    };

    // Handle date click
    const handleDateClick = (date: Date) => {
        if (isDateDisabled(date)) return;

        if (mode === 'single') {
            onSelect?.(date);
        } else if (mode === 'range') {
            if (!selected || typeof selected !== 'object' || !('from' in selected)) {
                onRangeSelect?.({ from: date, to: undefined });
            } else {
                const { from, to } = selected;

                if (!from || (from && to)) {
                    // Start new selection
                    onRangeSelect?.({ from: date, to: undefined });
                } else {
                    // Complete the range
                    if (isBefore(date, from)) {
                        onRangeSelect?.({ from: date, to: from });
                    } else {
                        onRangeSelect?.({ from, to: date });
                    }
                }
            }
        }
    };

    // Split days into weeks
    const weeks = React.useMemo(() => {
        const result: Date[][] = [];
        for (let i = 0; i < generateCalendarDays.length; i += 7) {
            result.push(generateCalendarDays.slice(i, i + 7));
        }
        return result;
    }, [generateCalendarDays]);

    return (
        <div className={cn('p-3 select-none', className)}>
            {/* Header with navigation */}
            <div className="flex items-center justify-between mb-4">
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 rounded-full hover:bg-accent transition-colors"
                    onClick={goToPreviousMonth}
                >
                    <ChevronLeft className="h-4 w-4" />
                </Button>

                <h2 className="text-sm font-semibold capitalize">
                    {format(currentMonth, 'MMMM yyyy', { locale })}
                </h2>

                <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 rounded-full hover:bg-accent transition-colors"
                    onClick={goToNextMonth}
                >
                    <ChevronRight className="h-4 w-4" />
                </Button>
            </div>

            {/* Weekday headers */}
            <div className="grid grid-cols-7 mb-2">
                {WEEKDAYS_SHORT.map((day) => (
                    <div
                        key={day}
                        className="h-8 flex items-center justify-center text-xs font-medium text-muted-foreground"
                    >
                        {day}
                    </div>
                ))}
            </div>

            {/* Calendar grid */}
            <div className="space-y-1">
                {weeks.map((week, weekIndex) => (
                    <div key={weekIndex} className="grid grid-cols-7">
                        {week.map((date, dayIndex) => {
                            const isCurrentMonth = isSameMonth(date, currentMonth);
                            const isSelectedDate = isSelected(date);
                            const isTodayDate = isToday(date);
                            const isDisabled = isDateDisabled(date);
                            const inRange = isInRange(date);
                            const rangeStart = isRangeStart(date);
                            const rangeEnd = isRangeEnd(date);

                            if (!showOutsideDays && !isCurrentMonth) {
                                return <div key={dayIndex} className="h-9 w-9" />;
                            }

                            return (
                                <div
                                    key={dayIndex}
                                    className={cn(
                                        'relative flex items-center justify-center',
                                        // Range background
                                        inRange && 'bg-primary/10',
                                        rangeStart && 'rounded-l-full bg-primary/10',
                                        rangeEnd && 'rounded-r-full bg-primary/10',
                                        (rangeStart || rangeEnd) && inRange && 'bg-primary/10'
                                    )}
                                >
                                    <button
                                        type="button"
                                        disabled={isDisabled}
                                        onClick={() => handleDateClick(date)}
                                        onMouseEnter={() => mode === 'range' && setHoveredDate(date)}
                                        onMouseLeave={() => mode === 'range' && setHoveredDate(null)}
                                        className={cn(
                                            'h-9 w-9 rounded-full text-sm font-normal transition-all duration-200',
                                            'hover:bg-accent hover:text-accent-foreground',
                                            'focus:outline-none focus:ring-2 focus:ring-primary/20 focus:ring-offset-1',
                                            // Outside month
                                            !isCurrentMonth && 'text-muted-foreground/40',
                                            // Today
                                            isTodayDate && !isSelectedDate && 'bg-accent text-accent-foreground font-medium',
                                            // Selected
                                            isSelectedDate && 'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground shadow-sm',
                                            // Disabled
                                            isDisabled && 'opacity-40 cursor-not-allowed hover:bg-transparent',
                                            // Range endpoints
                                            (rangeStart || rangeEnd) && 'bg-primary text-primary-foreground'
                                        )}
                                    >
                                        {format(date, 'd')}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
        </div>
    );
}

Calendar.displayName = 'Calendar';

export { Calendar };
