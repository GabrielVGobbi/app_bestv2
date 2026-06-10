import { Check, Download, ExternalLink, Loader2, X } from 'lucide-react';
import { Link } from '@inertiajs/react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { StoredExport } from '@/types/export';

interface DataTableExportStatusProps {
    activeExports: StoredExport[];
    onDownload: (uuid: string) => void;
}

function ExportProgressBar({ processed, total }: { processed: number; total: number | null }) {
    if (!total) {
        return <div className="bg-muted h-1 w-full animate-pulse rounded-full" />;
    }

    const pct = Math.min(100, Math.round((processed / total) * 100));

    return (
        <div className="bg-muted h-1 w-full overflow-hidden rounded-full">
            <div
                className="bg-primary h-full transition-all duration-500"
                style={{ width: `${pct}%` }}
            />
        </div>
    );
}

function ExportStatusIcon({ status }: { status: StoredExport['status'] }) {
    if (status === 'completed') {
        return <Check className="size-3.5 shrink-0 text-green-500" />;
    }

    if (status === 'failed') {
        return <X className="size-3.5 shrink-0 text-red-500" />;
    }

    return <Loader2 className="size-3.5 shrink-0 animate-spin text-blue-500" />;
}

export function DataTableExportStatus({ activeExports, onDownload }: DataTableExportStatusProps) {
    if (activeExports.length === 0) {
        return null;
    }

    const inProgressCount = activeExports.filter(
        (e) => e.status === 'pending' || e.status === 'processing',
    ).length;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button type="button" variant="outline" size="sm" className="relative gap-1.5">
                    {inProgressCount > 0 ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <Download className="size-4" />
                    )}
                    <span>
                        {inProgressCount > 0
                            ? `${inProgressCount} exportação${inProgressCount > 1 ? 'ões' : ''} em andamento`
                            : 'Exportações'}
                    </span>
                    {inProgressCount > 0 && (
                        <span className="bg-primary absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full text-[10px] text-white">
                            {inProgressCount}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0">
                <div className="border-b px-3 py-2">
                    <p className="text-sm font-medium">Exportações recentes</p>
                </div>
                <div className="divide-y">
                    {activeExports.map((exp) => (
                        <div key={exp.uuid} className="space-y-1.5 px-3 py-2.5">
                            <div className="flex items-start gap-2">
                                <ExportStatusIcon status={exp.status} />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {exp.file_name ?? 'Preparando...'}
                                    </p>
                                    <div className="flex items-center gap-1.5">
                                        <Badge variant="secondary" className="text-[10px]">
                                            {exp.format.toUpperCase()}
                                        </Badge>
                                        {exp.status === 'processing' && exp.total_records && (
                                            <span className="text-muted-foreground text-[10px]">
                                                {exp.processed_records} / {exp.total_records} registros
                                            </span>
                                        )}
                                        {exp.status === 'failed' && (
                                            <span className="text-[10px] text-red-500">Falhou</span>
                                        )}
                                        {exp.status === 'completed' && exp.total_records && (
                                            <span className="text-[10px] text-green-600">
                                                {exp.total_records} registros
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {(exp.status === 'pending' || exp.status === 'processing') && (
                                <ExportProgressBar
                                    processed={exp.processed_records}
                                    total={exp.total_records}
                                />
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
                                    Baixar arquivo
                                </a>
                            )}
                        </div>
                    ))}
                </div>
                <div className="border-t px-3 py-2">
                    <Link
                        href="/exportacoes"
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-xs"
                    >
                        <ExternalLink className="size-3" />
                        Ver histórico completo
                    </Link>
                </div>
            </PopoverContent>
        </Popover>
    );
}
