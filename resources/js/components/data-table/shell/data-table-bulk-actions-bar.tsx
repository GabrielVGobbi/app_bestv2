import { X, RefreshCw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import type {
    DataTableBulkAction,
    DataTableBulkAggregate,
    DataTableBulkActionsConfig,
    DataTableSelectionStatus,
} from '../core/types';

interface DataTableBulkActionsBarProps<TData> {
    selectedRows: TData[];
    selectedCount: number;
    actions: DataTableBulkAction<TData>[];
    aggregates?: DataTableBulkAggregate<TData>[];
    config?: DataTableBulkActionsConfig;
    onClear: () => void;
    status?: DataTableSelectionStatus<TData>;
}

const variantClasses: Record<string, string> = {
    destructive:
        'text-destructive hover:bg-destructive-soft hover:text-destructive-soft-foreground',
    default: 'text-foreground hover:bg-accent hover:text-foreground',
    outline: 'text-muted-foreground hover:bg-accent hover:text-foreground',
    secondary: 'text-muted-foreground hover:bg-accent hover:text-foreground',
    ghost: 'text-muted-foreground hover:bg-accent hover:text-foreground',
    link: 'text-primary hover:underline',
};

export function DataTableBulkActionsBar<TData>({
    selectedRows,
    selectedCount,
    actions,
    aggregates = [],
    config,
    onClear,
    status,
}: DataTableBulkActionsBarProps<TData>) {
    const selectedLabel =
        config?.selectedLabel ??
        (selectedCount === 1 ? 'selecionado' : 'selecionados');
    const complete = status?.complete ?? selectedRows.length === selectedCount;
    const pending = status?.pending ?? false;
    const exceedsLimit =
        config?.maxActionCount !== undefined &&
        selectedCount > config.maxActionCount;
    const limitMessage = exceedsLimit
        ? `Selecione até ${config?.maxActionCount} registros para executar esta ação.`
        : undefined;

    // Unmount when nothing is selected so focusable buttons are not in the DOM
    // while hidden: avoids the "aria-hidden on element with focused descendant" warning.
    if (selectedCount === 0) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed bottom-6 left-1/2 z-50 -translate-x-1/2 translate-y-0 opacity-100 transition-all duration-200">
            <div className="pointer-events-auto flex items-center gap-3 rounded-2xl border border-border bg-popover px-5 py-3 text-popover-foreground shadow-md">
                <div className="flex items-center gap-2 border-r border-border pr-4">
                    <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground tabular-nums">
                        {selectedCount}
                    </span>
                    <span className="text-sm font-medium text-muted-foreground">
                        {selectedLabel}
                    </span>
                </div>

                {complete &&
                    !pending &&
                    !status?.error &&
                    aggregates.length > 0 && (
                        <div className="flex items-center gap-3 border-r border-border pr-4">
                            {aggregates.map((agg) => {
                                const raw = agg.fn(selectedRows);
                                const value = agg.format
                                    ? agg.format(raw)
                                    : raw;

                                return (
                                    <span
                                        key={agg.id}
                                        className="text-sm text-foreground"
                                    >
                                        <span className="mr-1 font-medium text-muted-foreground">
                                            {agg.label}:
                                        </span>
                                        {value}
                                    </span>
                                );
                            })}
                        </div>
                    )}

                <div
                    className="max-w-sm text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {pending && <span>Revisando seleção...</span>}
                    {status?.reviewed && complete && !pending && (
                        <span>Seleção revisada.</span>
                    )}
                    {!pending && limitMessage && <p>{limitMessage}</p>}
                    {!pending && !complete && !status?.error && (
                        <p>
                            Seleção incompleta. Revise os registros antes de
                            continuar.
                        </p>
                    )}
                    {!pending && Boolean(status?.unavailableIds.length) && (
                        <p>
                            Registros indisponíveis, removidos ou sem acesso:{' '}
                            {status?.unavailableIds.join(', ')}
                        </p>
                    )}
                    {status?.error && (
                        <p role="alert">
                            Erro ao revisar seleção: {status.error}
                        </p>
                    )}
                    {status?.canResolve &&
                        !pending &&
                        (exceedsLimit ||
                            Boolean(status.error) ||
                            Boolean(status.unavailableIds.length)) && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={status.onResolve}
                            >
                                <RefreshCw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {status.error
                                    ? 'Tentar novamente'
                                    : 'Revisar seleção'}
                            </Button>
                        )}
                </div>

                <div className="flex items-center gap-1.5">
                    {actions.map((action) => {
                        const disabled =
                            pending ||
                            exceedsLimit ||
                            (!complete && !status?.canResolve) ||
                            (complete &&
                                !status?.canResolve &&
                                (action.disabled?.(selectedRows) ?? false));

                        return (
                            <Button
                                key={action.id}
                                type="button"
                                size="sm"
                                variant="ghost"
                                disabled={disabled}
                                title={limitMessage}
                                className={cn(
                                    'h-9 rounded-xl px-3',
                                    variantClasses[action.variant ?? 'ghost'],
                                )}
                                onClick={() =>
                                    status
                                        ? status.onAction(action)
                                        : complete &&
                                          action.onClick(selectedRows, onClear)
                                }
                            >
                                {action.icon && (
                                    <span className="mr-1.5 flex">
                                        {action.icon}
                                    </span>
                                )}
                                {action.label}
                            </Button>
                        );
                    })}
                </div>

                <div className="border-l border-border pl-3">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-8 w-8 rounded-lg p-0 text-muted-foreground hover:bg-accent hover:text-foreground"
                        onClick={onClear}
                        aria-label="Limpar seleção"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
