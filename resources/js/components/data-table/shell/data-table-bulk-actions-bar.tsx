import { X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import type { DataTableBulkAction, DataTableBulkAggregate, DataTableBulkActionsConfig } from '../core/types';

interface DataTableBulkActionsBarProps<TData> {
    selectedRows: TData[];
    selectedCount: number;
    actions: DataTableBulkAction<TData>[];
    aggregates?: DataTableBulkAggregate<TData>[];
    config?: DataTableBulkActionsConfig;
    onClear: () => void;
}

const variantClasses: Record<string, string> = {
    destructive: 'text-red-400 hover:text-red-300 hover:bg-red-500/10',
    default: 'text-white hover:text-white hover:bg-slate-700',
    outline: 'text-slate-300 hover:text-white hover:bg-slate-700',
    secondary: 'text-slate-300 hover:text-white hover:bg-slate-700',
    ghost: 'text-slate-300 hover:text-white hover:bg-slate-700',
    link: 'text-indigo-400 hover:text-indigo-300',
};

export function DataTableBulkActionsBar<TData>({
    selectedRows,
    selectedCount,
    actions,
    aggregates = [],
    config,
    onClear,
}: DataTableBulkActionsBarProps<TData>) {
    const selectedLabel = config?.selectedLabel ?? (selectedCount === 1 ? 'selecionado' : 'selecionados');

    // Unmount when nothing is selected so focusable buttons are not in the DOM
    // while hidden — avoids the "aria-hidden on element with focused descendant" warning.
    if (selectedCount === 0) {
        return null;
    }

    return (
        <div
            className="pointer-events-none fixed bottom-6 left-1/2 z-50 -translate-x-1/2 translate-y-0 opacity-100 transition-all duration-200"
        >
            <div
                className="pointer-events-auto flex items-center gap-3 rounded-2xl bg-slate-900 px-5 py-3 shadow-2xl shadow-slate-900/30"
            >
                <div className="flex items-center gap-2 border-r border-slate-700 pr-4">
                    <span className="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-500 text-sm font-bold text-white">
                        {selectedCount}
                    </span>
                    <span className="text-sm font-medium text-slate-300">{selectedLabel}</span>
                </div>

                {aggregates.length > 0 && (
                    <div className="flex items-center gap-3 border-r border-slate-700 pr-4">
                        {aggregates.map((agg) => {
                            const raw = agg.fn(selectedRows);
                            const value = agg.format ? agg.format(raw) : raw;

                            return (
                                <span key={agg.id} className="text-sm text-slate-300">
                                    <span className="mr-1 font-medium text-slate-400">{agg.label}:</span>
                                    {value}
                                </span>
                            );
                        })}
                    </div>
                )}

                <div className="flex items-center gap-1.5">
                    {actions.map((action) => {
                        const disabled = action.disabled?.(selectedRows) ?? false;

                        return (
                            <Button
                                key={action.id}
                                type="button"
                                size="sm"
                                variant="ghost"
                                disabled={disabled}
                                className={cn('h-9 rounded-xl px-3', variantClasses[action.variant ?? 'ghost'])}
                                onClick={() => action.onClick(selectedRows, onClear)}
                            >
                                {action.icon && <span className="mr-1.5 flex">{action.icon}</span>}
                                {action.label}
                            </Button>
                        );
                    })}
                </div>

                <div className="border-l border-slate-700 pl-3">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-8 w-8 rounded-md p-0 text-slate-500 hover:bg-slate-700 hover:text-slate-300"
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
