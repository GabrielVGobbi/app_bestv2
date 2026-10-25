import { useQuery } from '@tanstack/react-query';
import { Loader2, X, Download } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { httpGet } from '@/lib/http';
import type {
    ExportColumnOption,
    ExportFormat,
    StoredExport,
} from '@/types/export';
import { filtersToApiPayload } from '../core/query-params';
import type {
    DataTableFilterConfig,
    DataTableFilterValue,
    DataTableQuickFilter,
} from '../core/types';
import type { CreateExportPayload } from '../hooks/use-export';

interface ExportDialogProps {
    open: boolean;
    onClose: () => void;
    modelClass: string;
    columnsEndpoint: string;
    currentFilters?: Record<string, DataTableFilterValue>;
    currentFilterConfig?: Array<DataTableFilterConfig | DataTableQuickFilter>;
    currentSearch?: string;
    currentSort?: string;
    currentSortDirection?: 'asc' | 'desc';
    activeExports: StoredExport[];
    onStartExport: (payload: CreateExportPayload) => Promise<void>;
    onExportStarted?: () => void;
}

type ColumnsResponse = { data: ExportColumnOption[] };

export function DataTableExportDialog({
    open,
    onClose,
    modelClass,
    columnsEndpoint,
    currentFilters,
    currentFilterConfig = [],
    currentSearch,
    currentSort,
    currentSortDirection,
    activeExports,
    onStartExport,
    onExportStarted,
}: ExportDialogProps) {
    const [selectedColumns, setSelectedColumns] = useState<string[]>([]);
    const [format, setFormat] = useState<ExportFormat>('csv');
    const [exportScope, setExportScope] = useState<'current' | 'all'>(
        'current',
    );
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const filterPayload = useMemo(
        () => filtersToApiPayload(currentFilters ?? {}, currentFilterConfig),
        [currentFilterConfig, currentFilters],
    );
    const activeFilterCount = Object.keys(filterPayload).length;
    const activeExportCount = activeExports.filter(
        (activeExport) =>
            activeExport.modelClass === modelClass &&
            (activeExport.status === 'pending' ||
                activeExport.status === 'processing'),
    ).length;
    const activeStateSummary = useMemo(() => {
        const summary: string[] = [];

        if (currentSearch?.trim()) {
            summary.push(`Busca: ${currentSearch.trim()}`);
        }

        if (activeFilterCount > 0) {
            summary.push(
                `${activeFilterCount} filtro${activeFilterCount > 1 ? 's' : ''}`,
            );
        }

        if (currentSort) {
            summary.push(
                `Ordenação: ${currentSort} ${currentSortDirection ?? 'asc'}`,
            );
        }

        return summary;
    }, [activeFilterCount, currentSearch, currentSort, currentSortDirection]);
    const shouldApplyCurrentState = exportScope === 'current';

    const { data: columnsData, isLoading: isLoadingColumns } = useQuery({
        queryKey: ['export-columns', modelClass],
        queryFn: async ({ signal }) => {
            const result = await httpGet<ColumnsResponse>(
                `${columnsEndpoint}?model_class=${encodeURIComponent(modelClass)}`,
                { signal },
            );

            return result.data;
        },
        enabled: open,
        staleTime: 5 * 60 * 1000,
    });

    const columns = useMemo(() => columnsData ?? [], [columnsData]);

    const handleOpenChange = useCallback(
        (nextOpen: boolean) => {
            if (!nextOpen) {
                onClose();
            }
        },
        [onClose],
    );

    const toggleColumn = useCallback((key: string, checked: boolean) => {
        setSelectedColumns((prev) =>
            checked ? [...prev, key] : prev.filter((k) => k !== key),
        );
    }, []);

    const selectAll = useCallback(() => {
        setSelectedColumns(columns.map((c) => c.key));
    }, [columns]);

    const deselectAll = useCallback(() => {
        setSelectedColumns([]);
    }, []);

    const handleSubmit = useCallback(async () => {
        setIsSubmitting(true);
        setError(null);

        try {
            await onStartExport({
                modelClass,
                format,
                columns: selectedColumns,
                filters: shouldApplyCurrentState ? filterPayload : undefined,
                search: shouldApplyCurrentState ? currentSearch : undefined,
                sort: shouldApplyCurrentState ? currentSort : undefined,
                sort_direction: shouldApplyCurrentState
                    ? currentSortDirection
                    : undefined,
            });

            setSelectedColumns([]);
            setFormat('csv');
            setExportScope('current');
            onExportStarted?.();
            onClose();
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Erro ao iniciar exportação. Tente novamente.',
            );
        } finally {
            setIsSubmitting(false);
        }
    }, [
        modelClass,
        format,
        selectedColumns,
        shouldApplyCurrentState,
        filterPayload,
        currentSearch,
        currentSort,
        currentSortDirection,
        onStartExport,
        onExportStarted,
        onClose,
    ]);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Exportar dados</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Format selection */}
                    <div className="space-y-2">
                        <Label>Formato</Label>
                        <ToggleGroup
                            type="single"
                            value={format}
                            onValueChange={(v) =>
                                v && setFormat(v as ExportFormat)
                            }
                            variant="outline"
                            className="w-full"
                        >
                            <ToggleGroupItem value="csv" className="flex-1">
                                CSV
                            </ToggleGroupItem>
                            <ToggleGroupItem value="xlsx" className="flex-1">
                                Excel
                            </ToggleGroupItem>
                            <ToggleGroupItem value="tsv" className="flex-1">
                                TSV
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    <Separator />

                    <div className="space-y-2">
                        <Label>Escopo</Label>
                        <ToggleGroup
                            type="single"
                            value={exportScope}
                            onValueChange={(value) =>
                                value &&
                                setExportScope(value as 'current' | 'all')
                            }
                            variant="outline"
                            className="grid w-full grid-cols-2"
                        >
                            <ToggleGroupItem value="current">
                                Resultado atual
                            </ToggleGroupItem>
                            <ToggleGroupItem value="all">
                                Todos os registros
                            </ToggleGroupItem>
                        </ToggleGroup>

                        <div className="rounded-md border bg-muted/30 p-3">
                            {shouldApplyCurrentState &&
                            activeStateSummary.length > 0 ? (
                                <div className="flex flex-wrap gap-1.5">
                                    {activeStateSummary.map((item) => (
                                        <Badge
                                            key={item}
                                            variant="secondary"
                                            className="max-w-full truncate"
                                        >
                                            {item}
                                        </Badge>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    {shouldApplyCurrentState
                                        ? 'Sem busca, filtros ou ordenação ativos.'
                                        : 'Ignora busca, filtros e ordenação da tabela.'}
                                </p>
                            )}
                        </div>
                    </div>

                    <Separator />

                    {/* Column selection */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label>Colunas</Label>
                            {columns.length > 0 && (
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={selectAll}
                                        className="text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        Selecionar todas
                                    </button>
                                    <span className="text-xs text-muted-foreground">
                                        ·
                                    </span>
                                    <button
                                        type="button"
                                        onClick={deselectAll}
                                        className="text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        Limpar
                                    </button>
                                </div>
                            )}
                        </div>

                        {isLoadingColumns ? (
                            <div className="flex items-center gap-2 py-4">
                                <Loader2 className="size-4 animate-spin text-muted-foreground" />
                                <span className="text-sm text-muted-foreground">
                                    Carregando colunas...
                                </span>
                            </div>
                        ) : (
                            <div className="max-h-48 space-y-2 overflow-y-auto pr-1">
                                {columns.map((col) => (
                                    <div
                                        key={col.key}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`col-${col.key}`}
                                            checked={selectedColumns.includes(
                                                col.key,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleColumn(
                                                    col.key,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={`col-${col.key}`}
                                            className="cursor-pointer font-normal"
                                        >
                                            {col.label}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        )}

                        {selectedColumns.length > 0 && (
                            <p className="text-xs text-muted-foreground">
                                {selectedColumns.length} de {columns.length}{' '}
                                colunas selecionadas
                            </p>
                        )}

                        {selectedColumns.length === 0 && !isLoadingColumns && (
                            <p className="text-xs text-muted-foreground">
                                Nenhuma selecionada: todas as colunas serão
                                exportadas.
                            </p>
                        )}
                    </div>

                    {activeExportCount > 0 && (
                        <Alert>
                            <AlertDescription>
                                Existe {activeExportCount} exportação em
                                andamento para esta tabela.
                            </AlertDescription>
                        </Alert>
                    )}

                    {error && (
                        <Alert variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={isSubmitting}
                    >
                        <X className="size-4" aria-hidden="true" />
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={isSubmitting || isLoadingColumns}
                    >
                        {isSubmitting ? (
                            <Loader2 className="mr-2 size-4 animate-spin" />
                        ) : (
                            <Download className="size-4" aria-hidden="true" />
                        )}
                        Exportar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
