import type { PaginationMeta } from '@/types/pagination';

interface DataTableResultSummaryProps {
    meta?: PaginationMeta;
    loadedCount: number;
    mode: 'paginated' | 'infinite-scroll';
}

export function DataTableResultSummary({
    meta,
    loadedCount,
    mode,
}: DataTableResultSummaryProps) {
    if (!meta) {
        return null;
    }

    if (mode === 'infinite-scroll') {
        return (
            <p className="text-sm text-muted-foreground">
                {loadedCount} carregados de {meta.total} registros
            </p>
        );
    }

    return (
        <p className="text-sm text-muted-foreground">
            Mostrando {meta.from ?? 0} a {meta.to ?? 0} de {meta.total}{' '}
            registros
        </p>
    );
}
