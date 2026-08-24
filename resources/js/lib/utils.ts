import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

import { maskCnpj, maskCpf, maskCpfCnpj, maskPhone, onlyDigits } from './masks';

type MoneyValue = string | number | null | undefined;

interface MoneyFormatOptions {
    fallback?: string;
    fromCents?: boolean;
}

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function formatDateOnly(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const datePart = String(value).slice(0, 10);
    const [year, month, day] = datePart.split('-').map(Number);

    if (!year || !month || !day) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

function originalOrEmpty(value: string | null | undefined): string {
    return value?.trim() ?? '';
}

export function formatCpf(value: string | null | undefined): string {
    const digits = onlyDigits(value);

    return digits.length === 11 ? maskCpf(digits) : originalOrEmpty(value);
}

export function formatCnpj(value: string | null | undefined): string {
    const digits = onlyDigits(value);

    return digits.length === 14 ? maskCnpj(digits) : originalOrEmpty(value);
}

export function formatCpfCnpj(value: string | null | undefined): string {
    const digits = onlyDigits(value);

    if (digits.length !== 11 && digits.length !== 14) {
        return originalOrEmpty(value);
    }

    return maskCpfCnpj(digits);
}

export function formatPhone(value: string | null | undefined): string {
    const digits = onlyDigits(value);

    if (digits.length !== 10 && digits.length !== 11) {
        return originalOrEmpty(value);
    }

    return maskPhone(digits);
}

function parseMoneyValue(value: MoneyValue, fromCents: boolean): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'number') {
        return fromCents ? value / 100 : value;
    }

    const trimmedValue = value.trim();

    if (trimmedValue === '') {
        return null;
    }

    if (fromCents) {
        const cents = Number(onlyDigits(trimmedValue));

        return Number.isFinite(cents) ? cents / 100 : null;
    }

    const hasDecimalSeparator =
        trimmedValue.includes(',') || trimmedValue.includes('.');

    if (!hasDecimalSeparator) {
        const cents = Number(onlyDigits(trimmedValue));

        return Number.isFinite(cents) ? cents / 100 : null;
    }

    const normalizedValue = trimmedValue
        .replace(/[^\d,.-]/g, '')
        .replace(/\.(?=.*[,])/g, '')
        .replace(',', '.');

    const amount = Number(normalizedValue);

    return Number.isFinite(amount) ? amount : null;
}

export function formatMoney(
    value: MoneyValue,
    options: MoneyFormatOptions = {},
): string {
    const amount = parseMoneyValue(value, options.fromCents ?? false);

    if (amount === null) {
        return options.fallback ?? '';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(amount);
}

export const formatCurrencyBRL = formatMoney;
