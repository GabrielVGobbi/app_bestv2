import { Check, Database, Download, Loader2, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { StoredDatabaseExport } from '@/types/database-export';

interface DatabaseExportStatusProps {
    activeExports: StoredDatabaseExport[];
    onDownload: (uuid: string) => void;
}

function ExportTypeLabel({
    type,
}: {
    type: StoredDatabaseExport['export_type'];
}) {
    const labels: Record<string, string> = {
        schema: 'Schema',
        data: 'Dados',
        full: 'Completo',
    };

    return (
        <Badge variant="secondary" className="text-[10px]">
            {labels[type] ?? type}
        </Badge>
    );
}

function StatusIcon({ status }: { status: StoredDatabaseExport['status'] }) {
    if (status === 'completed') {
        return <Check className="size-3.5 shrink-0 text-green-500" />;
    }

    if (status === 'failed') {
        return <X className="size-3.5 shrink-0 text-red-500" />;
    }

    return <Loader2 className="size-3.5 shrink-0 animate-spin text-blue-500" />;
}

export function DatabaseExportStatus({
    activeExports,
    onDownload,
}: DatabaseExportStatusProps) {
    if (activeExports.length === 0) {
        return null;
    }

    const inProgressCount = activeExports.filter(
        (e) => e.status === 'pending' || e.status === 'processing',
    ).length;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="relative gap-1.5"
                >
                    {inProgressCount > 0 ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <Database className="size-4" />
                    )}
                    <span>
                        {inProgressCount > 0
                            ? `${inProgressCount} dump${inProgressCount > 1 ? 's' : ''} em andamento`
                            : 'Dumps'}
                    </span>
                    {inProgressCount > 0 && (
                        <span className="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] text-white">
                            {inProgressCount}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0">
                <div className="border-b px-3 py-2">
                    <p className="text-sm font-medium">Dumps recentes</p>
                </div>
                <div className="divide-y">
                    {activeExports.map((exp) => (
                        <div key={exp.uuid} className="space-y-1.5 px-3 py-2.5">
                            <div className="flex items-start gap-2">
                                <StatusIcon status={exp.status} />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {exp.file_name ?? 'Gerando dump...'}
                                    </p>
                                    <div className="flex items-center gap-1.5">
                                        <ExportTypeLabel
                                            type={exp.export_type}
                                        />
                                        {exp.status === 'pending' && (
                                            <span className="text-[10px] text-muted-foreground">
                                                Na fila...
                                            </span>
                                        )}
                                        {exp.status === 'processing' && (
                                            <span className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                                <span className="inline-block size-1.5 animate-pulse rounded-full bg-blue-400" />
                                                Processando
                                            </span>
                                        )}
                                        {exp.status === 'failed' && (
                                            <span className="text-[10px] text-red-500">
                                                Falhou
                                            </span>
                                        )}
                                        {exp.status === 'completed' && (
                                            <span className="text-[10px] text-green-600">
                                                Concluído
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {(exp.status === 'pending' ||
                                exp.status === 'processing') && (
                                <div className="h-1 w-full animate-pulse rounded-full bg-muted" />
                            )}

                            {exp.status === 'completed' && exp.download_url && (
                                <a
                                    href={exp.download_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                                    onClick={() => onDownload(exp.uuid)}
                                >
                                    <Download className="size-3" />
                                    Baixar arquivo SQL
                                </a>
                            )}
                        </div>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}
