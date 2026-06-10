import type { Table } from '@tanstack/react-table';
import { Columns3, MoreHorizontal } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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
    const hasColumnVisibility = Boolean(showColumnVisibility && columns.length > 0);

    if (!hasColumnVisibility) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="flex items-center gap-2">
                        <Columns3 className="size-4" />
                        Colunas
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {columns.map((column) => (
                        <DropdownMenuCheckboxItem
                            key={column.id}
                            checked={column.getIsVisible()}
                            onCheckedChange={() => column.toggleVisibility()}
                            onSelect={(event) => event.preventDefault()}
                        >
                            {column.columnDef.meta?.label ?? column.id}
                        </DropdownMenuCheckboxItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
