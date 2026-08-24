import type { VisibilityState } from '@tanstack/react-table';

export function isColumnVisible(
    columnId: string,
    columnVisibility: VisibilityState,
): boolean {
    return columnVisibility[columnId] !== false;
}
