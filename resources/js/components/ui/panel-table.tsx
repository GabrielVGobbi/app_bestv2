import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface PanelTableProps {
    className?: string;
    children: ReactNode;
}

function PanelTable({ className, children }: PanelTableProps) {
    return (
        <div className={cn('overflow-hidden rounded-sm border border-border bg-card', className)}>
            {children}
        </div>
    );
}

interface PanelTableHeadColumn {
    label: string;
    align?: 'left' | 'right';
    className?: string;
    srOnly?: boolean;
}

interface PanelTableHeadProps {
    columns: PanelTableHeadColumn[];
    className?: string;
}

function PanelTableHead({ columns, className }: PanelTableHeadProps) {
    return (
        <div
            className={cn(
                'hidden gap-3 border-b border-sidebar-border bg-sidebar px-4 py-2.5 text-xs font-semibold text-sidebar-foreground lg:grid',
                className,
            )}
        >
            {columns.map((column) => (
                <span
                    key={column.label}
                    className={cn(
                        column.align === 'right' && 'text-right',
                        column.srOnly && 'sr-only',
                        column.className,
                    )}
                >
                    {column.label}
                </span>
            ))}
        </div>
    );
}

interface PanelTableBodyProps {
    as?: 'div' | 'ul';
    className?: string;
    children: ReactNode;
}

function PanelTableBody({
    as: Comp = 'div',
    className,
    children,
}: PanelTableBodyProps) {
    return <Comp className={cn('divide-y divide-separator', className)}>{children}</Comp>;
}

interface PanelTableRowProps {
    as?: 'div' | 'li';
    className?: string;
    children: ReactNode;
}

function PanelTableRow({
    as: Comp = 'div',
    className,
    children,
}: PanelTableRowProps) {
    return (
        <Comp
            className={cn(
                'grid gap-3 px-4 py-3 lg:items-center',
                className,
            )}
        >
            {children}
        </Comp>
    );
}

export { PanelTable, PanelTableBody, PanelTableHead, PanelTableRow };
