import type { Row, RowSelectionState } from '@tanstack/react-table';
import { flexRender } from '@tanstack/react-table';

import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

import type { DataTableAppearance } from '../core/types';
import { DataTableEmptyState } from './data-table-empty-state';
import { DataTableTruncatedCell } from './data-table-truncated-cell';

interface DataTableBodyProps<TData> {
    rows: Row<TData>[];
    rowSelection?: RowSelectionState;
    visibleColumnCount: number;
    isLoading: boolean;
    appearance: Required<DataTableAppearance>;
    error?: Error | null;
    emptyMessage: string;
    hasSearch?: boolean;
    hasFilters?: boolean;
    isScrolled?: boolean;
    onClearSearch?: () => void;
    onResetFilters?: () => void;
    onRetry?: () => void;
}

export function DataTableBody<TData>({
    rows,
    rowSelection = {},
    visibleColumnCount,
    isLoading,
    appearance,
    error,
    emptyMessage,
    hasSearch,
    hasFilters,
    isScrolled = false,
    onClearSearch,
    onResetFilters,
    onRetry,
}: DataTableBodyProps<TData>) {
    const showHorizontalLines =
        appearance.gridLines === 'horizontal' ||
        appearance.gridLines === 'both';
    const showVerticalLines =
        appearance.gridLines === 'vertical' || appearance.gridLines === 'both';
    const cellPadding = appearance.size === 'sm' ? 'px-3' : 'px-4';
    const cellHeight = appearance.size === 'sm' ? 'h-12' : 'h-15';
    const bodyTextSize = appearance.size === 'sm' ? 'text-xs' : 'text-sm';

    if (isLoading) {
        return (
            <tbody>
                {Array.from({ length: 6 }).map((_, rowIndex) => (
                    <tr
                        key={rowIndex}
                        className={cn(showHorizontalLines && 'border-b')}
                    >
                        {Array.from({ length: visibleColumnCount }).map(
                            (__, cellIndex) => (
                                <td
                                    key={cellIndex}
                                    className={cn(
                                        cellPadding,
                                        appearance.size === 'sm'
                                            ? 'py-2'
                                            : 'py-3',
                                        showVerticalLines &&
                                            'border-r last:border-r-0',
                                    )}
                                >
                                    <Skeleton className="h-4 w-full" />
                                </td>
                            ),
                        )}
                    </tr>
                ))}
            </tbody>
        );
    }

    if (error) {
        return (
            <tbody>
                <tr>
                    <td colSpan={visibleColumnCount}>
                        <DataTableEmptyState type="error" onRetry={onRetry} />
                    </td>
                </tr>
            </tbody>
        );
    }

    if (rows.length === 0) {
        return (
            <tbody>
                <tr>
                    <td colSpan={visibleColumnCount}>
                        <DataTableEmptyState
                            type="empty"
                            title={emptyMessage}
                            hasSearch={hasSearch}
                            hasFilters={hasFilters}
                            onClearSearch={onClearSearch}
                            onResetFilters={onResetFilters}
                        />
                    </td>
                </tr>
            </tbody>
        );
    }

    return (
        <tbody>
            {rows.map((row) => {
                const isSelected = !!rowSelection[row.id];

                return (
                    <tr
                        key={row.id}
                        data-state={isSelected ? 'selected' : undefined}
                        className={cn(
                            'transition-colors hover:bg-accent/45',
                            showHorizontalLines && 'border-b',
                            appearance.rowStyle === 'striped' &&
                                'even:bg-muted/40',
                            appearance.rowStyle === 'soft-striped' &&
                                'even:bg-muted/20',
                            isSelected && 'bg-muted/60',
                        )}
                    >
                        {row.getVisibleCells().map((cell) => {
                            const meta = cell.column.columnDef.meta;
                            const align = meta?.align ?? 'left';
                            const isSticky = meta?.sticky === 'right';
                            const isTruncate = meta?.truncate;

                            const cellContent =
                                cell.column.id === 'select' ? (
                                    <Checkbox
                                        checked={isSelected}
                                        onCheckedChange={(value) =>
                                            row.toggleSelected(Boolean(value))
                                        }
                                        aria-label="Selecionar linha"
                                    />
                                ) : (
                                    flexRender(
                                        cell.column.columnDef.cell,
                                        cell.getContext(),
                                    )
                                );

                            return (
                                <td
                                    key={cell.id}
                                    className={cn(
                                        'overflow-hidden whitespace-nowrap',
                                        cellHeight,
                                        cellPadding,
                                        bodyTextSize,
                                        showVerticalLines &&
                                            'border-r last:border-r-0',
                                        align === 'center' && 'text-center',
                                        align === 'right' &&
                                            'text-right font-tabular',
                                        isSticky &&
                                            'sticky right-0 z-[1] border-l bg-card',
                                    )}
                                    style={{
                                        width: cell.column.getSize(),
                                        ...(isSticky && {
                                            boxShadow: isScrolled
                                                ? '-12px 0 16px -4px rgba(0,0,0,0.1)'
                                                : 'none',
                                        }),
                                    }}
                                >
                                    {isTruncate ? (
                                        <DataTableTruncatedCell>
                                            {cellContent}
                                        </DataTableTruncatedCell>
                                    ) : (
                                        cellContent
                                    )}
                                </td>
                            );
                        })}
                    </tr>
                );
            })}
        </tbody>
    );
}
