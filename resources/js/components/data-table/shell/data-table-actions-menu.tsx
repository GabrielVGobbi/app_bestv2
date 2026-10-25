import type { Table } from '@tanstack/react-table';
import { Columns3, MoreHorizontal } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownContent,
    DropdownLabel,
    DropdownMenu,
    DropdownSeparator,
    DropdownTrigger,
} from '@/components/ui/fluid/dropdown';
import { MenuItem } from '@/components/ui/fluid/menu-item';

interface DataTableActionsMenuProps<TData> {
    table: Table<TData>;
    showColumnVisibility?: boolean;
}

export function DataTableActionsMenu<TData>({
    table,
    showColumnVisibility,
}: DataTableActionsMenuProps<TData>) {
    const columns = table
        .getAllLeafColumns()
        .filter((column) => column.getCanHide());
    const hasColumnVisibility = Boolean(
        showColumnVisibility && columns.length > 0,
    );

    if (!hasColumnVisibility) {
        return null;
    }

    // Seleção múltipla do Fluid: linhas marcadas viram checkbox e o menu fica aberto ao alternar.
    const visibleIndices = columns.flatMap((column, index) =>
        column.getIsVisible() ? [index] : [],
    );

    return (
        <DropdownMenu>
            <DropdownTrigger
                render={
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        aria-label="Colunas visíveis"
                    >
                        <MoreHorizontal />
                    </Button>
                }
            />
            <DropdownContent
                align="end"
                className="w-56"
                checkedIndices={visibleIndices}
            >
                <DropdownLabel className="flex items-center gap-2">
                    <Columns3 className="size-4" />
                    Colunas
                </DropdownLabel>
                <DropdownSeparator />
                {columns.map((column, index) => (
                    <MenuItem
                        key={column.id}
                        index={index}
                        label={String(
                            column.columnDef.meta?.label ?? column.id,
                        )}
                        checked={column.getIsVisible()}
                        onSelect={() => column.toggleVisibility()}
                    />
                ))}
            </DropdownContent>
        </DropdownMenu>
    );
}
