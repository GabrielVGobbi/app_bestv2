import type { HeaderGroup, VisibilityState } from '@tanstack/react-table';
import { flexRender } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';

import { cn } from '@/lib/utils';

import { isColumnVisible } from '../core/column-visibility';
import { getStickyRightLayout } from '../core/sticky';
import type { DataTableAppearance, DataTableSize } from '../core/types';

const HEADER_SIZE_STYLES: Record<DataTableSize, string> = {
    sm: 'h-9 px-2.5 text-xs',
    md: 'h-11 px-3 text-xs',
};

interface DataTableHeaderProps<TData> {
    headerGroups: HeaderGroup<TData>[];
    appearance: Required<DataTableAppearance>;
    isScrolled?: boolean;
    columnVisibility?: VisibilityState;
}

export function DataTableHeader<TData>({
    headerGroups,
    appearance,
    isScrolled = false,
    columnVisibility = {},
}: DataTableHeaderProps<TData>) {
    const showHorizontalLines =
        appearance.gridLines === 'horizontal' ||
        appearance.gridLines === 'both';
    const showVerticalLines =
        appearance.gridLines === 'vertical' || appearance.gridLines === 'both';

    return (
        <thead className="sticky top-0 z-10 border-b border-white/10 bg-data-table-head">
            {headerGroups.map((headerGroup) => {
                const visibleHeaders = headerGroup.headers.filter((header) =>
                    isColumnVisible(header.column.id, columnVisibility),
                );
                const stickyLayout = getStickyRightLayout(
                    visibleHeaders.map((header) => ({
                        id: header.column.id,
                        size: header.getSize(),
                        sticky:
                            header.column.columnDef.meta?.sticky === 'right',
                    })),
                );

                return (
                    <tr
                        key={headerGroup.id}
                        className={cn(showHorizontalLines && 'border-b')}
                    >
                        {visibleHeaders.map((header) => {
                            const canSort = header.column.getCanSort();
                            const sorted = header.column.getIsSorted();
                            const align =
                                header.column.columnDef.meta?.align ?? 'left';
                            const isSticky =
                                header.column.columnDef.meta?.sticky ===
                                'right';
                            const stickyOffset =
                                stickyLayout.offsets.get(header.column.id) ?? 0;
                            const isLeadingSticky =
                                stickyLayout.leadingId === header.column.id;

                            return (
                                <th
                                    key={header.id}
                                    className={cn(
                                        'relative overflow-hidden font-medium whitespace-nowrap text-sidebar-foreground',
                                        HEADER_SIZE_STYLES[appearance.size],
                                        showVerticalLines &&
                                            'border-r last:border-r-0',
                                        align === 'center' && 'text-center',
                                        align === 'right' &&
                                            'text-right font-tabular',
                                        isSticky &&
                                            'sticky z-20 border-l border-white/10 bg-data-table-head',
                                    )}
                                    style={{
                                        width: header.getSize(),
                                        ...(isSticky && {
                                            right: stickyOffset,
                                            boxShadow:
                                                isScrolled && isLeadingSticky
                                                    ? '-12px 0 16px -4px rgba(0,0,0,0.1)'
                                                    : 'none',
                                        }),
                                    }}
                                >
                                    {header.isPlaceholder ? null : canSort ? (
                                        <button
                                            type="button"
                                            className={cn(
                                                'inline-flex w-full items-center gap-2',
                                                align === 'center' &&
                                                    'justify-center',
                                                align === 'right' &&
                                                    'justify-end',
                                            )}
                                            onClick={header.column.getToggleSortingHandler()}
                                        >
                                            {flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )}
                                            {sorted === 'asc' && (
                                                <ArrowUp className="size-3.5" />
                                            )}
                                            {sorted === 'desc' && (
                                                <ArrowDown className="size-3.5" />
                                            )}
                                            {!sorted && (
                                                <ChevronsUpDown className="size-3.5 opacity-40" />
                                            )}
                                        </button>
                                    ) : (
                                        <span
                                            className={cn(
                                                'inline-flex w-full items-center gap-2',
                                                align === 'center' &&
                                                    'justify-center',
                                                align === 'right' &&
                                                    'justify-end',
                                            )}
                                        >
                                            {flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )}
                                        </span>
                                    )}

                                    {header.column.getCanResize() && (
                                        <div
                                            onMouseDown={header.getResizeHandler()}
                                            onTouchStart={header.getResizeHandler()}
                                            className="absolute top-0 right-0 h-full w-1 cursor-col-resize touch-none hover:bg-primary/60"
                                        />
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                );
            })}
        </thead>
    );
}
