import { formatShortDate } from './date-presets';
import { isActiveFilterValue, optionLabel } from './filter-utils';
import type {
    DataTableFilterConfig,
    DataTableFilterValue,
    DataTableQuickFilter,
} from './types';

type PresentableFilter = Pick<
    DataTableFilterConfig,
    'label' | 'placeholder' | 'options'
> &
    Partial<Pick<DataTableFilterConfig, 'type'>>;

export interface DataTableFilterChip {
    id: string;
    title: string;
    label: string;
    value?: string;
    type?: 'date' | 'filter';
}

export function filterPlaceholder(
    filter: PresentableFilter,
    boundary?: 'from' | 'to',
): string {
    if (boundary) {
        const noun = filter.type === 'date-range' ? 'Data' : 'Valor';

        return `${noun} ${boundary === 'from' ? 'inicial' : 'final'}`;
    }

    if (filter.placeholder) {
        return filter.placeholder;
    }

    if (filter.type === 'text') {
        return `Filtrar por ${filter.label}`;
    }

    if (filter.type === 'number') {
        return `Informe ${filter.label}`;
    }

    return filter.type === 'date' ? 'Selecione a data' : 'Todos';
}

export function filterValueLabel(
    filter: PresentableFilter,
    value: DataTableFilterValue,
): string {
    if (!isActiveFilterValue(value)) {
        return filterPlaceholder(filter);
    }

    if (Array.isArray(value)) {
        return value
            .map((item) => optionLabel(filter.options, item))
            .join(', ');
    }

    if (typeof value === 'object' && value !== null) {
        const format = filter.type === 'date-range' ? formatShortDate : String;
        const from =
            value.from !== undefined && value.from !== ''
                ? format(value.from)
                : '';
        const to =
            value.to !== undefined && value.to !== '' ? format(value.to) : '';

        if (from && to) {
            return `${from} a ${to}`;
        }

        return from ? `A partir de ${from}` : `Até ${to}`;
    }

    return optionLabel(filter.options, value as string | number | boolean);
}

export function buildFilterChips(
    filters: Array<DataTableFilterConfig | DataTableQuickFilter>,
    values: Record<string, DataTableFilterValue>,
): DataTableFilterChip[] {
    return filters.flatMap((filter): DataTableFilterChip[] => {
        const value = values[filter.id];

        if (!isActiveFilterValue(value)) {
            return [];
        }

        const title =
            'title' in filter ? (filter.title ?? filter.label) : filter.label;

        if (Array.isArray(value)) {
            return value.map((item) => ({
                id: filter.id,
                title,
                label: optionLabel(filter.options, item),
                value: String(item),
            }));
        }

        if (typeof value === 'object' && value !== null) {
            return [
                {
                    id: filter.id,
                    title,
                    label: filterValueLabel(filter, value),
                    type:
                        'type' in filter && filter.type === 'date-range'
                            ? 'date'
                            : 'filter',
                },
            ];
        }

        return [
            {
                id: filter.id,
                title,
                label: filterValueLabel(filter, value),
                value: String(value),
            },
        ];
    });
}
