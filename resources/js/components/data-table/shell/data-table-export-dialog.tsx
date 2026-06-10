import { useQuery } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';
import { useCallback, useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
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
import type { DataTableFilterValue } from '../core/types';
import type { ExportColumnOption, ExportFormat, StoredExport } from '@/types/export';
import type { CreateExportPayload } from '../hooks/use-export';

interface ExportDialogProps {
    open: boolean;
    onClose: () => void;
    modelClass: string;
    columnsEndpoint: string;
    currentFilters?: Record<string, DataTableFilterValue>;
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
    currentSearch,
    currentSort,
    currentSortDirection,
    onStartExport,
    onExportStarted,
}: ExportDialogProps) {
    const [selectedColumns, setSelectedColumns] = useState<string[]>([]);
    const [format, setFormat] = useState<ExportFormat>('csv');
    const [useCurrentFilters, setUseCurrentFilters] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const hasActiveFilters = Object.values(currentFilters ?? {}).some(
        (v) => v !== null && v !== undefined && v !== '',
    );

    const { data: columnsData, isLoading: isLoadingColumns } = useQuery({
        queryKey: ['export-columns', modelClass],
        queryFn: async () => {
            const result = await httpGet<ColumnsResponse>(
                `${columnsEndpoint}?model_class=${encodeURIComponent(modelClass)}`,
            );

            return result.data;
        },
        enabled: open,
        staleTime: 5 * 60 * 1000,
    });

    const columns = columnsData ?? [];

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
                filters: useCurrentFilters ? (currentFilters as Record<string, unknown>) : undefined,
                search: useCurrentFilters ? currentSearch : undefined,
                sort: currentSort,
                sort_direction: currentSortDirection,
            });

            setSelectedColumns([]);
            setFormat('csv');
            setUseCurrentFilters(false);
            onExportStarted?.();
            onClose();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Erro ao iniciar exportação. Tente novamente.');
        } finally {
            setIsSubmitting(false);
        }
    }, [
        modelClass,
        format,
        selectedColumns,
        useCurrentFilters,
        currentFilters,
        currentSearch,
        currentSort,
        currentSortDirection,
        onStartExport,
        onExportStarted,
        onClose,
    ]);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-w-md">
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
                            onValueChange={(v) => v && setFormat(v as ExportFormat)}
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

                    {/* Column selection */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label>Colunas</Label>
                            {columns.length > 0 && (
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={selectAll}
                                        className="text-muted-foreground hover:text-foreground text-xs"
                                    >
                                        Selecionar todas
                                    </button>
                                    <span className="text-muted-foreground text-xs">·</span>
                                    <button
                                        type="button"
                                        onClick={deselectAll}
                                        className="text-muted-foreground hover:text-foreground text-xs"
                                    >
                                        Limpar
                                    </button>
                                </div>
                            )}
                        </div>

                        {isLoadingColumns ? (
                            <div className="flex items-center gap-2 py-4">
                                <Loader2 className="text-muted-foreground size-4 animate-spin" />
                                <span className="text-muted-foreground text-sm">Carregando colunas...</span>
                            </div>
                        ) : (
                            <div className="max-h-48 space-y-2 overflow-y-auto pr-1">
                                {columns.map((col) => (
                                    <div key={col.key} className="flex items-center gap-2">
                                        <Checkbox
                                            id={`col-${col.key}`}
                                            checked={selectedColumns.includes(col.key)}
                                            onCheckedChange={(checked) =>
                                                toggleColumn(col.key, checked === true)
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
                            <p className="text-muted-foreground text-xs">
                                {selectedColumns.length} de {columns.length} colunas selecionadas
                            </p>
                        )}

                        {selectedColumns.length === 0 && !isLoadingColumns && (
                            <p className="text-muted-foreground text-xs">
                                Nenhuma selecionada — todas as colunas serão exportadas.
                            </p>
                        )}
                    </div>

                    {/* Use current filters */}
                    {hasActiveFilters && (
                        <>
                            <Separator />
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="use-filters"
                                    checked={useCurrentFilters}
                                    onCheckedChange={(v) => setUseCurrentFilters(v === true)}
                                />
                                <Label htmlFor="use-filters" className="cursor-pointer font-normal">
                                    Usar filtros ativos da tabela
                                </Label>
                            </div>
                        </>
                    )}

                    {error && (
                        <Alert variant="destructive">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button type="button" onClick={handleSubmit} disabled={isSubmitting || isLoadingColumns}>
                        {isSubmitting && <Loader2 className="mr-2 size-4 animate-spin" />}
                        Exportar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
