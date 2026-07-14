import type { HeaderGroup } from '@tanstack/react-table';
import { flexRender } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';

import { cn } from '@/lib/utils';

import type { DataTableAppearance, DataTableSize } from '../core/types';

const HEADER_SIZE_STYLES: Record<DataTableSize, string> = {
    sm: 'h-9 px-2.5 text-xs',
    md: 'h-11 px-3 text-xs',
};

interface DataTableHeaderProps<TData> {
    headerGroups: HeaderGroup<TData>[];
    appearance: Required<DataTableAppearance>;
    isScrolled?: boolean;
}

export function DataTableHeader<TData>({
    headerGroups,
    appearance,
    isScrolled = false,
}: DataTableHeaderProps<TData>) {
    const showHorizontalLines =
        appearance.gridLines === 'horizontal' ||
        appearance.gridLines === 'both';
    const showVerticalLines =
        appearance.gridLines === 'vertical' || appearance.gridLines === 'both';

    return (
        <thead className="sticky top-0 z-10 bg-table-background">
            {headerGroups.map((headerGroup) => (
                <tr
                    key={headerGroup.id}
                    className={cn(showHorizontalLines && 'border-b')}
                >
                    {headerGroup.headers.map((header) => {
                        const canSort = header.column.getCanSort();
                        const sorted = header.column.getIsSorted();
                        const align =
                            header.column.columnDef.meta?.align ?? 'left';

                        const isSticky =
                            header.column.columnDef.meta?.sticky === 'right';

                        return (
                            <th
                                key={header.id}
                                className={cn(
                                    'relative overflow-hidden font-medium whitespace-nowrap text-table-text',
                                    HEADER_SIZE_STYLES[appearance.size],
                                    showVerticalLines &&
                                        'border-r last:border-r-0',
                                    align === 'center' && 'text-center',
                                    align === 'right' && 'text-right',
                                    isSticky &&
                                        'sticky right-0 z-20 border-l bg-table-background',
                                )}
                                style={{
                                    width: header.getSize(),
                                    ...(isSticky && {
                                        boxShadow: isScrolled
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
                                            align === 'right' && 'justify-end',
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
                                            align === 'right' && 'justify-end',
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
            ))}
        </thead>
    );
}
