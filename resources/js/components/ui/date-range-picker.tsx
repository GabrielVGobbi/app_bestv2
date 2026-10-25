import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { CalendarIcon, X, Save } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { Calendar, type CalendarRange } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type DateRange = CalendarRange;

interface DateRangePickerProps {
    value?: DateRange;
    onChange: (range: DateRange | undefined) => void;
    placeholder?: string;
    minDate?: Date;
    maxDate?: Date;
    align?: 'start' | 'center' | 'end';
    disabled?: boolean;
    className?: string;
}

function rangeLabel(range?: DateRange): string | undefined {
    if (!range?.from) {
        return undefined;
    }

    const from = format(range.from, 'd MMM. yyyy', { locale: ptBR });
    return range.to
        ? `${from} → ${format(range.to, 'd MMM. yyyy', { locale: ptBR })}`
        : from;
}

export function DateRangePicker({
    value,
    onChange,
    placeholder = 'Personalizado',
    minDate,
    maxDate,
    align = 'start',
    disabled = false,
    className,
}: DateRangePickerProps) {
    const [open, setOpen] = React.useState(false);
    const [pending, setPending] = React.useState<DateRange | undefined>(value);
    const label = rangeLabel(value);

    return (
        <Popover
            open={disabled ? false : open}
            onOpenChange={(nextOpen) => {
                if (nextOpen) setPending(value);
                setOpen(nextOpen);
            }}
        >
            <PopoverTrigger asChild>
                <button
                    type="button"
                    disabled={disabled}
                    className={cn(
                        'flex h-9 items-center gap-2 rounded-full border px-3 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                        label
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-card text-muted-foreground hover:bg-secondary',
                        className,
                    )}
                >
                    <CalendarIcon className="size-3.5" />
                    {label ?? placeholder}
                </button>
            </PopoverTrigger>
            <PopoverContent
                align={align}
                sideOffset={8}
                className="w-auto overflow-hidden p-0"
            >
                <Calendar
                    mode="range"
                    selected={pending}
                    onRangeSelect={setPending}
                    minDate={minDate}
                    maxDate={maxDate}
                    showOutsideDays={false}
                />
                <div className="flex items-center justify-between gap-3 border-t bg-muted/50 px-4 py-2.5">
                    <span className="min-w-0 truncate text-xs text-muted-foreground">
                        {rangeLabel(pending) ?? 'Selecione o intervalo'}
                    </span>
                    <div className="flex shrink-0 items-center gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setPending(undefined);
                                onChange(undefined);
                                setOpen(false);
                            }}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Limpar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={!pending?.from || !pending.to}
                            onClick={() => {
                                onChange(pending);
                                setOpen(false);
                            }}
                        >
                            <Save className="size-4" aria-hidden="true" />
                            Aplicar
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
