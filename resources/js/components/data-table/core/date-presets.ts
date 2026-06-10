import type { DataTableDatePreset } from './types';

function startOfDay(date: Date): Date {
    const nextDate = new Date(date);

    nextDate.setHours(0, 0, 0, 0);

    return nextDate;
}

function endOfDay(date: Date): Date {
    const nextDate = new Date(date);

    nextDate.setHours(23, 59, 59, 999);

    return nextDate;
}

function addDays(date: Date, days: number): Date {
    const nextDate = new Date(date);

    nextDate.setDate(nextDate.getDate() + days);

    return nextDate;
}

function startOfMonth(date: Date): Date {
    return startOfDay(new Date(date.getFullYear(), date.getMonth(), 1));
}

function endOfMonth(date: Date): Date {
    return endOfDay(new Date(date.getFullYear(), date.getMonth() + 1, 0));
}

function startOfWeek(date: Date): Date {
    return startOfDay(addDays(date, -date.getDay()));
}

function endOfWeek(date: Date): Date {
    return endOfDay(addDays(startOfWeek(date), 6));
}

export function toDateInputValue(value?: string | number): string {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 10);
}

export function toApiDateTime(
    value: string,
    boundary: 'from' | 'to',
): string | undefined {
    if (!value) {
        return undefined;
    }

    return boundary === 'from' ? `${value} 00:00:00` : `${value} 23:59:59`;
}

export function dateToInputValue(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function dateToApiDateTime(date: Date, boundary: 'from' | 'to'): string {
    return toApiDateTime(dateToInputValue(date), boundary) ?? '';
}

export function formatShortDate(value?: string | number): string {
    const dateValue = toDateInputValue(value);

    if (!dateValue) {
        return '';
    }

    return dateValue.split('-').reverse().join('/');
}

export const defaultDatePresets: DataTableDatePreset[] = [
    {
        label: 'Hoje',
        value: 'today',
        getRange: () => ({
            from: startOfDay(new Date()),
            to: endOfDay(new Date()),
        }),
    },
    {
        label: 'Ontem',
        value: 'yesterday',
        getRange: () => {
            const yesterday = addDays(new Date(), -1);

            return { from: startOfDay(yesterday), to: endOfDay(yesterday) };
        },
    },
    {
        label: 'Esta Semana',
        value: 'this_week',
        getRange: () => ({
            from: startOfWeek(new Date()),
            to: endOfWeek(new Date()),
        }),
    },
    {
        label: 'Semana Passada',
        value: 'last_week',
        getRange: () => {
            const lastWeek = addDays(new Date(), -7);

            return { from: startOfWeek(lastWeek), to: endOfWeek(lastWeek) };
        },
    },
    {
        label: 'Este Mês',
        value: 'this_month',
        getRange: () => ({
            from: startOfMonth(new Date()),
            to: endOfMonth(new Date()),
        }),
    },
    {
        label: 'Mês Passado',
        value: 'last_month',
        getRange: () => {
            const lastMonth = new Date(
                new Date().getFullYear(),
                new Date().getMonth() - 1,
                1,
            );

            return { from: startOfMonth(lastMonth), to: endOfMonth(lastMonth) };
        },
    },
    {
        label: 'Últimos 7 dias',
        value: 'last_7_days',
        getRange: () => ({
            from: startOfDay(addDays(new Date(), -6)),
            to: endOfDay(new Date()),
        }),
    },
    {
        label: 'Últimos 30 dias',
        value: 'last_30_days',
        getRange: () => ({
            from: startOfDay(addDays(new Date(), -29)),
            to: endOfDay(new Date()),
        }),
    },
    {
        label: 'Últimos 90 dias',
        value: 'last_90_days',
        getRange: () => ({
            from: startOfDay(addDays(new Date(), -89)),
            to: endOfDay(new Date()),
        }),
    },
];
