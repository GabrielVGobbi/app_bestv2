import type { Table } from '@tanstack/react-table';
import { useEffect, useState } from 'react';
import type { RefObject } from 'react';

import { getStickyGeometry, resolveColumnWidths } from '../core/table-layout';
import type { DataTableState } from '../core/types';

export function useTableGeometry<TData>(
    viewport: RefObject<HTMLDivElement | null>,
    table: Table<TData>,
    state: DataTableState,
) {
    const [width, setWidth] = useState(0);
    useEffect(() => {
        const element = viewport.current;

        if (!element) {
            return;
        }

        const measure = () => setWidth(element.clientWidth);
        const observer = new ResizeObserver(measure);
        observer.observe(element);
        measure();

        return () => observer.disconnect();
    }, [viewport]);

    const columns = table.getVisibleLeafColumns().map((column) => ({
        id: column.id,
        resizable: column.getCanResize(),
        minSize: column.columnDef.minSize,
        maxSize: column.columnDef.maxSize,
        size: Math.max(
            column.columnDef.minSize ?? 60,
            Math.min(
                column.columnDef.maxSize ?? 1200,
                state.columnSizing[column.id] ?? column.getSize(),
            ),
        ),
    }));
    const widths = resolveColumnWidths(columns, state.columnSizing, width);

    return {
        widths,
        geometry: getStickyGeometry(
            columns.map((column) => ({
                id: column.id,
                size: widths[column.id],
            })),
            state.columnPinning,
            width || Infinity,
        ),
    };
}
