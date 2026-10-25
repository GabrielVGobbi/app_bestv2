import { useQuery } from '@tanstack/react-query';
import { Loader2, X, Database } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';

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
import type { DatabaseExportType } from '@/types/database-export';

import { apiRoutes } from '../../lib/api/api-routes';
import type { CreateDatabaseExportPayload } from './use-database-export';

interface DatabaseExportDialogProps {
    open: boolean;
    onClose: () => void;
    onStartExport: (payload: CreateDatabaseExportPayload) => Promise<void>;
    onExportStarted?: () => void;
}

type TablesResponse = { data: { name: string }[] };

export function DatabaseExportDialog({
    open,
    onClose,
    onStartExport,
    onExportStarted,
}: DatabaseExportDialogProps) {
    const [exportType, setExportType] = useState<DatabaseExportType>('full');
    const [selectedTables, setSelectedTables] = useState<string[]>([]);
    const [scopeTables, setScopeTables] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const { data: tablesData, isLoading: isLoadingTables } = useQuery({
        queryKey: ['database-export-tables'],
        queryFn: async ({ signal }) => {
            const result = await httpGet<TablesResponse>(
                apiRoutes.databaseExports.tables,
                { signal },
            );

            return result.data;
        },
        enabled: open,
        staleTime: 60 * 1000,
    });

    const tables = useMemo(() => tablesData ?? [], [tablesData]);

    const handleOpenChange = useCallback(
        (nextOpen: boolean) => {
            if (!nextOpen) {
                onClose();
            }
        },
        [onClose],
    );

    const toggleTable = useCallback((name: string, checked: boolean) => {
        setSelectedTables((prev) =>
            checked ? [...prev, name] : prev.filter((t) => t !== name),
        );
    }, []);

    const selectAll = useCallback(() => {
        setSelectedTables(tables.map((t) => t.name));
    }, [tables]);

    const deselectAll = useCallback(() => {
        setSelectedTables([]);
    }, []);

    const handleSubmit = useCallback(async () => {
        setIsSubmitting(true);
        setError(null);

        try {
            await onStartExport({
                export_type: exportType,
                tables:
                    scopeTables && selectedTables.length > 0
                        ? selectedTables
                        : null,
            });

            setSelectedTables([]);
            setExportType('full');
            setScopeTables(false);
            onExportStarted?.();
            onClose();
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Erro ao iniciar o dump. Tente novamente.',
            );
        } finally {
            setIsSubmitting(false);
        }
    }, [
        exportType,
        scopeTables,
        selectedTables,
        onStartExport,
        onExportStarted,
        onClose,
    ]);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Gerar dump do banco</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Export type */}
                    <div className="space-y-2">
                        <Label>Conteúdo</Label>
                        <ToggleGroup
                            type="single"
                            value={exportType}
                            onValueChange={(v) =>
                                v && setExportType(v as DatabaseExportType)
                            }
                            variant="outline"
                            className="w-full"
                        >
                            <ToggleGroupItem value="schema" className="flex-1">
                                Schema
                            </ToggleGroupItem>
                            <ToggleGroupItem value="data" className="flex-1">
                                Dados
                            </ToggleGroupItem>
                            <ToggleGroupItem value="full" className="flex-1">
                                Completo
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <p className="text-xs text-muted-foreground">
                            {exportType === 'schema' &&
                                'Exporta apenas a estrutura das tabelas (CREATE TABLE).'}
                            {exportType === 'data' &&
                                'Exporta apenas os dados (INSERT INTO), sem estrutura.'}
                            {exportType === 'full' &&
                                'Exporta estrutura + dados completos do banco.'}
                        </p>
                    </div>

                    <Separator />

                    {/* Table scope */}
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="scope-tables"
                                checked={scopeTables}
                                onCheckedChange={(v) => {
                                    setScopeTables(v === true);

                                    if (!v) {
                                        setSelectedTables([]);
                                    }
                                }}
                            />
                            <Label
                                htmlFor="scope-tables"
                                className="cursor-pointer font-normal"
                            >
                                Exportar apenas tabelas específicas
                            </Label>
                        </div>

                        {scopeTables && (
                            <div className="space-y-2 pl-6">
                                <div className="flex items-center justify-between">
                                    <Label className="text-sm">Tabelas</Label>
                                    {tables.length > 0 && (
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

                                {isLoadingTables ? (
                                    <div className="flex items-center gap-2 py-2">
                                        <Loader2 className="size-4 animate-spin text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            Carregando tabelas...
                                        </span>
                                    </div>
                                ) : (
                                    <div className="max-h-48 space-y-1.5 overflow-y-auto pr-1">
                                        {tables.map((table) => (
                                            <div
                                                key={table.name}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={`table-${table.name}`}
                                                    checked={selectedTables.includes(
                                                        table.name,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleTable(
                                                            table.name,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`table-${table.name}`}
                                                    className="cursor-pointer font-mono text-xs font-normal"
                                                >
                                                    {table.name}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {selectedTables.length > 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        {selectedTables.length} de{' '}
                                        {tables.length} tabelas selecionadas
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

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
                        disabled={
                            isSubmitting || (scopeTables && isLoadingTables)
                        }
                    >
                        {isSubmitting ? (
                            <Loader2 className="mr-2 size-4 animate-spin" />
                        ) : (
                            <Database className="size-4" aria-hidden="true" />
                        )}
                        Gerar dump
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
