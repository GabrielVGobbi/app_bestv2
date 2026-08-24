import { format, isValid, parseISO } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type DatePickerProps = {
    /** Nome do campo para submit nativo (Inertia <Form>). Posta no formato yyyy-MM-dd. */
    name?: string;
    id?: string;
    /** Valor controlado. */
    value?: Date;
    /** Valor inicial (Date ou string ISO) para uso nao-controlado. */
    defaultValue?: Date | string;
    onChange?: (date: Date | undefined) => void;
    placeholder?: string;
    disabled?: boolean;
    minDate?: Date;
    maxDate?: Date;
    className?: string;
    'aria-invalid'?: boolean;
};

function toDate(value: Date | string | undefined): Date | undefined {
    if (!value) {
        return undefined;
    }

    if (value instanceof Date) {
        return isValid(value) ? value : undefined;
    }

    const parsed = parseISO(value);

    return isValid(parsed) ? parsed : undefined;
}

/**
 * Seletor de data padrao do projeto (Calendar + Popover).
 * - Exibe a data em pt-BR (dd/MM/yyyy) e posta em yyyy-MM-dd via `name`.
 * - Funciona controlado (value/onChange) ou nao-controlado (defaultValue + name).
 */
function DatePicker({
    name,
    id,
    value,
    defaultValue,
    onChange,
    placeholder = 'Selecione uma data',
    disabled,
    minDate,
    maxDate,
    className,
    'aria-invalid': ariaInvalid,
}: DatePickerProps) {
    const isControlled = value !== undefined;
    const [internal, setInternal] = React.useState<Date | undefined>(() => toDate(defaultValue));
    const [open, setOpen] = React.useState(false);

    const selected = isControlled ? value : internal;

    function handleSelect(date: Date | undefined): void {
        if (!isControlled) {
            setInternal(date);
        }

        onChange?.(date);
        setOpen(false);
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    id={id}
                    variant="outline"
                    disabled={disabled}
                    aria-invalid={ariaInvalid}
                    className={cn(
                        'h-9 min-w-0 w-full justify-start overflow-hidden text-left font-normal',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    <CalendarIcon className="mr-2 size-4 shrink-0" />
                    <span className="min-w-0 truncate">
                        {selected
                            ? format(selected, 'dd/MM/yyyy', { locale: ptBR })
                            : placeholder}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    selected={selected}
                    onSelect={handleSelect}
                    minDate={minDate}
                    maxDate={maxDate}
                />
            </PopoverContent>
            {name ? <input type="hidden" name={name} value={selected ? format(selected, 'yyyy-MM-dd') : ''} /> : null}
        </Popover>
    );
}

export { DatePicker };
