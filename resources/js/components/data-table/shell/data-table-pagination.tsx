import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PaginationMeta } from '@/types/pagination';

interface DataTablePaginationProps {
    meta?: PaginationMeta;
    page: number;
    perPage: number;
    showSummary?: boolean;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
}

export function DataTablePagination({
    meta,
    page,
    perPage,
    showSummary = true,
    onPageChange,
    onPerPageChange,
}: DataTablePaginationProps) {
    if (!meta) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 py-1 md:flex-row md:items-center md:justify-between">
            {showSummary ? (
                <p className="text-sm text-muted-foreground">
                    Mostrando {meta.from ?? 0} a {meta.to ?? 0} de {meta.total}{' '}
                    registros
                </p>
            ) : (
                <span />
            )}

            <div className="flex items-center gap-3">
                <Select
                    value={String(perPage)}
                    onValueChange={(value) => onPerPageChange(Number(value))}
                >
                    <SelectTrigger size="sm" className="w-24">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {[10, 15, 30, 50, 100].map((value) => (
                            <SelectItem key={value} value={String(value)}>
                                {value}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="text-xs font-medium text-muted-foreground">
                    Página {meta.current_page} de {meta.last_page}
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="size-8 p-0"
                        disabled={page <= 1}
                        onClick={() => onPageChange(page - 1)}
                    >
                        <ChevronLeft />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="size-8 p-0"
                        disabled={page >= meta.last_page}
                        onClick={() => onPageChange(page + 1)}
                    >
                        <ChevronRight />
                    </Button>
                </div>
            </div>
        </div>
    );
}
