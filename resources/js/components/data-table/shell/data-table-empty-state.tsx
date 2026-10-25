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
        <div
            className={cn(
                'mx-auto flex size-16 items-center justify-center rounded-2xl bg-secondary',
                type === 'error' ? 'text-destructive' : 'text-muted-foreground',
            )}
        >
            <Icon className="size-7" strokeWidth={1.5} />
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
                    <h3 className="text-base font-semibold text-foreground">
                        {title ??
                            (isError
                                ? 'Não foi possível carregar os dados'
                                : 'Nenhum registro encontrado')}
                    </h3>
                    <p className="text-sm leading-6 text-muted-foreground">
                        {description ??
                            (isError
                                ? 'Verifique sua conexão e tente novamente. Se o problema continuar, entre em contato com o suporte.'
                                : 'Sua busca ou filtros não retornaram resultados. Ajuste os critérios e tente novamente.')}
                    </p>
                </div>

                <div className="mt-6 flex flex-wrap items-center justify-center gap-2">
                    {isError && (
                        <Button type="button" size="sm" onClick={onRetry}>
                            Tentar novamente
                        </Button>
                    )}

                    {!isError && hasSearch && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onClearSearch}
                        >
                            Limpar busca
                        </Button>
                    )}

                    {!isError && hasFilters && (
                        <Button
                            type="button"
                            variant={hasSearch ? 'ghost' : 'outline'}
                            size="sm"
                            onClick={onResetFilters}
                        >
                            Limpar filtros
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
