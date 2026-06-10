import { Search, WifiOff } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface DataTableEmptyStateProps {
    type: 'empty' | 'error';
    title?: string;
    description?: string;
    hasSearch?: boolean;
    hasFilters?: boolean;
    onClearSearch?: () => void;
    onResetFilters?: () => void;
    onRetry?: () => void;
}

function EmptyStateGraphic({ type }: { type: 'empty' | 'error' }) {
    const Icon = type === 'error' ? WifiOff : Search;

    return (
        <div className="relative mx-auto flex size-28 items-center justify-center">
            <div className="border-border absolute inset-0 rounded-full border bg-[radial-gradient(circle_at_center,var(--muted)_0,transparent_65%)]" />
            <div className="border-border bg-background absolute inset-4 rounded-full border shadow-sm" />
            <div
                className={cn(
                    'relative flex size-14 items-center justify-center rounded-2xl border shadow-xs',
                    type === 'error'
                        ? 'border-destructive/20 bg-destructive/10 text-destructive'
                        : 'border-border bg-muted text-muted-foreground',
                )}
            >
                <Icon className="size-7" />
            </div>
            <div className="bg-primary/20 absolute top-2 right-5 size-2 rounded-full" />
            <div className="bg-muted-foreground/20 absolute bottom-4 left-4 size-2.5 rounded-full" />
            <div className="bg-border absolute right-2 bottom-8 size-1.5 rounded-full" />
        </div>
    );
}

export function DataTableEmptyState({
    type,
    title,
    description,
    hasSearch,
    hasFilters,
    onClearSearch,
    onResetFilters,
    onRetry,
}: DataTableEmptyStateProps) {
    const isError = type === 'error';

    return (
        <div className="flex items-center justify-center px-6 py-16">
            <div className="mx-auto flex max-w-sm flex-col items-center text-center">
                <EmptyStateGraphic type={type} />

                <div className="mt-5 space-y-2">
                    <h3 className="text-foreground text-base font-semibold">
                        {title ?? (isError ? "You're offline" : 'Nenhum registro encontrado')}
                    </h3>
                    <p className="text-muted-foreground text-sm leading-6">
                        {description ??
                            (isError
                                ? 'Please check your internet connection and try again. If the issue continues, contact support.'
                                : 'Sua busca ou filtros nao retornaram resultados. Ajuste os criterios e tente novamente.')}
                    </p>
                </div>

                <div className="mt-6 flex flex-wrap items-center justify-center gap-2">
                    {isError && (
                        <Button type="button" size="sm" onClick={onRetry}>
                            Tentar novamente
                        </Button>
                    )}

                    {!isError && hasSearch && (
                        <Button type="button" variant="outline" size="sm" onClick={onClearSearch}>
                            Limpar busca
                        </Button>
                    )}

                    {!isError && hasFilters && (
                        <Button type="button" variant={hasSearch ? 'ghost' : 'outline'} size="sm" onClick={onResetFilters}>
                            Limpar filtros
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
