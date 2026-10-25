import type { HeaderGroup } from '@tanstack/react-table';
import { flexRender } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';

import { cn } from '@/lib/utils';

import { clampColumnWidth } from '../core/table-layout';
import type { StickyGeometry } from '../core/table-layout';
import type { DataTableAppearance } from '../core/types';
import { useColumnResize } from '../hooks/use-column-resize';

interface DataTableHeaderProps<TData> {
    headerGroups: HeaderGroup<TData>[];
    appearance: Required<DataTableAppearance>;
    isScrolled?: boolean;
    geometry?: StickyGeometry;
    widths: Record<string, number>;
    onAutosize?: (id: string) => void;
}

export function DataTableHeader<TData>({
    headerGroups,
    appearance,
    isScrolled = false,
    geometry = {},
    widths,
    onAutosize,
}: DataTableHeaderProps<TData>) {
    const startResize = useColumnResize();
    const showHorizontalLines =
        appearance.gridLines === 'horizontal' ||
        appearance.gridLines === 'both';
    const showVerticalLines =
        appearance.gridLines === 'vertical' || appearance.gridLines === 'both';

    return (
        <thead className="sticky top-0 z-10 bg-muted">
            {headerGroups.map((headerGroup) => (
                <tr
                    key={headerGroup.id}
                    className={cn(showHorizontalLines && 'border-b')}
                >
                    {headerGroup.headers.map((header, index, headers) => {
                        const canSort = header.column.getCanSort();
                        const sorted = header.column.getIsSorted();
                        const align =
                            header.column.columnDef.meta?.align ?? 'left';

                        const sticky = geometry[header.column.id];
                        const isSticky = Boolean(sticky);
                        const label =
                            header.column.columnDef.meta?.label ??
                            header.column.id;

                        return (
                            <th
                                key={header.id}
                                draggable={false}
                                data-column-id={header.column.id}
                                className={cn(
                                    'relative overflow-hidden text-xs font-semibold whitespace-nowrap text-muted-foreground',
                                    appearance.size === 'sm'
                                        ? 'h-9 px-3 text-xs'
                                        : 'h-11 px-4 text-xs',
                                    showVerticalLines &&
                                        'border-r last:border-r-0',
                                    align === 'center' && 'text-center',
                                    align === 'right' && 'text-right',
                                    header.column.id === 'actions' &&
                                        'select-none',
                                    isSticky && 'sticky z-20 bg-muted',
                                )}
                                style={{
                                    ...sticky,
                                    ...(isSticky && {
                                        boxShadow: isScrolled
                                            ? `${sticky?.left !== undefined ? 8 : -8}px 0 12px -8px rgba(0,0,0,0.2)`
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

                                {header.column.getCanResize() &&
                                    header.column.id !== 'actions' &&
                                    headerGroup.headers[index + 1]?.column
                                        .id !== 'actions' && (
                                        <div
                                            role="separator"
                                            tabIndex={0}
                                            aria-orientation="vertical"
                                            aria-label={`Largura de ${label}`}
                                            aria-valuenow={Math.round(
                                                widths[header.column.id],
                                            )}
                                            aria-valuemin={
                                                header.column.columnDef
                                                    .minSize ?? 60
                                            }
                                            aria-valuemax={
                                                header.column.columnDef
                                                    .maxSize ?? 1200
                                            }
                                            onPointerDown={(event) =>
                                                startResize(event, {
                                                    size: header.column.getSize(),
                                                    minSize:
                                                        header.column.columnDef
                                                            .minSize,
                                                    maxSize:
                                                        header.column.columnDef
                                                            .maxSize,
                                                    fillColumnId:
                                                        headers.findLast(
                                                            (item) =>
                                                                item.column.getCanResize(),
                                                        )?.column.id,
                                                    onComplete: (sizes) =>
                                                        header
                                                            .getContext()
                                                            .table.setColumnSizing(
                                                                (current) => ({
                                                                    ...current,
                                                                    ...sizes,
                                                                }),
                                                            ),
                                                })
                                            }
                                            onDoubleClick={() =>
                                                onAutosize?.(header.column.id)
                                            }
                                            onKeyDown={(event) => {
                                                if (
                                                    event.key === 'ArrowLeft' ||
                                                    event.key === 'ArrowRight'
                                                ) {
                                                    event.preventDefault();
                                                    const step = event.shiftKey
                                                        ? 50
                                                        : 10;
                                                    const size =
                                                        clampColumnWidth(
                                                            widths[
                                                                header.column.id
                                                            ] +
                                                                (event.key ===
                                                                'ArrowRight'
                                                                    ? step
                                                                    : -step),
                                                            header.column
                                                                .columnDef
                                                                .minSize,
                                                            header.column
                                                                .columnDef
                                                                .maxSize,
                                                        );
                                                    header
                                                        .getContext()
                                                        .table.setColumnSizing(
                                                            (current) => ({
                                                                ...current,
                                                                ...widths,
                                                                [header.column
                                                                    .id]: size,
                                                            }),
                                                        );
                                                }
                                            }}
                                            className="absolute top-0 right-0 z-30 h-full w-2.5 cursor-col-resize touch-none select-none after:absolute after:inset-y-1 after:right-0 after:w-0.5 after:rounded-full after:bg-primary after:opacity-0 after:transition-opacity after:duration-150 after:ease-out hover:after:opacity-100 focus-visible:outline-2 focus-visible:outline-ring focus-visible:after:opacity-100 data-[resizing=true]:after:opacity-100 motion-reduce:after:transition-none"
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
