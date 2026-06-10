import type { DataTableFilterOption, DataTableFilterValue } from './types';

/**
 * Returns true when a filter value is non-empty (i.e. the filter is active).
 * Single source of truth — replaces scattered hasValue / hasFilterValue /
 * isEmptyFilterValue helpers across the data-table system.
 */
export function isActiveFilterValue(value: DataTableFilterValue): boolean {
    if (value === null || value === undefined || value === '') {
        return false;
    }

    if (Array.isArray(value)) {
        return value.length > 0;
    }

    if (typeof value === 'object') {
        return Boolean(value.from || value.to);
    }

    return true;
}

/**
 * Coerces a scalar filter value to its string representation.
 * Returns '' for null, undefined, arrays, and objects.
 */
export function valueToString(value: DataTableFilterValue): string {
    if (
        value === null ||
        value === undefined ||
        Array.isArray(value) ||
        typeof value === 'object'
    ) {
        return '';
    }

    return String(value);
}

/**
 * Safely extracts a `{ from?, to? }` range from a filter value.
 * Returns `{}` when the value is not a range object.
 */
export function rangeValue(value: DataTableFilterValue): {
    from?: string | number;
    to?: string | number;
} {
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        return value;
    }

    return {};
}

/**
 * Returns the display label for an option value, falling back to String(value).
 */
export function optionLabel(
    options: DataTableFilterOption[] | undefined,
    value: string | number | boolean,
): string {
    return (
        options?.find((option) => String(option.value) === String(value))
            ?.label ?? String(value)
    );
}
