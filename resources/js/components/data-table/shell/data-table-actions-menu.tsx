import type { Table } from '@tanstack/react-table';
import {
    Columns3,
    Download,
    RefreshCw,
    RotateCcw,
    Wrench,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

interface DataTableActionsMenuProps<TData> {
    table: Table<TData>;
    showColumnVisibility?: boolean;
    canResetTable?: boolean;
    onResetTable?: () => void;
    showRefresh?: boolean;
    isFetching?: boolean;
    onRefresh?: () => void;
    showExport?: boolean;
    onExport?: () => void;
}

export function DataTableActionsMenu<TData>({
    table,
    showColumnVisibility,
    canResetTable = false,
    onResetTable,
    showRefresh = false,
    isFetching = false,
    onRefresh,
    showExport = false,
    onExport,
}: DataTableActionsMenuProps<TData>) {
    const columns = table
        .getAllLeafColumns()
        .filter((column) => column.getCanHide());
    const hasColumnVisibility = Boolean(
        showColumnVisibility && columns.length > 0,
    );
    const hasRefresh = Boolean(showRefresh && onRefresh);
    const hasExport = Boolean(showExport && onExport);
    const hasReset = Boolean(onResetTable);

    if (!hasColumnVisibility && !hasReset && !hasRefresh && !hasExport) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-label="Manutenção da tabela"
                >
                    <Wrench />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                {(hasRefresh || hasExport) && (
                    <DropdownMenuGroup>
                        {hasRefresh && (
                            <DropdownMenuItem
                                disabled={isFetching}
                                onSelect={() => onRefresh?.()}
                            >
                                <RefreshCw
                                    className={cn(
                                        'size-4',
                                        isFetching && 'animate-spin',
                                    )}
                                />
                                Atualizar
                            </DropdownMenuItem>
                        )}
                        {hasExport && (
                            <DropdownMenuItem onSelect={() => onExport?.()}>
                                <Download className="size-4" />
                                Exportar
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuGroup>
                )}
                {hasColumnVisibility && (
                    <DropdownMenuGroup>
                        {(hasRefresh || hasExport) && (
                            <DropdownMenuSeparator />
                        )}
                        <DropdownMenuLabel className="flex items-center gap-2">
                            <Columns3 className="size-4" />
                            Colunas
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {columns.map((column) => (
                            <DropdownMenuCheckboxItem
                                key={column.id}
                                checked={column.getIsVisible()}
                                onCheckedChange={(visible) =>
                                    column.toggleVisibility(!!visible)
                                }
                                onSelect={(event) => event.preventDefault()}
                            >
                                {column.columnDef.meta?.label ?? column.id}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuGroup>
                )}
                {hasReset && (
                    <>
                        {(hasColumnVisibility || hasRefresh || hasExport) && (
                            <DropdownMenuSeparator />
                        )}
                        <DropdownMenuItem
                            disabled={!canResetTable}
                            onSelect={() => onResetTable?.()}
                        >
                            <RotateCcw className="size-4" />
                            Redefinir tabela
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
